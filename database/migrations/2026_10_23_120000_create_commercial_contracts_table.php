<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_contracts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('commercial_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number')->unique();
            $table->string('status', 24)->default('generated')->index();
            $table->string('template_mode', 24)->default('standard');
            $table->unsignedInteger('template_version')->default(1);
            $table->date('contract_date');
            $table->date('valid_until')->nullable();
            $table->json('seller_snapshot');
            $table->json('buyer_snapshot');
            $table->json('terms_snapshot');
            $table->string('source_template_path')->nullable();
            $table->string('generated_docx_path')->nullable();
            $table->string('generated_pdf_path')->nullable();
            $table->string('content_checksum', 64)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['commercial_document_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_contracts');
    }
};
