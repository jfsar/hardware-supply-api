<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ManagesCatalog;
use Tests\TestCase;

class CategoryBrandManagementTest extends TestCase
{
    use ManagesCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCatalogPermissions();
    }

    public function test_manager_creates_a_nested_category_with_seo_fields(): void
    {
        $manager = $this->catalogManager();
        $parent = Category::factory()->create();

        $response = $this->actingAsToken($manager)
            ->postJson('/api/v1/admin/categories', [
                'name' => 'Power Tools',
                'parent_id' => $parent->id,
                'sort_order' => 5,
                'status' => 'active',
                'seo_title' => 'Buy Power Tools Online',
                'seo_description' => 'Drills, grinders and saws for pros.',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.slug', 'power-tools');
        $response->assertJsonPath('data.parent_id', $parent->id);
        $response->assertJsonPath('data.seo.title', 'Buy Power Tools Online');

        $this->assertDatabaseHas('categories', ['slug' => 'power-tools', 'seo_title' => 'Buy Power Tools Online']);
    }

    public function test_category_update_supports_partial_payload(): void
    {
        $manager = $this->catalogManager();
        $category = Category::factory()->create(['seo_description' => null]);

        $response = $this->actingAsToken($manager)
            ->patchJson("/api/v1/admin/categories/{$category->ulid}", [
                'seo_title' => 'Fresh Title',
            ]);

        $response->assertOk();
        $this->assertSame('Fresh Title', $category->fresh()->seo_title);
        $this->assertEquals($category->name, $category->fresh()->name);
    }

    public function test_category_cannot_become_its_own_child(): void
    {
        $manager = $this->catalogManager();
        $parent = Category::factory()->create();
        $child = Category::factory()->childOf($parent)->create();

        $response = $this->actingAsToken($manager)
            ->patchJson("/api/v1/admin/categories/{$parent->ulid}", [
                'parent_id' => $child->id,
            ]);

        $response->assertStatus(422);
        $this->assertNull($parent->fresh()->parent_id);
    }

    public function test_deleting_a_category_with_children_is_blocked(): void
    {
        $manager = $this->catalogManager();
        $parent = Category::factory()->create();
        Category::factory()->childOf($parent)->create();

        $response = $this->actingAsToken($manager)
            ->deleteJson("/api/v1/admin/categories/{$parent->ulid}");

        $response->assertStatus(409);
        $response->assertJsonPath('error.code', 'CATEGORY_IN_USE');
        $this->assertNull($parent->fresh()->deleted_at);
    }

    public function test_deleting_a_category_with_visible_products_is_blocked(): void
    {
        $manager = $this->catalogManager();
        $category = Category::factory()->create();
        Product::factory()->active()->inCategory($category)->create();

        $response = $this->actingAsToken($manager)
            ->deleteJson("/api/v1/admin/categories/{$category->ulid}");

        $response->assertStatus(409);
        $response->assertJsonPath('error.code', 'CATEGORY_IN_USE');
    }

    public function test_an_unused_category_can_be_deleted_and_restores_cache(): void
    {
        $manager = $this->catalogManager();
        $category = Category::factory()->create();
        Product::factory()->draft()->inCategory($category)->create();

        $response = $this->actingAsToken($manager)
            ->deleteJson("/api/v1/admin/categories/{$category->ulid}");

        $response->assertOk();
        $this->assertSoftDeleted($category);

        $this->assertDatabaseHas('audit_logs', ['action' => 'category.deleted', 'resource_id' => $category->id]);
    }

    public function test_brand_crud_flow_is_audited(): void
    {
        $manager = $this->catalogManager();

        $created = $this->actingAsToken($manager)
            ->postJson('/api/v1/admin/brands', ['name' => 'Bosch Professional', 'description' => 'Power tools.']);

        $created->assertCreated();
        $created->assertJsonPath('data.slug', 'bosch-professional');

        /** @var Brand $brand */
        $brand = Brand::query()->where('slug', 'bosch-professional')->firstOrFail();

        $updated = $this->actingAsToken($manager)
            ->patchJson("/api/v1/admin/brands/{$brand->ulid}", ['status' => 'inactive']);

        $updated->assertOk();
        $updated->assertJsonPath('data.status', 'inactive');

        $deleted = $this->actingAsToken($manager)
            ->deleteJson("/api/v1/admin/brands/{$brand->ulid}");

        $deleted->assertOk();
        $this->assertSoftDeleted($brand);

        foreach (['brand.created', 'brand.updated', 'brand.deleted'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action, 'resource_id' => $brand->id]);
        }
    }

    public function test_categories_manage_permission_guards_the_surface(): void
    {
        $customer = $this->plainStaffUser();

        $this->actingAsToken($customer)
            ->getJson('/api/v1/admin/categories')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->actingAsToken($customer)
            ->postJson('/api/v1/admin/brands', ['name' => 'Nope'])
            ->assertStatus(403);
    }
}
