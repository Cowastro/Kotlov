<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telephony_calls', function (Blueprint $table): void {
            $table->string('manager_outcome')->nullable()->after('callback_status')->index();
            $table->timestamp('next_follow_up_at')->nullable()->after('manager_outcome')->index();
        });
    }

    public function down(): void
    {
        Schema::table('telephony_calls', function (Blueprint $table): void {
            $table->dropIndex(['manager_outcome']);
            $table->dropIndex(['next_follow_up_at']);
            $table->dropColumn(['manager_outcome', 'next_follow_up_at']);
        });
    }
};
