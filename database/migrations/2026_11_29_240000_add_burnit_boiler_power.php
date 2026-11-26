<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The seven BURNiT pellet boilers carried no specs, so the «Мощность» filter of «Пеллетные котлы» missed them. Their
 * nominal power is the model number (Advant 4G 13/18/25, Ambient 4G/10, VIZION 4G/13/18/25, kW): it is added as a spec.
 */
return new class extends Migration
{
    public const POWER = [
        'pelletnyi-kotel-burnit-advant-4g-13' => 13,
        'pelletnyi-kotel-burnit-advant-4g-18' => 18,
        'pelletnyi-kotel-burnit-advant-4g-25' => 25,
        'pelletnyi-kotel-burnit-ambient-4g10' => 10,
        'pelletnyi-kotel-burnit-vizion-4g13' => 13,
        'pelletnyi-kotel-burnit-vizion-4g18' => 18,
        'pelletnyi-kotel-burnit-vizion-4g25' => 25,
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
                if (str_contains(mb_strtolower($name), 'мощност')) {
                    continue 2;
                }
            }
            $specs[] = ['key' => 'Номинальная мощность', 'value' => $kw.' кВт'];
            DB::table('products')->where('id', $product->id)->update(['specs' => json_encode($specs, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
        }
    }

    public function down(): void {}
};
