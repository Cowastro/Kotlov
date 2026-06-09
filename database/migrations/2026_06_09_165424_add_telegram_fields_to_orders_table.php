<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('assigned_to')->nullable()->after('admin_comment');
            $table->unsignedBigInteger('telegram_message_id')->nullable()->after('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['assigned_to', 'telegram_message_id']);
        });
    }
};
