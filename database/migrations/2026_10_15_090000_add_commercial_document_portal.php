<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('commercial_document_items', 'portal_snapshot')) {
            Schema::table('commercial_document_items', function (Blueprint $table): void {
                $table->json('portal_snapshot')->nullable()->after('sort_order');
            });
        }

        if (! Schema::hasTable('commercial_document_portal_links')) {
            Schema::create('commercial_document_portal_links', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('commercial_document_id')->constrained()->cascadeOnDelete();
                $table->uuid('public_id')->unique();
                $table->string('token_hash', 64)->unique();
                $table->text('token_encrypted');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('expires_at')->index();
                $table->timestamp('first_activated_at')->nullable();
                $table->timestamp('last_opened_at')->nullable();
                $table->timestamp('revoked_at')->nullable()->index();
                $table->unsignedInteger('open_count')->default(0);
                $table->timestamps();
                $table->index(['commercial_document_id', 'revoked_at']);
            });
        }

        if (! Schema::hasTable('commercial_document_portal_sessions')) {
            Schema::create('commercial_document_portal_sessions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('portal_link_id')->constrained('commercial_document_portal_links')->cascadeOnDelete();
                $table->string('token_hash', 64)->unique();
                $table->string('device_label')->nullable();
                $table->string('ip_hash', 64)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->timestamp('authorized_at');
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('expires_at')->index();
                $table->timestamp('revoked_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('commercial_document_portal_challenges')) {
            Schema::create('commercial_document_portal_challenges', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('portal_link_id')->constrained('commercial_document_portal_links')->cascadeOnDelete();
                $table->uuid('public_id')->unique();
                $table->string('code_hash', 64);
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('expires_at')->index();
                $table->timestamp('consumed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('commercial_document_portal_events')) {
            Schema::create('commercial_document_portal_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('commercial_document_id')->constrained()->cascadeOnDelete();
                $table->foreignId('portal_link_id')->nullable()->constrained('commercial_document_portal_links')->nullOnDelete();
                $table->foreignId('portal_session_id')->nullable()->constrained('commercial_document_portal_sessions')->nullOnDelete();
                $table->string('type', 64)->index();
                $table->string('ip_hash', 64)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('occurred_at')->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_document_portal_events');
        Schema::dropIfExists('commercial_document_portal_challenges');
        Schema::dropIfExists('commercial_document_portal_sessions');
        Schema::dropIfExists('commercial_document_portal_links');

        if (Schema::hasColumn('commercial_document_items', 'portal_snapshot')) {
            Schema::table('commercial_document_items', function (Blueprint $table): void {
                $table->dropColumn('portal_snapshot');
            });
        }
    }
};
