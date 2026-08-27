<?php

namespace App\Actions\Fulfillment;

use App\Enums\ShipmentStatus;
use App\Events\ShipmentDelivered;
use App\Events\ShipmentDispatched;
use App\Models\Shipment;
use App\Models\ShipmentTrackingEvent;
use App\Models\User;
use App\Services\RecordAuditLog;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Append a tracking event to a shipment's timeline (Phase 6 Task 4).
 * Events are append-only and never mutate earlier rows; the shipment's
 * status and actual timestamps (shipped/delivered/picked-up) move to
 * match the latest event. Estimated delivery is never overwritten
 * (FR-SHIP-007).
 */
class RecordTrackingEvent
{
    public function __construct(protected RecordAuditLog $recordAuditLog) {}

    /**
     * @param  array<string, mixed>|null  $rawPayload  optional provider scan payload
     */
    public function __invoke(
        Shipment $shipment,
        ?User $actor,
        ShipmentStatus $status,
        ?string $locationText = null,
        ?CarbonInterface $eventAt = null,
        ?string $description = null,
        ?array $rawPayload = null,
    ): ShipmentTrackingEvent {
        $event = DB::transaction(function () use ($shipment, $actor, $status, $locationText, $eventAt, $description, $rawPayload): ShipmentTrackingEvent {
            /** @var Shipment $locked */
            $locked = Shipment::query()->lockForUpdate()->findOrFail($shipment->getKey());

            $at = $eventAt ?? now();

            $event = $locked->trackingEvents()->create([
                'status' => $status->value,
                'location_text' => $locationText,
                'event_at' => $at,
                'description' => $description,
                'raw_payload' => $rawPayload,
            ]);

            $attributes = ['status' => $status];

            if ($status === ShipmentStatus::Shipped && $locked->shipped_at === null) {
                $attributes['shipped_at'] = $at;
            }

            if ($status === ShipmentStatus::Delivered && $locked->delivered_at === null) {
                $attributes['delivered_at'] = $at;
            }

            if ($status === ShipmentStatus::PickedUp && $locked->picked_up_at === null) {
                $attributes['picked_up_at'] = $at;
            }

            $locked->forceFill($attributes)->save();

            ($this->recordAuditLog)($actor, 'shipment.tracking_event.created', 'Shipment', (int) $locked->getKey(), null, [
                'shipment_number' => $locked->shipment_number,
                'status' => $status->value,
                'event_at' => $at->toIso8601String(),
                'location_text' => $locationText,
            ]);

            return $event;
        });

        $this->emitDomainEvents($shipment, $status);

        return $event;
    }

    /**
     * Fired after the commit so the queued notification serializes a fully
     * committed model (SRS §26). Guarded by the original DB-level status,
     * so duplicate scans of the same status never re-notify the customer.
     */
    protected function emitDomainEvents(Shipment $shipment, ShipmentStatus $status): void
    {
        $was = $shipment->getOriginal('status');
        $wasValue = $was instanceof ShipmentStatus ? $was->value : (string) $was;

        if ($wasValue === $status->value) {
            return;
        }

        match ($status) {
            ShipmentStatus::Shipped, ShipmentStatus::ReadyForPickup => event(new ShipmentDispatched($shipment->refresh())),
            ShipmentStatus::Delivered, ShipmentStatus::PickedUp => event(new ShipmentDelivered($shipment->refresh())),
            default => null,
        };
    }
}
