<?php

namespace App\Exceptions\Reviews;

use RuntimeException;

class ReviewNotVerifiedPurchaserException extends RuntimeException
{
    public static function unverified(): self
    {
        return new self(__('Only verified purchasers may review this product.'));
    }
}
