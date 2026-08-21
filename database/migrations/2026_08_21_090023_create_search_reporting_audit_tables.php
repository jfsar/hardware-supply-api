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
        Schema::create('search_synonyms', function (Blueprint $table) {
            $table->id();
            $table->string('term')->unique();
            $table->json('synonyms');
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });

        Schema::create('search_queries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->char('session_hash', 64)->nullable();
            $table->string('query', 500);
            $table->integer('result_count')->unsigned();
            $table->json('filters')->nullable();
            $table->dateTime('occurred_at', 6);

            $table->index(['user_id', 'occurred_at']);
        });

        Schema::create('report_exports', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('report_type', 100);
            $table->json('filters');
            $table->string('status', 30)->index();
            $table->string('storage_disk', 50)->nullable();
            $table->string('storage_path')->nullable();
            $table->dateTime('started_at', 6)->nullable();
            $table->dateTime('completed_at', 6)->nullable();
            $table->dateTime('expires_at', 6)->nullable();
            $table->text('error_message')->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('key');
            $table->string('endpoint');
            $table->char('request_hash', 64);
            $table->smallInteger('response_status')->unsigned()->nullable();
            $table->json('response_body')->nullable();
            $table->dateTime('expires_at', 6)->index();
            $table->dateTime('created_at', 6)->nullable();

            $table->unique(['user_id', 'endpoint', 'key']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->string('resource_type', 100);
            $table->bigInteger('resource_id')->unsigned()->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45);
            $table->string('user_agent', 500)->nullable();
            $table->char('request_id', 26)->nullable();
            $table->dateTime('created_at', 6)->nullable();

            $table->index(['resource_type', 'resource_id', 'created_at']);
            $table->index(['actor_user_id', 'created_at']);
        });

        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 100);
            $table->string('severity', 20);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->char('request_id', 26)->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('occurred_at', 6);

            $table->index(['event_type', 'occurred_at']);
            $table->index(['severity', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_events');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('report_exports');
        Schema::dropIfExists('search_queries');
        Schema::dropIfExists('search_synonyms');
    }
};
