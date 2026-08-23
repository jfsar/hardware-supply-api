<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Customers\DeleteSavedAddress;
use App\Actions\Customers\SaveAddress;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\SaveAddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Region;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    /**
     * Show the customer's saved address, when present (FR-CUST-002).
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $address = $user->address()->with(['region', 'province', 'city', 'barangay', 'postalCode'])->first();

        return response()->json([
            'data' => $address !== null ? (new AddressResource($address))->resolve() : null,
        ]);
    }

    /**
     * Create or replace the saved address (FR-CUST-002/003/005).
     */
    public function update(SaveAddressRequest $request, SaveAddress $save): JsonResponse
    {
        $address = $save(
            $request->user(),
            array_merge($request->validated(), ['country_id' => $this->countryIdFor((int) $request->validated('region_id'))]),
        );

        return response()->json([
            'data' => new AddressResource($address->load(['region', 'province', 'city', 'barangay', 'postalCode'])),
        ], 200);
    }

    /**
     * Remove the saved address; historical order addresses are unaffected.
     */
    public function destroy(Request $request, DeleteSavedAddress $delete): JsonResponse
    {
        $delete($request->user());

        return response()->json([
            'data' => [
                'message' => __('Address removed.'),
            ],
        ]);
    }

    /**
     * Resolve the country id from the submitted region (PH-only scope).
     */
    private function countryIdFor(int $regionId): int
    {
        $region = Region::query()->findOrFail($regionId);

        return (int) $region->country_id;
    }
}
