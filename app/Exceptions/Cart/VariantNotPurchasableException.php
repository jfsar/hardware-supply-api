<?php

namespace App\Exceptions\Cart;

use RuntimeException;

class VariantNotPurchasableException extends RuntimeException
{
    public readonly string $sku;

    public static function forSku(string $sku): self
    {
        $exception = new self(__('This item is no longer available.'));
        $exception->sku = $sku;

        return $exception;
    }

    /**
     * @return array<string, string>
     */
    public function details(): array
    {
        return ['sku' => $this->sku];
    }
}
