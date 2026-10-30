<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('b2b_client_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('b2b_client_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 30);
            $table->string('token_hash', 64)->unique();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['b2b_client_id', 'email']);
            $table->index(['b2b_client_id', 'expires_at']);
        });

        Schema::create('b2b_client_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('b2b_client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 60);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['b2b_client_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('b2b_client_audit_logs');
        Schema::dropIfExists('b2b_client_invitations');
    }
};
