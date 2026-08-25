<?php

namespace App\Exceptions\Inventory;

use RuntimeException;

class NegativeStockException extends RuntimeException
{
    /**
     * The SKU whose adjustment would drive stock negative.
     */
    public readonly string $sku;

    public static function forSku(string $sku): self
    {
        $exception = new self(__('Stock on hand cannot be driven below zero for SKU :sku.', ['sku' => $sku]));
        $exception->sku = $sku;

        return $exception;
    }
}
