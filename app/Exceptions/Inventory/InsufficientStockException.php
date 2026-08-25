<?php

namespace App\Exceptions\Inventory;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    /**
     * Offending SKUs with their shortfall details.
     *
     * @var array<string, float>
     */
    public readonly array $skus;

    /**
     * @param  array<string, float>  $shortfalls  sku => requested quantity
     */
    public static function forSkus(array $shortfalls): self
    {
        $exception = new self(__('Insufficient stock for the requested items.'));
        $exception->skus = $shortfalls;

        return $exception;
    }

    /**
     * The offending SKUs, ready for error envelope details.
     *
     * @return array<string, float>
     */
    public function details(): array
    {
        return ['skus' => $this->skus];
    }
}
