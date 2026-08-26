<?php

namespace Tests\Unit;

use App\Models\PriceHistory;
use App\Models\PriceList;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Pricing\RecordPriceChange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecordPriceChangeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function appends_an_audit_row_without_touching_existing_history(): void
    {
        $variant = ProductVariant::factory()->create();
        $list = PriceList::factory()->default()->create();
        $actor = User::factory()->create();

        $change = new RecordPriceChange;

        $first = $change($variant, $list, 25000, $actor, 'initial import');
        $second = $change($variant, $list, 19900, $actor, 'spring sale');

        $this->assertSame(2, PriceHistory::query()->count());
        $this->assertSame(25000, $first->price_amount_minor);
        $this->assertSame(19900, $second->price_amount_minor);
        $this->assertSame($actor->id, $second->changed_by_user_id);
        $this->assertSame('spring sale', $second->reason);
        $this->assertSame($list->currency_code, $second->currency_code);
    }

    #[Test]
    public function accepts_a_custom_effective_from_window(): void
    {
        $variant = ProductVariant::factory()->create();
        $list = PriceList::factory()->default()->create();
        $at = Carbon::parse('2030-01-15 08:00:00');

        $row = (new RecordPriceChange)($variant, $list, 10000, null, null, $at);

        $this->assertTrue($row->effective_from->equalTo($at));
        $this->assertNull($row->effective_to);
    }
}
