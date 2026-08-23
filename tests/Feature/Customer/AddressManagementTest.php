<?php

namespace Tests\Feature\Customer;

use App\Console\Commands\ImportPsgc;
use App\Models\Barangay;
use App\Models\City;
use App\Models\CustomerAddress;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\InteractsWithSanctum;
use Tests\TestCase;

class AddressManagementTest extends TestCase
{
    use InteractsWithSanctum;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call(ImportPsgc::class, ['path' => __DIR__.'/../../fixtures/psgc/sample.csv']);

        $this->user = User::factory()->create();
    }

    public function test_show_returns_null_when_no_address_is_saved(): void
    {
        $this->actingAsToken($this->user)
            ->getJson('/api/v1/address')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_a_customer_can_save_one_address(): void
    {
        $response = $this->actingAsToken($this->user)
            ->putJson('/api/v1/address', $this->validPayload());

        $response->assertOk()
            ->assertJsonPath('data.recipient_name', 'Juan Dela Cruz')
            ->assertJsonPath('data.city.name', 'Meycauayan City')
            ->assertJsonPath('data.barangay.name', 'Salvacion');

        $this->assertDatabaseCount(CustomerAddress::class, 1);
    }

    public function test_saving_again_replaces_the_single_address_without_duplicating(): void
    {
        $this->actingAsToken($this->user);

        $this->putJson('/api/v1/address', $this->validPayload())->assertOk();
        $this->putJson('/api/v1/address', array_merge($this->validPayload(), [
            'address_line1' => '456 New Street',
        ]))->assertOk();

        $this->assertDatabaseCount(CustomerAddress::class, 1);
        $this->assertDatabaseHas(CustomerAddress::class, ['address_line1' => '456 New Street']);
    }

    public function test_ncr_style_cities_accept_a_null_province(): void
    {
        $pasay = City::query()->where('name', 'Pasay')->firstOrFail();
        $barangay = Barangay::query()->where('city_id', $pasay->id)->firstOrFail();

        $this->actingAsToken($this->user)
            ->putJson('/api/v1/address', array_merge($this->validPayload(), [
                'region_id' => $pasay->region_id,
                'province_id' => null,
                'city_id' => $pasay->id,
                'barangay_id' => $barangay->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.province', null)
            ->assertJsonPath('data.city.name', 'Pasay');
    }

    public function test_hierarchy_mismatches_are_rejected(): void
    {
        $meycauayanId = City::query()->where('name', 'Meycauayan City')->value('id');
        $mismatchedBarangay = Barangay::query()->where('city_id', '!=', $meycauayanId)->firstOrFail();

        $this->actingAsToken($this->user)
            ->putJson('/api/v1/address', array_merge($this->validPayload(), [
                'barangay_id' => $mismatchedBarangay->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields.barangay_id.0', fn (string $message) => $message !== '');

        // Province from a different region than the city.
        $foreignProvince = Province::query()
            ->where('id', '!=', $meycauayanId ? City::query()->find($meycauayanId)->province_id : null)
            ->firstOrFail();

        $response = $this->actingAsToken($this->user)
            ->putJson('/api/v1/address', array_merge($this->validPayload(), [
                'province_id' => $foreignProvince->id,
            ]))
            ->assertUnprocessable();

        foreach (['province_id', 'city_id'] as $field) {
            $response->assertJsonPath("error.details.fields.{$field}.0", fn (string $message) => $message !== '');
        }
    }

    public function test_deleting_the_address_keeps_the_row_recoverable_and_history_safe(): void
    {
        $this->actingAsToken($this->user);

        $this->putJson('/api/v1/address', $this->validPayload())->assertOk();
        $this->deleteJson('/api/v1/address')->assertOk();

        $this->getJson('/api/v1/address')->assertOk()->assertJsonPath('data', null);

        // The UNIQUE(user_id) index means the same row is restored on re-save.
        $this->putJson('/api/v1/address', $this->validPayload())->assertOk();

        $this->assertDatabaseCount(CustomerAddress::class, 1);
        $this->assertSame(1, CustomerAddress::query()->count());
    }

    public function test_guests_cannot_access_addresses(): void
    {
        $this->getJson('/api/v1/address')->assertUnauthorized();
        $this->putJson('/api/v1/address', $this->validPayload())->assertUnauthorized();
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        $meycauayan = City::query()->where('name', 'Meycauayan City')->firstOrFail();

        return [
            'region_id' => $meycauayan->region_id,
            'province_id' => $meycauayan->province_id,
            'city_id' => $meycauayan->id,
            'barangay_id' => Barangay::query()->where('city_id', $meycauayan->id)->value('id'),
            'address_line1' => '123 Rizal Avenue',
            'address_line2' => 'Unit 4B',
            'recipient_name' => 'Juan Dela Cruz',
            'recipient_phone' => '+639171234567',
            'notes' => null,
        ];
    }
}
