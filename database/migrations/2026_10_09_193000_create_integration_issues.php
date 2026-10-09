<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_source_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('integration_product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('fingerprint')->unique();
            $table->string('type', 64)->index();
            $table->string('severity', 16)->default('warning')->index();
            $table->string('status', 16)->default('open')->index();
            $table->string('title');
            $table->text('message')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('first_detected_at')->index();
            $table->timestamp('last_detected_at')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_issues');
    }
};
