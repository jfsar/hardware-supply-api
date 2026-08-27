<?php

namespace App\Enums;

/**
 * Lifecycle states for individual shipments (SRS §23, FR-SHIP-003).
 * Tracks a package from creation through delivery or return.
 */
enum ShipmentStatus: string
{
    case Pending = 'pending';
    case Packed = 'packed';
    case Shipped = 'shipped';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Returned = 'returned';
    case ReadyForPickup = 'ready_for_pickup';
    case PickedUp = 'picked_up';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Packed => 'Packed',
            self::Shipped => 'Shipped',
            self::InTransit => 'In Transit',
            self::OutForDelivery => 'Out for Delivery',
            self::Delivered => 'Delivered',
            self::Failed => 'Failed',
            self::Returned => 'Returned',
            self::ReadyForPickup => 'Ready for Pickup',
            self::PickedUp => 'Picked Up',
        };
    }

    /**
     * Whether the shipment is still in progress (not terminal).
     */
    public function isActive(): bool
    {
        return ! in_array($this, [
            self::Delivered,
            self::Failed,
            self::Returned,
            self::PickedUp,
        ], true);
    }

    /**
     * Whether the shipment has reached a final state.
     */
    public function isTerminal(): bool
    {
        return ! $this->isActive();
    }

    /**
     * Whether the shipment is awaiting customer pickup.
     */
    public function isAwaitingPickup(): bool
    {
        return $this === self::ReadyForPickup;
    }
}
