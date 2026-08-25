<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['attribute_id', 'value_text', 'value_integer', 'value_decimal', 'value_boolean', 'sort_order'])]
class AttributeValue extends Model
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
            'sort_order' => 'integer',
        ];
    }

    /**
     * The attribute this option belongs to.
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}
