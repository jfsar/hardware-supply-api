<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'name', 'code', 'promotion_type', 'discount_type', 'discount_value',
    'max_discount_amount_minor', 'starts_at', 'ends_at', 'usage_limit',
    'per_customer_limit', 'is_stackable', 'priority', 'status',
])]
class Promotion extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Bootstrap the model and its attributes.
     */
    protected static function booted(): void
    {
        static::creating(function (Promotion $promotion): void {
            $promotion->ulid ??= (string) Str::ulid();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'promotion_type' => PromotionType::class,
            'discount_type' => DiscountType::class,
            'status' => PromotionStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_stackable' => 'boolean',
        ];
    }

    /**
     * Structured eligibility rules (e.g. buy_x_get_y quantities).
     */
    public function rules(): HasMany
    {
        return $this->hasMany(PromotionRule::class);
    }

    /**
     * A rule row of the given type, if configured.
     */
    public function ruleOfType(string $ruleType): ?PromotionRule
    {
        return $this->rules->first(fn (PromotionRule $rule): bool => $rule->rule_type === $ruleType);
    }

    /**
     * Whether this promotion applies without a coupon code: it has no
     * code of its own and no coupon references it (Phase 4 Task 5).
     */
    public function isAutoApplicable(): bool
    {
        return $this->code === null
            && ! Coupon::query()->where('promotion_id', $this->id)->exists();
    }
}
