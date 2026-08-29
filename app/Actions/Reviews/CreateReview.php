<?php

namespace App\Actions\Reviews;

use App\Enums\ReviewStatus;
use App\Exceptions\Reviews\ReviewAlreadyExistsException;
use App\Exceptions\Reviews\ReviewNotVerifiedPurchaserException;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;

class CreateReview
{
    /**
     * Create (or reinstate) the customer's single review for a product.
     *
     * Only customers with at least one fully delivered line may review
     * (FR-REV-003); the unique (user_id, product_id) index backs the
     * one-review-per-product rule while soft-deleted rows are restored.
     *
     * @param  array{rating: int, title?: string|null, body: string}  $data
     */
    public function __invoke(User $user, Product $product, array $data): Review
    {
        $orderItem = $this->verifiedOrderItem($user, $product);

        if ($orderItem === null) {
            throw ReviewNotVerifiedPurchaserException::unverified();
        }

        $review = Review::withTrashed()->firstOrNew([
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        if ($review->exists && ! $review->trashed()) {
            throw ReviewAlreadyExistsException::onePerProduct();
        }

        $review->fill($data);
        $review->order_item_id = $orderItem->id;
        $review->status = ReviewStatus::Pending;
        $review->verified_purchase = true;
        $review->published_at = null;
        $review->deleted_at = null;
        $review->save();

        return $review;
    }

    /**
     * The most recent delivered line this customer holds for the product.
     */
    private function verifiedOrderItem(User $user, Product $product): ?OrderItem
    {
        /** @var OrderItem|null */
        return OrderItem::query()
            ->where('quantity_fulfilled', '>', 0)
            ->whereIn('product_variant_id', $product->variants()->pluck('id'))
            ->whereHas('order', fn (Builder $query) => $query->where('user_id', $user->id))
            ->orderByDesc('id')
            ->first();
    }
}
