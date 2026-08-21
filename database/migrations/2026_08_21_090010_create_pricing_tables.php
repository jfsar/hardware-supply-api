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
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 50)->unique();
            $table->char('currency_code', 3);
            $table->string('customer_scope', 30);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });

        Schema::create('price_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('price_amount_minor');
            $table->char('currency_code', 3);
            $table->dateTime('effective_from', 6);
            $table->dateTime('effective_to', 6)->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->unique(['price_list_id', 'product_variant_id', 'effective_from'], 'price_list_items_price_variant_effective_unique');
            $table->index(['product_variant_id', 'effective_from', 'effective_to'], 'price_list_items_variant_effective_range_index');
        });

        Schema::create('customer_price_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('price_list_id')->constrained()->cascadeOnDelete();
            $table->dateTime('effective_from', 6);
            $table->dateTime('effective_to', 6)->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->unique(['user_id', 'price_list_id', 'effective_from']);
        });

        Schema::create('quantity_price_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('min_quantity', 18, 3);
            $table->decimal('max_quantity', 18, 3)->nullable();
            $table->bigInteger('unit_price_amount_minor');
            $table->char('currency_code', 3);
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->unique(['price_list_item_id', 'min_quantity']);
        });

        Schema::create('price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('price_list_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('price_amount_minor');
            $table->char('currency_code', 3);
            $table->dateTime('effective_from', 6);
            $table->dateTime('effective_to', 6)->nullable();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500)->nullable();
            $table->dateTime('created_at', 6)->nullable();

            $table->index(['product_variant_id', 'effective_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_histories');
        Schema::dropIfExists('quantity_price_tiers');
        Schema::dropIfExists('customer_price_lists');
        Schema::dropIfExists('price_list_items');
        Schema::dropIfExists('price_lists');
    }
};
