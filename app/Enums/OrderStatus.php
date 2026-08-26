<?php

namespace App\Enums;

/**
 * Order lifecycle states (SRS §17) plus Expired, which the Phase 4 guide's
 * transitions map adds for abandoned awaiting-payment orders. Transitions
 * are validated through canTransitionTo(); illegal moves raise 409
 * ORDER_STATE_INVALID.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';
    case Processing = 'processing';
    case Packed = 'packed';
    case PartiallyFulfilled = 'partially_fulfilled';
    case Fulfilled = 'fulfilled';
    case Shipped = 'shipped';
    case PartiallyDelivered = 'partially_delivered';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case PartiallyCancelled = 'partially_cancelled';
    case Returned = 'returned';
    case PartiallyReturned = 'partially_returned';
    case Refunded = 'refunded';
    case Expired = 'expired';

    /**
     * Allowed forward transitions per SRS §17/§54 and the Phase 4 guide.
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        self::Pending->value => [
            self::AwaitingPayment->value,
            self::PartiallyCancelled->value,
            self::Cancelled->value,
        ],
        self::AwaitingPayment->value => [
            self::Paid->value,
            self::PartiallyCancelled->value,
            self::Cancelled->value,
            self::Expired->value,
        ],
        self::Paid->value => [
            self::Processing->value,
            self::PartiallyCancelled->value,
            self::Cancelled->value,
        ],
        self::Processing->value => [
            self::Packed->value,
            self::PartiallyFulfilled->value,
            self::PartiallyCancelled->value,
            self::Cancelled->value,
        ],
        self::Packed->value => [
            self::Shipped->value,
            self::PartiallyFulfilled->value,
            self::PartiallyCancelled->value,
            self::Cancelled->value,
        ],
        self::PartiallyFulfilled->value => [
            self::Fulfilled->value,
            self::Shipped->value,
            self::PartiallyCancelled->value,
            self::Cancelled->value,
        ],
        self::Fulfilled->value => [self::Shipped->value, self::PartiallyDelivered->value],
        self::Shipped->value => [self::PartiallyDelivered->value, self::Delivered->value],
        self::PartiallyDelivered->value => [
            self::Delivered->value,
            self::Completed->value,
            self::PartiallyReturned->value,
        ],
        self::Delivered->value => [
            self::Completed->value,
            self::Returned->value,
            self::PartiallyReturned->value,
        ],
        self::Completed->value => [],
        self::PartiallyCancelled->value => [self::Cancelled->value],
        self::Cancelled->value => [],
        self::Expired->value => [],
        self::Returned->value => [self::Refunded->value],
        self::PartiallyReturned->value => [self::Returned->value, self::Refunded->value],
        self::Refunded->value => [],
    ];

    /**
     * Whether moving from this state to the target is legal (FR-ORD-003).
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target->value, self::TRANSITIONS[$this->value] ?? [], true);
    }

    /**
     * Statuses from which the customer-facing cancel flow may start.
     * Partially-cancelled orders keep accepting further line cancels.
     */
    public function isCancellable(): bool
    {
        return in_array($this, [
            self::Pending,
            self::AwaitingPayment,
            self::Paid,
            self::Processing,
            self::Packed,
            self::PartiallyFulfilled,
            self::PartiallyCancelled,
        ], true);
    }
}
