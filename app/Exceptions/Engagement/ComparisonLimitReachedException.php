<?php

namespace App\Exceptions\Engagement;

use RuntimeException;

class ComparisonLimitReachedException extends RuntimeException
{
    public static function limit(): self
    {
        return new self(__('The comparison already holds the maximum number of products.'));
    }
}
