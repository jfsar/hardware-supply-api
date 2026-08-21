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
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->string('status', 30)->index();
            $table->string('reason_code', 50);
            $table->string('customer_reason', 500)->nullable();
            $table->string('resolution_type', 30);
            $table->foreignId('replacement_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->dateTime('requested_at', 6);
            $table->dateTime('approved_at', 6)->nullable();
            $table->dateTime('received_at', 6)->nullable();
            $table->dateTime('resolved_at', 6)->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });

        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->string('condition', 30)->nullable();
            $table->string('reason_code', 50)->nullable();
            $table->bigInteger('refund_amount_minor')->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('return_requests');
    }
};
