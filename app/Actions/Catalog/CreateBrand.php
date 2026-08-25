<?php

namespace App\Actions\Catalog;

use App\Models\Brand;
use App\Models\User;
use App\Services\GenerateUniqueSlug;
use App\Services\RecordAuditLog;

class CreateBrand
{
    public function __construct(
        protected GenerateUniqueSlug $generateUniqueSlug,
        protected RecordAuditLog $recordAuditLog,
    ) {}

    /**
     * Create a brand.
     *
     * @param  array<string, mixed>  $data
     */
    public function __invoke(User $actor, array $data): Brand
    {
        $brand = Brand::query()->create([
            'name' => (string) $data['name'],
            'slug' => ($this->generateUniqueSlug)('brands', (string) $data['name']),
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);

        $this->recordAuditLog->model($actor, 'brand.created', $brand);

        return $brand;
    }
}
