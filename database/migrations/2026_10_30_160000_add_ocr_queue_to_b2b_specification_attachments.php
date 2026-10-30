<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('b2b_specification_attachments', function (Blueprint $table): void {
            $table->uuid('ocr_batch_id')->nullable()->after('sha256')->index();
            $table->string('ocr_status', 30)->default('idle')->after('ocr_batch_id')->index();
            $table->text('ocr_message')->nullable()->after('ocr_status');
            $table->unsignedSmallInteger('ocr_attempts')->default(0)->after('ocr_message');
            $table->foreignId('ocr_requested_by')->nullable()->after('ocr_attempts')->constrained('users')->nullOnDelete();
            $table->timestamp('ocr_requested_at')->nullable()->after('ocr_requested_by');
            $table->timestamp('ocr_started_at')->nullable()->after('ocr_requested_at');
            $table->timestamp('ocr_completed_at')->nullable()->after('ocr_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('b2b_specification_attachments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('ocr_requested_by');
            $table->dropIndex(['ocr_batch_id']);
            $table->dropIndex(['ocr_status']);
            $table->dropColumn([
                'ocr_batch_id', 'ocr_status', 'ocr_message', 'ocr_attempts',
                'ocr_requested_at', 'ocr_started_at', 'ocr_completed_at',
            ]);
        });
    }
};
