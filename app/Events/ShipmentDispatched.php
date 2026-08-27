<?php

namespace App\Events;

use App\Models\Shipment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a delivery shipment ships, or a pickup shipment becomes
 * ready for pickup (Phase 6 Task 6, SRS §26). Listeners queue the
 * customer-facing dispatch email.
 */
class ShipmentDispatched
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Shipment $shipment) {}
}
