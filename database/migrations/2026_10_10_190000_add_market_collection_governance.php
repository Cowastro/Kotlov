<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_price_sources', function (Blueprint $table): void {
            $table->unsignedSmallInteger('collection_interval_minutes')->default(360)->after('freshness_hours');
            $table->unsignedSmallInteger('max_requests_per_run')->default(100)->after('collection_interval_minutes');
            $table->unsignedInteger('max_requests_per_day')->default(500)->after('max_requests_per_run');
            $table->json('allowed_path_prefixes')->nullable()->after('max_requests_per_day');
            $table->boolean('respect_robots_txt')->default(true)->after('allowed_path_prefixes');
            $table->boolean('collection_authorized')->default(false)->after('respect_robots_txt');
            $table->timestamp('last_collection_at')->nullable()->after('collection_authorized');
            $table->timestamp('next_collection_at')->nullable()->after('last_collection_at')->index();
        });

        Schema::create('market_price_collection_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('market_price_source_id')->constrained()->cascadeOnDelete();
            $table->uuid('session_uuid')->unique();
            $table->string('trigger', 32)->default('manual');
            $table->string('status', 32)->default('running')->index();
            $table->unsignedInteger('requested_count')->default(0);
            $table->unsignedInteger('recorded_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('warning_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->json('events')->nullable();
            $table->text('summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->index();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['market_price_source_id', 'started_at'], 'market_collection_source_started_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_price_collection_runs');

        Schema::table('market_price_sources', function (Blueprint $table): void {
            $table->dropIndex(['next_collection_at']);
            $table->dropColumn([
                'collection_interval_minutes',
                'max_requests_per_run',
                'max_requests_per_day',
                'allowed_path_prefixes',
                'respect_robots_txt',
                'collection_authorized',
                'last_collection_at',
                'next_collection_at',
            ]);
        });
    }
};
