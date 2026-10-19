<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_sync_cursors', function (Blueprint $table): void {
            $table->id();
            $table->string('channel', 40);
            $table->string('stream', 40);
            $table->timestamp('cursor_at')->nullable();
            $table->timestamp('last_started_at')->nullable();
            $table->timestamp('last_succeeded_at')->nullable();
            $table->timestamp('last_failed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['channel', 'stream']);
        });

        Schema::create('deal_by_order_inboxes', function (Blueprint $table): void {
            $table->id();
            $table->string('remote_order_id')->unique();
            $table->string('remote_status')->nullable()->index();
            $table->string('remote_status_name')->nullable();
            $table->timestamp('remote_created_at')->nullable();
            $table->timestamp('remote_modified_at')->nullable()->index();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at')->index();
            $table->char('payload_hash', 64);
            $table->longText('payload');
            $table->string('processing_status', 24)->default('pending')->index();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('processing_attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_by_order_inboxes');
        Schema::dropIfExists('marketplace_sync_cursors');
    }
};
