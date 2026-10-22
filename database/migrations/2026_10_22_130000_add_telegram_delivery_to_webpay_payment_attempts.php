<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webpay_payment_attempts', function (Blueprint $table): void {
            $table->timestamp('telegram_notified_at')->nullable()->after('verified_at');
            $table->timestamp('telegram_notification_claimed_at')->nullable()->after('telegram_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('webpay_payment_attempts', function (Blueprint $table): void {
            $table->dropColumn(['telegram_notified_at', 'telegram_notification_claimed_at']);
        });
    }
};
