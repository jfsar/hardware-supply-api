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
        Schema::create('inventory_reservations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cart_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->string('status', 30);
            $table->dateTime('expires_at', 6);
            $table->dateTime('released_at', 6)->nullable();
            $table->dateTime('consumed_at', 6)->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->index(['product_variant_id', 'location_id', 'status', 'expires_at'], 'inventory_reservations_variant_location_status_index');
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('movement_type', 40);
            $table->decimal('quantity_delta', 18, 3);
            $table->decimal('quantity_before', 18, 3);
            $table->decimal('quantity_after', 18, 3);
            $table->string('reference_type', 100)->nullable();
            $table->bigInteger('reference_id')->unsigned()->nullable();
            $table->string('reason', 500)->nullable();
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('created_at', 6)->nullable();

            $table->index(['product_variant_id', 'created_at']);
            $table->index(['location_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_reservations');
    }
};
