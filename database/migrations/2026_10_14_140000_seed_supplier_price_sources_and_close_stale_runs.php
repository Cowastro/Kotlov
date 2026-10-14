<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('suppliers') || ! Schema::hasTable('supplier_sources')) {
            return;
        }

        $sources = [
            [
                'codes' => ['bania'],
                'names' => ['BANIA.by', 'Банька'],
                'source_name' => 'Онлайн-прайс с нашими ценами',
                'type' => 'google_sheet',
                'url' => 'https://docs.google.com/spreadsheets/d/1eDUENigmEbQjge6G1WFqvJdjZ_YBM32A/edit?gid=700541031#gid=700541031',
            ],
            [
                'codes' => ['ligmet', 'ligmet-group'],
                'names' => ['Лигмет груп', 'Лигмет групп'],
                'source_name' => 'Онлайн-остатки и прайс',
                'type' => 'google_drive',
                'url' => 'https://drive.google.com/drive/folders/1pQQRGMKBEHHEjUF3AYsi_dTvlxxTFxzz',
            ],
            [
                'codes' => ['termostudia', 'termostudio'],
                'names' => ['Термостудия'],
                'source_name' => 'Онлайн-остатки',
                'type' => 'google_sheet',
                'url' => 'https://docs.google.com/spreadsheets/d/1cQEYeQIDjAyHG-_r-ZqL0v0zsSj9hZ5m9yDpyTBBqm0/edit?gid=1298422136#gid=1298422136',
            ],
            [
                'codes' => ['maytek', 'maytek-group', 'maitek'],
                'names' => ['Майтек груп', 'Майтек групп'],
                'source_name' => 'Онлайн-остатки',
                'type' => 'google_sheet',
                'url' => 'https://docs.google.com/spreadsheets/d/1Cucw7U8f2Qs6U7kcWZvrdwZitXgP17j5W0TcujUFiN8/edit?pli=1&gid=2059074837#gid=2059074837',
            ],
        ];

        foreach ($sources as $source) {
            $supplierId = DB::table('suppliers')
                ->whereIn('code', $source['codes'])
                ->value('id');

            if (! $supplierId) {
                $supplierId = DB::table('suppliers')
                    ->where(function ($query) use ($source): void {
                        foreach ($source['names'] as $name) {
                            $query->orWhereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($name).'%']);
                        }
                    })
                    ->value('id');
            }

            if (! $supplierId) {
                continue;
            }

            $existing = DB::table('supplier_sources')
                ->where('supplier_id', $supplierId)
                ->where('name', $source['source_name'])
                ->first();

            $values = [
                'type' => $source['type'],
                'config' => json_encode(['url' => $source['url']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'is_active' => true,
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('supplier_sources')->where('id', $existing->id)->update($values);
            } else {
                DB::table('supplier_sources')->insert($values + [
                    'supplier_id' => $supplierId,
                    'name' => $source['source_name'],
                    'schedule_cron' => 'Обновляется поставщиком',
                    'created_at' => now(),
                ]);
            }
        }

        if (Schema::hasTable('supplier_syncs')) {
            DB::table('supplier_syncs')
                ->where('last_status', 'running')
                ->where('last_run_at', '<', now()->subHours(6))
                ->update([
                    'last_status' => 'stale',
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Справочные ссылки и исправленные статусы не удаляем при откате кода.
    }
};
