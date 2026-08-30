<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'order_id' => OrderFactory::new(),
            'invoice_number' => 'INV-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
            'status' => InvoiceStatus::Issued,
            'currency_code' => config('commerce.currency', 'PHP'),
            'subtotal_minor' => 25000,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'shipping_minor' => 0,
            'total_minor' => 25000,
            'issued_at' => now(),
            'pdf_path' => null,
        ];
    }

    /**
     * Bind the invoice to a specific order.
     */
    public function forOrder(Order $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order_id' => $order->id,
            'currency_code' => $order->currency_code,
            'total_minor' => $order->total_minor,
        ]);
    }
}
