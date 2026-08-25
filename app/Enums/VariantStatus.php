<?php

namespace App\Enums;

enum VariantStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';

    /**
     * Whether the variant may be added to a cart or used to publish a product.
     */
    public function isPurchasable(): bool
    {
        return $this === self::Active;
    }
}
