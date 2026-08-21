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
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->string('method_type', 30);
            $table->string('provider', 100)->nullable();
            $table->boolean('is_pickup')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0);
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });

        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 50)->unique();
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });

        Schema::create('shipping_zone_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_zone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('region_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('province_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('barangay_id')->nullable()->constrained()->cascadeOnDelete();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->index(['shipping_zone_id', 'country_id', 'region_id', 'province_id', 'city_id', 'barangay_id'], 'shipping_zone_rules_geo_scope_index');
        });

        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_method_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipping_zone_id')->constrained()->cascadeOnDelete();
            $table->integer('min_weight_grams')->unsigned()->nullable();
            $table->integer('max_weight_grams')->unsigned()->nullable();
            $table->integer('min_length_mm')->unsigned()->nullable();
            $table->integer('max_length_mm')->unsigned()->nullable();
            $table->bigInteger('min_order_total_minor')->nullable();
            $table->bigInteger('max_order_total_minor')->nullable();
            $table->bigInteger('rate_minor');
            $table->char('currency_code', 3);
            $table->bigInteger('free_shipping_threshold_minor')->nullable();
            $table->smallInteger('estimated_min_days')->unsigned()->nullable();
            $table->smallInteger('estimated_max_days')->unsigned()->nullable();
            $table->dateTime('starts_at', 6);
            $table->dateTime('ends_at', 6)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });

        Schema::create('pickup_locations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->foreignId('region_id')->constrained()->restrictOnDelete();
            $table->foreignId('province_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('city_id')->constrained()->restrictOnDelete();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->foreignId('postal_code_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->json('opening_hours')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pickup_locations');
        Schema::dropIfExists('shipping_rates');
        Schema::dropIfExists('shipping_zone_rules');
        Schema::dropIfExists('shipping_zones');
        Schema::dropIfExists('shipping_methods');
    }
};
