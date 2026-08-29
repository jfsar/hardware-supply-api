<?php

namespace App\Exceptions\Reviews;

use RuntimeException;

class ReviewAlreadyExistsException extends RuntimeException
{
    public static function onePerProduct(): self
    {
        return new self(__('You have already reviewed this product.'));
    }
}
