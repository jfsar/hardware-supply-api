<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Inventory\AdjustInventory;
use App\Enums\MovementType;
use App\Exceptions\Inventory\NegativeStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AdjustInventoryRequest;
use App\Http\Resources\Inventory\AdminInventoryMovementResource;
use App\Http\Resources\Inventory\AdminInventoryResource;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    /**
     * Paginated stock rows with derived availability (inventory.view).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $inventories = Inventory::query()
            ->with(['variant.product', 'location'])
            ->when($request->boolean('low_stock'), fn (Builder $query) => $query->lowStock())
            ->when($request->filled('location'), fn (Builder $query) => $query->whereHas(
                'location',
                fn (Builder $location) => $location->where('ulid', (string) $request->input('location')),
            ))
            ->when($request->filled('sku'), fn (Builder $query) => $query->whereHas(
                'variant',
                fn (Builder $variant) => $variant->where('sku', 'like', '%'.(string) $request->input('sku').'%'),
            ))
            ->orderByDesc('updated_at')
            ->paginate(min((int) $request->input('per_page', 25), 100));

        return AdminInventoryResource::collection($inventories);
    }

    /**
     * Paginated ledger history (inventory.view).
     */
    public function movements(Request $request): AnonymousResourceCollection
    {
        $movements = InventoryMovement::query()
            ->with(['variant', 'location', 'performedBy'])
            ->when($request->filled('variant'), fn (Builder $query) => $query->whereHas(
                'variant',
                fn (Builder $variant) => $variant->where('ulid', (string) $request->input('variant')),
            ))
            ->when($request->filled('type'), function (Builder $query) use ($request): void {
                $type = MovementType::tryFrom((string) $request->input('type'));

                throw_if($type === null, ValidationException::withMessages([
                    'type' => __('The selected movement type is invalid.'),
                ]));

                $query->where('movement_type', $type);
            })
            ->when($request->filled('date_from'), fn (Builder $query) => $query->where('created_at', '>=', (string) $request->input('date_from')))
            ->when($request->filled('date_to'), fn (Builder $query) => $query->where('created_at', '<=', (string) $request->input('date_to').' 23:59:59.999999'))
            ->orderByDesc('id')
            ->paginate(min((int) $request->input('per_page', 25), 100));

        return AdminInventoryMovementResource::collection($movements);
    }

    /**
     * Apply a signed stock adjustment for one variant (inventory.adjust).
     *
     * @throws NegativeStockException when the adjustment would drive stock negative
     */
    public function adjust(AdjustInventoryRequest $request, ProductVariant $variant, AdjustInventory $adjust): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $location = $request->filled('location')
            ? Location::query()->where('ulid', (string) $request->validated('location'))->firstOrFail()
            : null;

        $inventory = $adjust(
            $user,
            $variant,
            $request->quantityDelta(),
            $request->movementType(),
            (string) $request->validated('reason'),
            $location,
        );

        return response()->json([
            'data' => new AdminInventoryResource($inventory->load(['variant.product', 'location'])),
        ]);
    }
}
