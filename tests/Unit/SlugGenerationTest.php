<?php

namespace Tests\Unit;

use App\Models\Brand;
use App\Services\GenerateUniqueSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlugGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_basic_names_are_slugified(): void
    {
        $slug = (new GenerateUniqueSlug)('products', 'Claw Hammer 16oz!');

        $this->assertSame('claw-hammer-16oz', $slug);
    }

    public function test_collisions_receive_an_incrementing_suffix(): void
    {
        $first = (new GenerateUniqueSlug)('brands', 'Power Grip');
        Brand::query()->create(['name' => 'Power Grip', 'slug' => $first]);

        $second = (new GenerateUniqueSlug)('brands', 'Power   Grip');
        Brand::query()->create(['name' => 'Power Grip', 'slug' => $second]);

        $third = (new GenerateUniqueSlug)('brands', 'POWER GRIP');
        Brand::query()->create(['name' => 'Power Grip', 'slug' => $third]);

        $this->assertSame('power-grip', $first);
        $this->assertSame('power-grip-2', $second);
        $this->assertSame('power-grip-3', $third);
    }

    public function test_soft_deleted_rows_still_reserve_their_slug(): void
    {
        $brand = Brand::factory()->create(['name' => 'Torq Masters', 'slug' => 'torq-masters']);
        $brand->delete();

        $generated = (new GenerateUniqueSlug)('brands', 'Torq Masters');

        $this->assertSame('torq-masters-2', $generated);
    }

    public function test_ignore_id_excludes_the_row_being_updated(): void
    {
        $brand = Brand::factory()->create(['name' => 'Hexline', 'slug' => 'hexline']);

        $regenerated = (new GenerateUniqueSlug)('brands', 'Hexline', $brand->id);

        $this->assertSame('hexline', $regenerated);
    }

    public function test_empty_slugs_fall_back_to_a_ulid(): void
    {
        $generated = (new GenerateUniqueSlug)('categories', '???');

        $this->assertMatchesRegularExpression('/^[0-9a-z]{26}$/', $generated);
    }
}
