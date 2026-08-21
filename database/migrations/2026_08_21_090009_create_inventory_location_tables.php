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
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->string('location_type', 30)->index();
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->foreignId('region_id')->constrained()->restrictOnDelete();
            $table->foreignId('province_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('city_id')->constrained()->restrictOnDelete();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->foreignId('postal_code_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });

        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_on_hand', 18, 3)->default(0);
            $table->decimal('quantity_reserved', 18, 3)->default(0);
            $table->decimal('reorder_level', 18, 3)->default(0);
            $table->dateTime('updated_at', 6)->nullable();

            $table->unique(['location_id', 'product_variant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventories');
        Schema::dropIfExists('locations');
    }
};
