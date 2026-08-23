<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\Account\AccountExportReady;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;

class GenerateAccountExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Build the customer's eligible personal data export and notify them.
     *
     * Payment secrets and authentication credentials are never included (SRS §46).
     */
    public function __construct(public User $user) {}

    public function handle(): void
    {
        $user = $this->user->refresh();

        $address = $user->address()->with(['region', 'province', 'city', 'barangay'])->first();

        $payload = [
            'generated_at' => now()->toISOString(),
            'profile' => [
                'id' => $user->ulid,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'created_at' => $user->created_at?->toISOString(),
            ],
            'address' => $address !== null ? [
                'address_line1' => $address->address_line1,
                'address_line2' => $address->address_line2,
                'recipient_name' => $address->recipient_name,
                'recipient_phone' => $address->recipient_phone,
                'region' => $address->region?->name,
                'province' => $address->province?->name,
                'city' => $address->city?->name,
                'barangay' => $address->barangay?->name,
                'notes' => $address->notes,
            ] : null,
            'orders' => [],
        ];

        Storage::put("exports/{$user->ulid}.json", (string) json_encode($payload, JSON_PRETTY_PRINT));

        $user->notify(new AccountExportReady);
    }
}
