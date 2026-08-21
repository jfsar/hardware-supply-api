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
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->foreignId('region_id')->constrained()->restrictOnDelete();
            $table->foreignId('province_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('city_id')->constrained()->restrictOnDelete();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->foreignId('postal_code_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('recipient_name', 200);
            $table->string('recipient_phone', 30);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('notes', 1000)->nullable();
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
        Schema::dropIfExists('customer_addresses');
    }
};
