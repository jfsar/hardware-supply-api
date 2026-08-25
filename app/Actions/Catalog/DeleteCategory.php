<?php

namespace App\Actions\Catalog;

use App\Enums\ProductStatus;
use App\Exceptions\Catalog\CategoryInUseException;
use App\Models\Category;
use App\Models\User;
use App\Services\RecordAuditLog;
use App\Support\CategoryTreeCache;
use Illuminate\Support\Facades\DB;

class DeleteCategory
{
    public function __construct(protected RecordAuditLog $recordAuditLog) {}

    /**
     * Soft-delete a category unless it still has children or visible products.
     */
    public function __invoke(User $actor, Category $category): void
    {
        DB::transaction(function () use ($category): void {
            /** @var Category $fresh */
            $fresh = Category::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();

            $hasChildren = $fresh->children()->exists();

            $hasVisibleProducts = $fresh->products()
                ->where('status', ProductStatus::Active->value)
                ->whereNull('deleted_at')
                ->exists();

            if ($hasChildren || $hasVisibleProducts) {
                throw new CategoryInUseException(
                    __('The category still has children or visible products.'),
                );
            }

            $fresh->delete();
        });

        $this->recordAuditLog->model($actor, 'category.deleted', $category);
        CategoryTreeCache::flush();
    }
}
