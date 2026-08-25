<?php

namespace App\Enums;

use App\Models\AttributeValue;
use Illuminate\Database\Eloquent\Model;

enum AttributeDataType: string
{
    case Text = 'text';
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Boolean = 'boolean';
    case Option = 'option';

    /**
     * Persist a validated scalar onto the typed value columns of a pivot row.
     */
    public function apply(Model $row, mixed $value): void
    {
        match ($this) {
            self::Text, self::Option => $row->fill(['value_text' => (string) $value]),
            self::Integer => $row->fill(['value_integer' => (int) $value]),
            self::Decimal => $row->fill(['value_decimal' => (float) $value]),
            self::Boolean => $row->fill(['value_boolean' => (bool) $value]),
        };
    }

    /**
     * Read the typed scalar back off an attribute-value style model.
     */
    public function read(AttributeValue|Model $row): mixed
    {
        return match ($this) {
            self::Text, self::Option => $row->value_text,
            self::Integer => $row->value_integer,
            self::Decimal => $row->value_decimal !== null ? (float) $row->value_decimal : null,
            self::Boolean => $row->value_boolean,
        };
    }
}
