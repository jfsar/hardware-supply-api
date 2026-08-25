<?php

namespace App\Models;

use App\Enums\AttributeDataType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'attribute_id', 'attribute_value_id', 'value_text', 'value_integer', 'value_decimal', 'value_boolean'])]
class ProductAttributeValue extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value_integer' => 'integer',
            'value_boolean' => 'boolean',
        ];
    }

    /**
     * The owning product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The attribute definition.
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    /**
     * The predefined option when the data type is option.
     */
    public function attributeValue(): BelongsTo
    {
        return $this->belongsTo(AttributeValue::class);
    }

    /**
     * The resolved scalar value honouring the attribute data type.
     */
    public function typedValue(): mixed
    {
        /** @var Attribute|null $attribute */
        $attribute = $this->relationLoaded('attribute') ? $this->getRelation('attribute') : null;
        $dataType = $attribute?->data_type ?? AttributeDataType::Text;

        return $dataType->read($this);
    }
}
