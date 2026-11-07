<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_branches', function (Blueprint $table): void {
            $table->boolean('online_only')->default(false);
            $table->dateTime('online_only_until')->nullable();
            $table->json('online_only_weekdays')->nullable();
        });

        $until = Carbon::parse('2026-11-08 00:00:00', 'Europe/Minsk')->setTimezone(config('app.timezone'));

        if ($until->isFuture()) {
            DB::table('partner_branches')
                ->whereIn('city_id', DB::table('cities')->select('id')->where('slug', 'minsk'))
                ->whereIn('b2b_client_id', DB::table('b2b_clients')->select('id')->where('unp', '193265863'))
                ->where('address', 'like', '%Селицкого%')
                ->where('is_active', true)
                ->update(['online_only' => true, 'online_only_until' => $until]);
        }
    }

    public function down(): void
    {
        Schema::table('partner_branches', function (Blueprint $table): void {
            $table->dropColumn(['online_only', 'online_only_until', 'online_only_weekdays']);
        });
    }
};
