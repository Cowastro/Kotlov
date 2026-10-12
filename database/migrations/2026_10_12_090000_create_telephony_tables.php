<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telephony_connections', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('a1')->unique();
            $table->string('company_id')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->text('primary_api_key')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->timestamp('refresh_token_expires_at')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->timestamp('webhook_last_received_at')->nullable();
            $table->timestamp('api_last_success_at')->nullable();
            $table->timestamp('history_synced_until')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('telephony_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->default('a1');
            $table->string('external_id')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('group_name')->nullable();
            $table->string('full_number')->nullable();
            $table->string('short_number')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_id']);
            $table->index(['provider', 'short_number']);
            $table->index(['provider', 'full_number']);
        });

        Schema::create('telephony_calls', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('a1');
            $table->string('external_uuid');
            $table->string('company_id')->nullable();
            $table->string('direction')->default('inbound');
            $table->string('dial_type')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('employee_phone')->nullable();
            $table->string('line_number')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('matched_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('ringing');
            $table->string('handling_status')->default('unprocessed');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->unsignedInteger('conversation_duration')->nullable();
            $table->string('record_name')->nullable();
            $table->text('recording_url')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('callback_status')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('manager_note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_uuid']);
            $table->index(['handling_status', 'started_at']);
            $table->index(['status', 'started_at']);
            $table->index('customer_phone');
        });

        Schema::create('telephony_call_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telephony_call_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('a1');
            $table->string('external_uuid');
            $table->string('notification_type');
            $table->string('state')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->char('payload_hash', 64);
            $table->json('payload');
            $table->timestamp('received_at');
            $table->timestamps();

            $table->unique(['provider', 'external_uuid', 'payload_hash'], 'telephony_event_dedup');
            $table->index(['external_uuid', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telephony_call_events');
        Schema::dropIfExists('telephony_calls');
        Schema::dropIfExists('telephony_employees');
        Schema::dropIfExists('telephony_connections');
    }
};
