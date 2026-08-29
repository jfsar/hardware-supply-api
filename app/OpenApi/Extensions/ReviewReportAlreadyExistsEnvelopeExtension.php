<?php

namespace App\OpenApi\Extensions;

use App\Exceptions\Reviews\ReviewReportAlreadyExistsException;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

final class ReviewReportAlreadyExistsEnvelopeExtension extends ApiErrorEnvelopeExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(ReviewReportAlreadyExistsException::class);
    }

    protected function status(): int
    {
        return 409;
    }

    protected function errorCode(): string
    {
        return 'REVIEW_REPORT_ALREADY_EXISTS';
    }

    protected function summary(): string
    {
        return 'The customer already reported this review';
    }
}
