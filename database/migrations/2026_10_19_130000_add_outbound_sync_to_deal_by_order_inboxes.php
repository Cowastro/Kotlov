<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deal_by_order_inboxes', function (Blueprint $table): void {
            $table->string('last_pushed_status')->nullable()->after('last_error');
            $table->timestamp('last_pushed_at')->nullable()->after('last_pushed_status');
            $table->text('last_push_error')->nullable()->after('last_pushed_at');
            $table->unsignedInteger('push_attempts')->default(0)->after('last_push_error');
        });
    }

    public function down(): void
    {
        Schema::table('deal_by_order_inboxes', function (Blueprint $table): void {
            $table->dropColumn(['last_pushed_status', 'last_pushed_at', 'last_push_error', 'push_attempts']);
        });
    }
};
