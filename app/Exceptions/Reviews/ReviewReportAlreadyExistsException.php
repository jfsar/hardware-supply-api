<?php

namespace App\Exceptions\Reviews;

use RuntimeException;

class ReviewReportAlreadyExistsException extends RuntimeException
{
    public static function duplicate(): self
    {
        return new self(__('You have already reported this review.'));
    }
}
