<?php

namespace App\Enums;

enum ProductStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';

    /**
     * Only active products are rendered through the public catalog (NFR-DATA-006).
     */
    public function isPubliclyVisible(): bool
    {
        return $this === self::Active;
    }
}
