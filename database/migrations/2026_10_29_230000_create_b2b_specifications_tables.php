<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('b2b_specifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('b2b_client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 40)->unique();
            $table->string('title');
            $table->text('comment')->nullable();
            $table->string('status', 40)->default('submitted')->index();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('commercial_document_id')->nullable()->constrained()->nullOnDelete();
            $table->text('client_message')->nullable();
            $table->text('manager_note')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['b2b_client_id', 'created_at']);
        });

        Schema::create('b2b_specification_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('b2b_specification_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 40)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('b2b_specification_attachments');
        Schema::dropIfExists('b2b_specifications');
    }
};
