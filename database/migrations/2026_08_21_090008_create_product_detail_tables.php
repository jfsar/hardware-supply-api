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
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('storage_disk', 50);
            $table->string('path');
            $table->string('mime_type', 100);
            $table->integer('width')->unsigned()->nullable();
            $table->integer('height')->unsigned()->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->index(['product_id', 'sort_order']);
        });

        Schema::create('product_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('storage_disk', 50);
            $table->string('path');
            $table->string('mime_type', 100);
            $table->bigInteger('file_size_bytes')->unsigned();
            $table->integer('sort_order')->default(0);
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
            $table->dateTime('deleted_at', 6)->nullable();
        });

        Schema::create('product_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_product_id')->constrained('products')->cascadeOnDelete();
            $table->string('relation_type', 30);
            $table->integer('sort_order')->default(0);
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->unique(['product_id', 'related_product_id', 'relation_type'], 'product_relations_product_related_type_unique');
        });

        Schema::create('product_bundles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('bundle_type', 30);
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });

        Schema::create('product_bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_id')->constrained('product_bundles')->cascadeOnDelete();
            $table->foreignId('component_product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->unique(['bundle_id', 'component_product_variant_id'], 'product_bundle_items_bundle_variant_unique');
        });

        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->nullable()->constrained()->nullOnDelete();
            $table->string('value_text')->nullable();
            $table->bigInteger('value_integer')->nullable();
            $table->decimal('value_decimal', 18, 6)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->unique(['product_id', 'attribute_id']);
        });

        Schema::create('variant_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->nullable()->constrained()->nullOnDelete();
            $table->string('value_text')->nullable();
            $table->bigInteger('value_integer')->nullable();
            $table->decimal('value_decimal', 18, 6)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->unique(['product_variant_id', 'attribute_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variant_attribute_values');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('product_bundle_items');
        Schema::dropIfExists('product_bundles');
        Schema::dropIfExists('product_relations');
        Schema::dropIfExists('product_documents');
        Schema::dropIfExists('product_images');
    }
};
