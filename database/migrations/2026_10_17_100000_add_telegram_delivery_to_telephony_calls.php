<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telephony_calls', function (Blueprint $table): void {
            $table->unsignedBigInteger('telegram_message_id')->nullable()->after('metadata');
            $table->timestamp('telegram_notified_at')->nullable()->after('telegram_message_id');
            $table->timestamp('telegram_notification_claimed_at')->nullable()->after('telegram_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('telephony_calls', function (Blueprint $table): void {
            $table->dropColumn([
                'telegram_message_id',
                'telegram_notified_at',
                'telegram_notification_claimed_at',
            ]);
        });
    }
};
