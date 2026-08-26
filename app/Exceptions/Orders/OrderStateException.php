<?php

namespace App\Exceptions\Orders;

use App\Enums\OrderStatus;
use RuntimeException;

/**
 * Illegal state transition attempt on an order (FR-ORD-003).
 */
class OrderStateException extends RuntimeException
{
    public readonly string $currentStatus;

    public readonly string $targetStatus;

    public static function illegalTransition(OrderStatus $current, OrderStatus $target): self
    {
        $exception = new self(__('The requested order action is not allowed in its current state.'));
        $exception->currentStatus = $current->value;
        $exception->targetStatus = $target->value;

        return $exception;
    }

    /**
     * @return array<string, string>
     */
    public function details(): array
    {
        return [
            'current_status' => $this->currentStatus,
            'target_status' => $this->targetStatus,
        ];
    }
}
