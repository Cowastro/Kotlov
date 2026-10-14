<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_documents', function (Blueprint $table): void {
            $table->foreignId('revision_of_id')->nullable()->after('source_document_id')
                ->constrained('commercial_documents')->nullOnDelete();
            $table->unsignedSmallInteger('revision_number')->default(1)->after('revision_of_id');
            $table->string('outcome_reason', 64)->nullable()->after('status');
            $table->text('outcome_note')->nullable()->after('outcome_reason');
            $table->timestamp('closed_at')->nullable()->after('accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('commercial_documents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('revision_of_id');
            $table->dropColumn(['revision_number', 'outcome_reason', 'outcome_note', 'closed_at']);
        });
    }
};
