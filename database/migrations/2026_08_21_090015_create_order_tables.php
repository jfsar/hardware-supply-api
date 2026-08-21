<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('order_number', 40)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('checkout_session_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->char('currency_code', 3);
            $table->string('order_status', 40)->index();
            $table->string('payment_status', 40)->index();
            $table->string('fulfillment_status', 40)->index();
            $table->bigInteger('subtotal_minor')->default(0);
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('shipping_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('adjustment_minor')->default(0);
            $table->bigInteger('total_minor');
            $table->string('customer_email')->index();
            $table->string('customer_phone', 30)->nullable();
            $table->dateTime('placed_at', 6)->nullable()->index();
            $table->dateTime('paid_at', 6)->nullable();
            $table->dateTime('cancelled_at', 6)->nullable();
            $table->dateTime('completed_at', 6)->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku_snapshot', 100);
            $table->string('product_name_snapshot');
            $table->string('variant_name_snapshot')->nullable();
            $table->bigInteger('unit_price_minor');
            $table->decimal('quantity', 18, 3);
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('line_total_minor');
            $table->decimal('quantity_cancelled', 18, 3)->default(0);
            $table->decimal('quantity_fulfilled', 18, 3)->default(0);
            $table->decimal('quantity_returned', 18, 3)->default(0);
            $table->decimal('quantity_refunded', 18, 3)->default(0);
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });

        Schema::create('order_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('address_type', 20);
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('province_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('barangay_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('postal_code_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('recipient_name', 200);
            $table->string('recipient_phone', 30);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->unique(['order_id', 'address_type']);
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('created_at', 6)->nullable();

            $table->index(['order_id', 'created_at']);
        });

        Schema::create('order_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note');
            $table->boolean('is_customer_visible')->default(false);
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });

        Schema::create('order_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('label', 150);
            $table->bigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->string('reason', 500)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('discount_amount_minor');
            $table->char('currency_code', 3);
            $table->dateTime('redeemed_at', 6);
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->index(['coupon_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('order_adjustments');
        Schema::dropIfExists('order_notes');
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_addresses');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
