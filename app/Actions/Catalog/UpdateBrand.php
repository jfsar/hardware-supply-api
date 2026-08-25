<?php

namespace App\Actions\Catalog;

use App\Models\Brand;
use App\Models\User;
use App\Services\RecordAuditLog;

class UpdateBrand
{
    public function __construct(protected RecordAuditLog $recordAuditLog) {}

    /**
     * Apply a partial brand update.
     *
     * @param  array<string, mixed>  $data
     */
    public function __invoke(User $actor, Brand $brand, array $data): Brand
    {
        foreach (['name', 'description', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $brand->{$field} = $data[$field];
            }
        }

        $brand->save();

        $this->recordAuditLog->model($actor, 'brand.updated', $brand);

        return $brand;
    }
}
