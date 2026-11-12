<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('deal_stage', 24)->nullable();
            $table->text('deal_next_action')->nullable();
            $table->dateTime('deal_control_at')->nullable()->index();
            $table->boolean('deal_on_hold')->default(false);
            $table->unsignedInteger('deal_version')->default(0);
            $table->dateTime('deal_stage_changed_at')->nullable();
            $table->dateTime('deal_control_notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['deal_control_at']);
            $table->dropColumn(['deal_stage', 'deal_next_action', 'deal_control_at', 'deal_on_hold',
                'deal_version', 'deal_stage_changed_at', 'deal_control_notified_at']);
        });
    }
};
