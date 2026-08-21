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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->string('invoice_number', 50)->unique();
            $table->string('status', 30)->index();
            $table->char('currency_code', 3);
            $table->bigInteger('subtotal_minor');
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('shipping_minor')->default(0);
            $table->bigInteger('total_minor');
            $table->dateTime('issued_at', 6);
            $table->string('pdf_path')->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 500);
            $table->decimal('quantity', 18, 3);
            $table->bigInteger('unit_price_minor');
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('line_total_minor');
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });

        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('credit_note_number', 50)->unique();
            $table->string('status', 30)->index();
            $table->string('reason', 500);
            $table->bigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->dateTime('issued_at', 6);
            $table->string('pdf_path')->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });

        Schema::create('credit_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 500);
            $table->decimal('quantity', 18, 3);
            $table->bigInteger('amount_minor');
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_note_items');
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
