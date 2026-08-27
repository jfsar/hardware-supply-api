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
        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->unsignedSmallInteger('shipping_estimated_min_days')->nullable()->after('shipping_minor');
            $table->unsignedSmallInteger('shipping_estimated_max_days')->nullable()->after('shipping_estimated_min_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->dropColumn(['shipping_estimated_min_days', 'shipping_estimated_max_days']);
        });
    }
};
