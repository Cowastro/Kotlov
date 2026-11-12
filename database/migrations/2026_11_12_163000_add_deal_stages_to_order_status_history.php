<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_status_history', function (Blueprint $table): void {
            $table->string('deal_stage_from', 24)->nullable();
            $table->string('deal_stage_to', 24)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_status_history', function (Blueprint $table): void {
            $table->dropColumn(['deal_stage_from', 'deal_stage_to']);
        });
    }
};
