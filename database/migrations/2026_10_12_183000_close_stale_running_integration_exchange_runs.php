<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('integration_exchange_runs')) {
            return;
        }

        DB::table('integration_exchange_runs')
            ->where('status', 'running')
            ->where('started_at', '<', now()->subHour())
            ->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => 'Сеанс не был завершён. Закрыт автоматически после исправления приёма многочастных файлов CommerceML.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Historical exchange states must not be reopened on rollback.
    }
};
