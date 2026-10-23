<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('suppliers') || ! Schema::hasColumn('suppliers', 'communication_channels')) {
            return;
        }

        $supplier = DB::table('suppliers')->where('code', 'azbuka-tepla')->first();
        if (! $supplier) {
            return;
        }

        $decoded = json_decode((string) ($supplier->communication_channels ?? '[]'), true);
        $channels = collect(is_array($decoded) ? $decoded : [])
            ->map(function ($channel, $key): ?array {
                if (is_array($channel)) {
                    return $channel;
                }

                if (is_string($key) && is_scalar($channel) && trim((string) $channel) !== '') {
                    return ['type' => $key, 'value' => trim((string) $channel)];
                }

                return null;
            })
            ->filter()
            ->reject(fn (array $channel): bool => in_array(
                mb_strtolower((string) ($channel['type'] ?? '')),
                ['phone', 'whatsapp', 'viber', 'telegram'],
                true,
            ))
            ->prepend([
                'type' => 'phone',
                'name' => 'Петр',
                'value' => '+375 44 557-19-35',
                'note' => 'Менеджер Азбуки Тепла',
            ])
            ->values()
            ->all();

        DB::table('suppliers')->where('id', $supplier->id)->update([
            'contact' => 'Петр: +375 44 557-19-35',
            'communication_channels' => json_encode($channels, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Contact corrections are intentionally not rolled back to stale data.
    }
};
