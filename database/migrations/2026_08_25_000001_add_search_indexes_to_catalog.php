<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the catalog search supporting structures. FULLTEXT is MySQL-only;
     * the SQLite test database skips it gracefully.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->fullText(['name', 'short_description'], 'products_name_short_description_fulltext');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropFullText('products_name_short_description_fulltext');
        });
    }
};
