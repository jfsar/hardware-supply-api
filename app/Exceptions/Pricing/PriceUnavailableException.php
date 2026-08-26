<?php

namespace App\Exceptions\Pricing;

use RuntimeException;

class PriceUnavailableException extends RuntimeException
{
    public readonly string $sku;

    public static function forSku(string $sku): self
    {
        $exception = new self(__('No active price could be resolved for this item.'));
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
