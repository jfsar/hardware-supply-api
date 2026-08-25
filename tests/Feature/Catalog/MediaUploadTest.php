<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\ProductImage;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithSanctum;
use Tests\TestCase;

class MediaUploadTest extends TestCase
{
    use InteractsWithSanctum, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
        Storage::fake('r2');
    }

    /**
     * A catalog manager with a token-authenticated product.
     *
     * @return array{0: User, 1: Product}
     */
    private function managerWithProduct(): array
    {
        $manager = User::factory()->create();
        $manager->roles()->attach(Role::query()->where('slug', 'catalog_manager')->value('id'));

        $product = Product::factory()->draft()->create();

        return [$manager, $product];
    }

    public function test_image_upload_stores_with_server_generated_name_on_media_disk(): void
    {
        [$manager, $product] = $this->managerWithProduct();

        $response = $this->actingAsToken($manager)
            ->postJson("/api/v1/admin/products/{$product->ulid}/images", [
                'image' => UploadedFile::fake()->image('my-vacation-photo.jpg', 800, 600),
                'is_primary' => true,
            ]);

        $response->assertCreated();

        /** @var ProductImage $image */
        $image = $product->images()->sole();

        // Server-side ULID filename under products/{ulid}/; original name discarded.
        $this->assertMatchesRegularExpression(
            '/^products\/'.$product->ulid.'\/[0-9A-HJ-NP-TV-Z]{26}\.jpg$/',
            $image->path,
        );
        $this->assertSame('r2', $image->storage_disk);
        $this->assertSame(800, $image->width);
        $this->assertSame(600, $image->height);
        $this->assertTrue($image->is_primary);

        Storage::disk('r2')->assertExists($image->path);
        $this->assertStringContainsString('/'.$image->path, (string) parse_url($response->json('data.url'), PHP_URL_PATH));
    }

    public function test_rejected_mime_types_and_oversize_files_are_refused(): void
    {
        [$manager, $product] = $this->managerWithProduct();
        $base = "/api/v1/admin/products/{$product->ulid}/images";

        $gif = $this->actingAsToken($manager)->postJson($base, [
            'image' => UploadedFile::fake()->create('animation.gif', 100, 'image/gif'),
        ]);
        $gif->assertStatus(422);
        $this->assertArrayHasKey('image', $gif->json('error.details.fields'));

        $oversizeKb = ((int) config('catalog.image.max_kb')) + 1;
        $huge = $this->actingAsToken($manager)->postJson($base, [
            'image' => UploadedFile::fake()->create('huge.jpg', $oversizeKb, 'image/jpeg'),
        ]);
        $huge->assertStatus(422);

        $this->assertSame(0, $product->images()->count());
        Storage::disk('r2')->assertDirectoryEmpty('products/'.$product->ulid);
    }

    public function test_single_primary_rule_is_enforced_across_images(): void
    {
        [$manager, $product] = $this->managerWithProduct();
        $base = "/api/v1/admin/products/{$product->ulid}/images";

        // The first upload becomes primary by default.
        $first = $this->actingAsToken($manager)->postJson($base, [
            'image' => UploadedFile::fake()->image('first.jpg'),
        ]);
        $first->assertCreated();

        $second = $this->actingAsToken($manager)->postJson($base, [
            'image' => UploadedFile::fake()->image('second.jpg'),
        ]);
        $second->assertCreated();

        // An explicit primary request steals the flag from every other image.
        $third = $this->actingAsToken($manager)->postJson($base, [
            'image' => UploadedFile::fake()->image('third.jpg'),
            'is_primary' => true,
        ]);
        $third->assertCreated();

        $primaries = $product->images()->where('is_primary', true)->get();

        $this->assertCount(1, $primaries);
        $this->assertSame((int) $third->json('data.id'), (int) $primaries->sole()->id);

        /** @var ProductImage $firstRow */
        $firstRow = ProductImage::query()->findOrFail((int) $first->json('data.id'));
        $this->assertFalse($firstRow->is_primary);

        /** @var ProductImage $secondRow */
        $secondRow = ProductImage::query()->findOrFail((int) $second->json('data.id'));
        $this->assertFalse($secondRow->is_primary);
    }

    public function test_image_deletion_removes_row_audit_and_file(): void
    {
        [$manager, $product] = $this->managerWithProduct();

        $upload = $this->actingAsToken($manager)
            ->postJson("/api/v1/admin/products/{$product->ulid}/images", [
                'image' => UploadedFile::fake()->image('doomed.jpg'),
            ]);
        $upload->assertCreated();

        $path = $upload->json('data.path');
        Storage::disk('r2')->assertExists($path);

        $deleted = $this->actingAsToken($manager)
            ->deleteJson("/api/v1/admin/products/{$product->ulid}/images/{$upload->json('data.id')}");

        $deleted->assertOk();
        $this->assertSame(0, $product->images()->count());
        Storage::disk('r2')->assertMissing($path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product_image.deleted']);
    }

    public function test_document_upload_accepts_pdf_only(): void
    {
        [$manager, $product] = $this->managerWithProduct();
        $base = "/api/v1/admin/products/{$product->ulid}/documents";

        $pdfContent = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[]/Count 0>>endobj\ntrailer<</Root 1 0 R>>";
        $accepted = $this->actingAsToken($manager)->postJson($base, [
            'title' => 'User Manual',
            'document' => UploadedFile::fake()->createWithContent('manual.pdf', $pdfContent),
        ]);

        $accepted->assertCreated();
        $accepted->assertJsonPath('data.title', 'User Manual');

        /** @var ProductDocument $document */
        $document = $product->documents()->sole();
        $this->assertSame('application/pdf', $document->mime_type);
        $this->assertMatchesRegularExpression('/\.pdf$/', $document->path);
        Storage::disk('r2')->assertExists($document->path);

        $rejected = $this->actingAsToken($manager)->postJson($base, [
            'title' => 'Not a PDF',
            'document' => UploadedFile::fake()->createWithContent('manual.docx', 'plain text'),
        ]);
        $rejected->assertStatus(422);

        $missingTitle = $this->actingAsToken($manager)->postJson($base, [
            'document' => UploadedFile::fake()->createWithContent('manual.pdf', $pdfContent),
        ]);
        $missingTitle->assertStatus(422);
    }

    public function test_media_endpoints_require_permission(): void
    {
        [, $product] = $this->managerWithProduct();
        $outsider = User::factory()->create();

        $this->actingAsToken($outsider)
            ->postJson("/api/v1/admin/products/{$product->ulid}/images", [
                'image' => UploadedFile::fake()->image('nope.jpg'),
            ])
            ->assertStatus(403);
    }
}
