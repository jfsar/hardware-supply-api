<?php

namespace App\Http\Controllers\Api\V1\Engagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\NotificationPreferences\UpdateNotificationPreferencesRequest;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    /**
     * The customer's email preference toggles; the row is lazily created so
     * first-time readers see every category enabled (NFR-PRIV-003).
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->payload($this->preferencesFor($request->user())),
        ]);
    }

    /**
     * Update the booleans, keeping unspecified categories untouched.
     */
    public function update(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        $preferences = $this->preferencesFor($request->user());
        $preferences->fill($this->columnsFrom($request->validated()));
        $preferences->save();

        return response()->json([
            'data' => $this->payload($preferences->refresh()),
        ]);
    }

    /**
     * Loading a preference row opt-in is the documented behaviour of the
     * NotificationPreferenceGate (absent row means everything enabled).
     */
    private function preferencesFor(User $user): NotificationPreference
    {
        return NotificationPreference::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'order_updates_enabled' => true,
                'payment_updates_enabled' => true,
                'promotions_enabled' => true,
                'back_in_stock_enabled' => true,
                'price_drop_enabled' => true,
            ],
        );
    }

    /**
     * Map the wire category keys to their column names.
     *
     * @return array<string, bool>
     */
    private function columnsFrom(array $validated): array
    {
        $columns = [
            'order_updates' => 'order_updates_enabled',
            'payment_updates' => 'payment_updates_enabled',
            'promotions' => 'promotions_enabled',
            'back_in_stock' => 'back_in_stock_enabled',
            'price_drop' => 'price_drop_enabled',
        ];

        $mapped = [];

        foreach ($columns as $wire => $column) {
            if (array_key_exists($wire, $validated)) {
                $mapped[$column] = (bool) $validated[$wire];
            }
        }

        return $mapped;
    }

    /**
     * @return array{order_updates: bool, payment_updates: bool, promotions: bool, back_in_stock: bool, price_drop: bool}
     */
    private function payload(NotificationPreference $preferences): array
    {
        return [
            'order_updates' => (bool) $preferences->order_updates_enabled,
            'payment_updates' => (bool) $preferences->payment_updates_enabled,
            'promotions' => (bool) $preferences->promotions_enabled,
            'back_in_stock' => (bool) $preferences->back_in_stock_enabled,
            'price_drop' => (bool) $preferences->price_drop_enabled,
        ];
    }
}
