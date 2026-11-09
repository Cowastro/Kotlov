<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_conversations', function (Blueprint $table): void {
            $table->unsignedBigInteger('ai_resume_message_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('support_conversations', function (Blueprint $table): void {
            $table->dropColumn('ai_resume_message_id');
        });
    }
};
