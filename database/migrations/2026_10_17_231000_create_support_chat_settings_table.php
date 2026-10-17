<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_conversations', function (Blueprint $table) {
            $table->timestamp('ai_handoff_at')->nullable()->after('last_manager_message_at');
        });

        Schema::create('support_chat_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->default('default')->unique();
            $table->boolean('ai_enabled')->default(false);
            $table->foreignId('enabled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_chat_settings');

        Schema::table('support_conversations', function (Blueprint $table) {
            $table->dropColumn('ai_handoff_at');
        });
    }
};
