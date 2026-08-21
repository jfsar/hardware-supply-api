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
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name', 150);
            $table->string('code', 50)->nullable()->unique();
            $table->string('promotion_type', 30);
            $table->string('discount_type', 30);
            $table->decimal('discount_value', 18, 3);
            $table->bigInteger('max_discount_amount_minor')->nullable();
            $table->dateTime('starts_at', 6);
            $table->dateTime('ends_at', 6)->nullable();
            $table->integer('usage_limit')->unsigned()->nullable();
            $table->integer('per_customer_limit')->unsigned()->nullable();
            $table->boolean('is_stackable')->default(false);
            $table->integer('priority')->default(0);
            $table->string('status', 30)->default('active')->index();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
            $table->dateTime('deleted_at', 6)->nullable();

            $table->index(['status', 'starts_at', 'ends_at']);
        });

        Schema::create('promotion_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->index('product_id');
            $table->index('product_variant_id');
        });

        Schema::create('promotion_categories', function (Blueprint $table) {
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->primary(['promotion_id', 'category_id']);
        });

        Schema::create('promotion_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->string('rule_type', 50);
            $table->json('configuration');
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 50)->unique();
            $table->integer('usage_limit')->unsigned()->nullable();
            $table->integer('per_customer_limit')->unsigned()->nullable();
            $table->dateTime('starts_at', 6);
            $table->dateTime('ends_at', 6);
            $table->boolean('is_active')->default(true)->index();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
            $table->dateTime('deleted_at', 6)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('promotion_rules');
        Schema::dropIfExists('promotion_categories');
        Schema::dropIfExists('promotion_products');
        Schema::dropIfExists('promotions');
    }
};
