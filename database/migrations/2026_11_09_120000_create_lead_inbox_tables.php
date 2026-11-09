<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_channels', function (Blueprint $table): void {
            $table->string('provider')->primary();
            $table->boolean('enabled')->default(false);
            $table->string('account_id')->nullable();
            $table->string('api_version', 12)->nullable();
            $table->text('access_token')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->text('verify_token')->nullable();
            $table->string('public_url', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('lead_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction', 12);
            $table->string('state', 12);
            $table->string('event_key', 64)->nullable();
            $table->uuid('send_key')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->text('body');
            $table->timestamps();
            $table->unique(['contact_request_id', 'event_key']);
            $table->unique(['contact_request_id', 'user_id', 'send_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_messages');
        Schema::dropIfExists('lead_channels');
    }
};
