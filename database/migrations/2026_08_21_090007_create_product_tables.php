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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->index();
            $table->string('slug')->unique();
            $table->string('sku_prefix', 50)->nullable();
            $table->string('short_description', 1000)->nullable();
            $table->longText('description')->nullable();
            $table->string('warranty_type', 50)->nullable();
            $table->smallInteger('warranty_duration_months')->unsigned()->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->dateTime('published_at', 6)->nullable()->index();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
            $table->dateTime('deleted_at', 6)->nullable();

            $table->index(['status', 'category_id']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tax_class_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku', 100)->unique();
            $table->string('name')->nullable();
            $table->bigInteger('cost_amount_minor')->nullable();
            $table->char('cost_currency_code', 3)->nullable();
            $table->integer('weight_grams')->unsigned()->nullable();
            $table->integer('length_mm')->unsigned()->nullable();
            $table->integer('width_mm')->unsigned()->nullable();
            $table->integer('height_mm')->unsigned()->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('status', 30)->default('active')->index();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
            $table->dateTime('deleted_at', 6)->nullable();

            $table->index(['product_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
    }
};
