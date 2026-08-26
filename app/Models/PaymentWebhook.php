<?php

namespace App\Models;

use App\Enums\WebhookProcessingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'provider', 'provider_event_id', 'event_type', 'signature_valid',
    'payload', 'headers', 'processing_status', 'received_at', 'processed_at',
    'processing_error',
])]
class PaymentWebhook extends Model
{
    /** @use HasFactory<Database\Factories\PaymentWebhookFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'payload' => 'array',
            'headers' => 'array',
            'processing_status' => WebhookProcessingStatus::class,
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
