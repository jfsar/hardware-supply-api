<?php

namespace Database\Factories;

use App\Enums\AttributeDataType;
use App\Models\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attribute>
 */
class AttributeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'data_type' => AttributeDataType::Text,
            'unit' => null,
            'is_filterable' => false,
            'is_variant_defining' => false,
        ];
    }

    /**
     * Indicate the attribute stores predefined option values.
     */
    public function option(): static
    {
        return $this->state(fn (array $attributes) => [
            'data_type' => AttributeDataType::Option,
        ]);
    }

    /**
     * Indicate the attribute distinguishes variants (size, color...).
     */
    public function variantDefining(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_variant_defining' => true,
        ]);
    }

    /**
     * Indicate the attribute feeds search filters/facets.
     */
    public function filterable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_filterable' => true,
        ]);
    }
}
