<?php

namespace App\Actions\Catalog;

use App\Models\Brand;
use App\Models\User;
use App\Services\RecordAuditLog;
use Illuminate\Support\Facades\DB;

class DeleteBrand
{
    public function __construct(protected RecordAuditLog $recordAuditLog) {}

    /**
     * Soft-delete a brand; products keep their history via nullOnDelete.
     */
    public function __invoke(User $actor, Brand $brand): void
    {
        DB::transaction(function () use ($brand): void {
            /** @var Brand $fresh */
            $fresh = Brand::query()->whereKey($brand->id)->lockForUpdate()->firstOrFail();

            $fresh->delete();
        });

        $this->recordAuditLog->model($actor, 'brand.deleted', $brand);
    }
}
