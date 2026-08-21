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
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });

        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wishlist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->dateTime('created_at', 6)->nullable();

            $table->unique(['wishlist_id', 'product_id']);
        });

        Schema::create('recently_viewed_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->char('session_hash', 64)->nullable();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->dateTime('viewed_at', 6);
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->index(['user_id', 'viewed_at']);
            $table->index(['session_hash', 'viewed_at']);
        });

        Schema::create('product_comparisons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->char('session_hash', 64)->nullable()->unique();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });

        Schema::create('product_comparison_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comparison_id')->constrained('product_comparisons')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->unique(['comparison_id', 'product_id']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->tinyInteger('rating')->unsigned();
            $table->string('title')->nullable();
            $table->text('body');
            $table->string('status', 30)->index();
            $table->boolean('verified_purchase')->default(false);
            $table->dateTime('published_at', 6)->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
            $table->dateTime('deleted_at', 6)->nullable();

            $table->unique(['user_id', 'product_id']);
            $table->index(['product_id', 'status', 'created_at']);
        });

        Schema::create('review_helpful_votes', function (Blueprint $table) {
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('created_at', 6)->nullable();

            $table->primary(['review_id', 'user_id']);
        });

        Schema::create('review_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reason_code', 50);
            $table->string('details', 500)->nullable();
            $table->string('status', 30)->index();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at', 6)->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->unique(['review_id', 'user_id']);
        });

        Schema::create('back_in_stock_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30);
            $table->dateTime('notified_at', 6)->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->unique(['email', 'product_variant_id', 'status'], 'back_in_stock_subscriptions_email_variant_status_unique');
        });

        Schema::create('price_drop_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('target_price_minor')->nullable();
            $table->char('currency_code', 3);
            $table->string('status', 30);
            $table->dateTime('notified_at', 6)->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });

        Schema::create('recommendation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->char('session_hash', 64)->nullable();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 50);
            $table->json('metadata')->nullable();
            $table->dateTime('occurred_at', 6);

            $table->index(['event_type', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recommendation_events');
        Schema::dropIfExists('price_drop_subscriptions');
        Schema::dropIfExists('back_in_stock_subscriptions');
        Schema::dropIfExists('review_reports');
        Schema::dropIfExists('review_helpful_votes');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('product_comparison_items');
        Schema::dropIfExists('product_comparisons');
        Schema::dropIfExists('recently_viewed_products');
        Schema::dropIfExists('wishlist_items');
        Schema::dropIfExists('wishlists');
    }
};
