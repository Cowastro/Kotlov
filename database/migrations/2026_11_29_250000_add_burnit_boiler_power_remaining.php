<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Three BURNiT cards were skipped by the previous migration: «Время горения на полной мощности» read as a power spec.
 * Only a key starting with «Мощность»/«Номинальная мощность» counts as one now.
 */
return new class extends Migration
{
    public const POWER = [
        'pelletnyi-kotel-burnit-advant-4g-13' => 13,
        'pelletnyi-kotel-burnit-advant-4g-25' => 25,
        'pelletnyi-kotel-burnit-ambient-4g10' => 10,
    ];

    public function up(): void
    {
        foreach (self::POWER as $slug => $kw) {
            $product = DB::table('products')->where('slug', $slug)->first(['id', 'specs']);
            if (! $product) {
                continue;
            }
            $specs = json_decode((string) $product->specs, true);
            $specs = is_array($specs) ? $specs : [];
            foreach ($specs as $key => $spec) {
                $name = is_array($spec) ? (string) ($spec['key'] ?? $spec['name'] ?? '') : (string) $key;
                if (preg_match('/^(номинальная |максимальная )?мощност/u', mb_strtolower(trim($name)))) {
                    continue 2;
                }
            }
            $specs[] = ['key' => 'Номинальная мощность', 'value' => $kw.' кВт'];
            DB::table('products')->where('id', $product->id)->update(['specs' => json_encode($specs, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
        }
    }

    public function down(): void {}
};
