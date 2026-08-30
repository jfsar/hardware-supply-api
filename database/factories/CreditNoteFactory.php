<?php

namespace Database\Factories;

use App\Enums\CreditNoteStatus;
use App\Models\CreditNote;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CreditNote>
 */
class CreditNoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'invoice_id' => InvoiceFactory::new(),
            'order_id' => OrderFactory::new(),
            'credit_note_number' => 'CN-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
            'status' => CreditNoteStatus::Issued,
            'reason' => $this->faker->sentence(),
            'amount_minor' => 25000,
            'currency_code' => config('commerce.currency', 'PHP'),
            'issued_at' => now(),
            'pdf_path' => null,
        ];
    }
}
