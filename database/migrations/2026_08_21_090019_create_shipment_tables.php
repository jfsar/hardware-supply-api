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
        Schema::create('delivery_drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->string('phone', 30);
            $table->string('license_reference', 100)->nullable();
            $table->string('status', 30)->index();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
            $table->dateTime('deleted_at', 6)->nullable();
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipping_method_id')->constrained()->restrictOnDelete();
            $table->foreignId('pickup_location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('delivery_driver_id')->nullable()->constrained()->nullOnDelete();
            $table->string('shipment_number', 50)->unique();
            $table->string('status', 30)->index();
            $table->string('tracking_number', 100)->nullable();
            $table->string('carrier_name', 150)->nullable();
            $table->dateTime('estimated_delivery_at', 6)->nullable();
            $table->dateTime('shipped_at', 6)->nullable();
            $table->dateTime('delivered_at', 6)->nullable();
            $table->dateTime('picked_up_at', 6)->nullable();
            $table->json('delivery_address_snapshot')->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->index(['order_id', 'status']);
        });

        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->unique(['shipment_id', 'order_item_id']);
        });

        Schema::create('shipment_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('status', 40);
            $table->string('location_text')->nullable();
            $table->dateTime('event_at', 6);
            $table->string('description', 500)->nullable();
            $table->json('raw_payload')->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->index(['shipment_id', 'event_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_tracking_events');
        Schema::dropIfExists('shipment_items');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('delivery_drivers');
    }
};
