<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_portal_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('token_hash', 64)->unique();
            $table->text('token_encrypted');
            $table->string('access_email');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->index();
            $table->timestamp('first_activated_at')->nullable();
            $table->timestamp('last_opened_at')->nullable();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->unsignedInteger('open_count')->default(0);
            $table->timestamps();
            $table->index(['order_id', 'revoked_at'], 'order_portal_order_revoked_idx');
        });

        Schema::create('order_portal_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('portal_link_id')->constrained('order_portal_links')->cascadeOnDelete();
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

        Schema::create('order_portal_challenges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('portal_link_id')->constrained('order_portal_links')->cascadeOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_portal_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('portal_link_id')->nullable()->constrained('order_portal_links')->nullOnDelete();
            $table->foreignId('portal_session_id')->nullable()->constrained('order_portal_sessions')->nullOnDelete();
            $table->string('type', 64)->index();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_portal_events');
        Schema::dropIfExists('order_portal_challenges');
        Schema::dropIfExists('order_portal_sessions');
        Schema::dropIfExists('order_portal_links');
    }
};
