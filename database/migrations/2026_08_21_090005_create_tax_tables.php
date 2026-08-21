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
        Schema::create('tax_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 50)->unique();
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });

        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_class_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->decimal('rate', 8, 5);
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('starts_at', 6);
            $table->dateTime('ends_at', 6)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->index(['tax_class_id', 'country_id', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('tax_classes');
    }
};
