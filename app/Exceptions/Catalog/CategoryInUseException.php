<?php

namespace App\Exceptions\Catalog;

use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class CategoryInUseException extends RuntimeException implements HttpExceptionInterface
{
    public function getStatusCode(): int
    {
        return 409;
    }

    /**
     * @return array<string, mixed>
     */
    public function getHeaders(): array
    {
        return [];
    }
}
