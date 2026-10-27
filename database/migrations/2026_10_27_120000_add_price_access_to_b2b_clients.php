<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('b2b_clients', function (Blueprint $table): void {
            $table->json('allowed_integration_source_ids')->nullable()->after('delivery_terms');
        });

        $sourceIds = DB::table('integration_sources')
            ->where('is_active', true)
            ->get(['id', 'settings'])
            ->filter(function (object $source): bool {
                $settings = json_decode((string) $source->settings, true) ?: [];

                return ($settings['b2b_enabled'] ?? false) === true
                    && ($settings['b2b_price_role'] ?? 'unknown') === 'sale';
            })
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        DB::table('b2b_clients')
            ->where('status', 'approved')
            ->update(['allowed_integration_source_ids' => json_encode($sourceIds)]);
    }

    public function down(): void
    {
        Schema::table('b2b_clients', function (Blueprint $table): void {
            $table->dropColumn('allowed_integration_source_ids');
        });
    }
};
