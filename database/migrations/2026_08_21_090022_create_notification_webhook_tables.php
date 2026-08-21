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
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('order_updates_enabled')->default(true);
            $table->boolean('payment_updates_enabled')->default(true);
            $table->boolean('promotions_enabled')->default(true);
            $table->boolean('back_in_stock_enabled')->default(true);
            $table->boolean('price_drop_enabled')->default(true);
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 100);
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->dateTime('read_at', 6)->nullable();
            $table->dateTime('created_at', 6)->nullable();

            $table->index(['user_id', 'read_at']);
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 30);
            $table->string('recipient');
            $table->string('status', 30);
            $table->string('provider_message_id')->nullable();
            $table->integer('attempt_count')->unsigned()->default(0);
            $table->string('last_error', 500)->nullable();
            $table->dateTime('sent_at', 6)->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });

        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('url', 500);
            $table->text('secret_encrypted');
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
            $table->dateTime('deleted_at', 6)->nullable();
        });

        Schema::create('webhook_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 100);
            $table->string('api_version', 20);
            $table->dateTime('created_at', 6)->nullable();

            $table->unique(['webhook_endpoint_id', 'event_type', 'api_version'], 'webhook_subscriptions_endpoint_event_api_version_unique');
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->char('event_id', 26);
            $table->string('event_type', 100);
            $table->string('api_version', 20);
            $table->json('payload');
            $table->string('signature');
            $table->string('status', 30);
            $table->integer('attempt_count')->unsigned()->default(0);
            $table->dateTime('next_attempt_at', 6)->nullable();
            $table->dateTime('delivered_at', 6)->nullable();
            $table->smallInteger('last_http_status')->unsigned()->nullable();
            $table->string('last_error', 500)->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->unique(['webhook_endpoint_id', 'event_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_subscriptions');
        Schema::dropIfExists('webhook_endpoints');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('notification_preferences');
    }
};
