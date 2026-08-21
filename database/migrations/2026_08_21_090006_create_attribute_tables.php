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
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug')->unique();
            $table->string('data_type', 30);
            $table->string('unit', 30)->nullable();
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_variant_defining')->default(false);
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });

        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('value_text');
            $table->bigInteger('value_integer')->nullable();
            $table->decimal('value_decimal', 18, 6)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->integer('sort_order')->default(0);
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->unique(['attribute_id', 'value_text']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attributes');
    }
};
