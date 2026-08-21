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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('provider', 50);
            $table->string('payment_method', 30);
            $table->char('currency_code', 3);
            $table->bigInteger('amount_minor');
            $table->string('status', 30)->index();
            $table->string('provider_payment_id')->nullable();
            $table->dateTime('last_attempt_at', 6)->nullable();
            $table->dateTime('paid_at', 6)->nullable();
            $table->dateTime('failed_at', 6)->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->index(['provider', 'provider_payment_id']);
            $table->index(['order_id', 'status']);
        });

        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->integer('attempt_number')->unsigned();
            $table->string('provider_reference')->nullable();
            $table->string('request_id')->nullable();
            $table->string('status', 30);
            $table->bigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->string('failure_code', 100)->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->dateTime('started_at', 6);
            $table->dateTime('completed_at', 6)->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->unique(['payment_id', 'attempt_number']);
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_attempt_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 50);
            $table->string('transaction_type', 30);
            $table->string('provider_transaction_id')->nullable();
            $table->bigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->string('status', 30);
            $table->dateTime('processed_at', 6);
            $table->json('metadata')->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->unique(['provider', 'provider_transaction_id']);
        });

        Schema::create('payment_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50);
            $table->string('provider_event_id')->nullable();
            $table->string('event_type', 100);
            $table->boolean('signature_valid');
            $table->json('payload');
            $table->json('headers')->nullable();
            $table->string('processing_status', 30);
            $table->dateTime('received_at', 6);
            $table->dateTime('processed_at', 6)->nullable();
            $table->text('processing_error')->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->unique(['provider', 'provider_event_id']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('provider_refund_id')->nullable();
            $table->bigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->string('status', 30)->index();
            $table->string('reason', 500)->nullable();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('requested_at', 6);
            $table->dateTime('processed_at', 6)->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });

        Schema::create('refund_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->bigInteger('amount_minor');
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->unique(['refund_id', 'order_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refund_items');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payment_webhooks');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('payments');
    }
};
