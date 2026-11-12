<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('review_sms_token', 22)->nullable()->unique();
            $table->timestamp('review_sms_attempted_at')->nullable();
            $table->timestamp('review_sms_sent_at')->nullable();
            $table->string('review_sms_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['review_sms_token']);
            $table->dropColumn(['review_sms_token', 'review_sms_attempted_at', 'review_sms_sent_at', 'review_sms_id']);
        });
    }
};
