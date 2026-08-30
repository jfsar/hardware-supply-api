<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Promote a unique, public ULID into webhook_endpoints so admin
     * routes bind endpoints the same way every other entity resolves
     * (house rule: entity tables carry an immutable public ulid). The
     * column arrives nullable so existing rows are backfilled row-by-row
     * without a lock-hogging full-table update on large installs.
     */
    public function up(): void
    {
        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->ulid('ulid')->unique()->nullable()->after('id');
        });

        DB::table('webhook_endpoints')
            ->select(['id'])
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('webhook_endpoints')
                        ->where('id', $row->id)
                        ->whereNull('ulid')
                        ->update(['ulid' => (string) Str::ulid()]);
                }
            });

        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->ulid('ulid')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->dropUnique(['ulid']);
            $table->dropColumn('ulid');
        });
    }
};
