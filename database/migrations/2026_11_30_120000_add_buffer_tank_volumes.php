<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Nine buffer tanks carried no volume spec, so «Объём бака» of «Буферные ёмкости» missed them; the volume is the model
 * number (Drazice NADO 1000/140 = 1000 л buffer with a 140 л DHW tank, Galmet SG(B) 1500, Aquastic AQ PT 500). It is
 * added as a spec, and the section's «Тип» filter is filled.
 */
return new class extends Migration
{
    public const VOLUME = [
        'bufernaia-emkost-aquastic-aq-pt-500-erp' => 500,
        'bufernaia-emkost-drazice-nado-1000-140-v1' => 1000,
        'bufernaia-emkost-drazice-nado-500-140-v1' => 500,
        'bufernaia-emkost-drazice-nado-750-140-v1' => 750,
        'bufernaia-emkost-galmet-bufor-sgb-10000-skay-fl-seryi-80-100600' => 10000,
        'bufernaia-emkost-galmet-bufor-sgb-1500-skay-fl-seryi-70-150600' => 1500,
        'bufernaia-emkost-galmet-bufor-sgb-2000-skay-fl-seryi-80-200600' => 2000,
        'bufernaia-emkost-galmet-bufor-sgb-3000-skay-fl-seryi-80-300600' => 3000,
        'bufernaia-emkost-galmet-bufor-sgb-4000-skay-fl-seryi-80-400600' => 4000,
    ];

    public function up(): void
    {
        foreach (self::VOLUME as $slug => $litres) {
            $product = DB::table('products')->where('slug', $slug)->first(['id', 'specs']);
            if (! $product) {
                continue;
            }
            $specs = json_decode((string) $product->specs, true);
            $specs = is_array($specs) ? $specs : [];
            foreach ($specs as $key => $spec) {
                $name = is_array($spec) ? (string) ($spec['key'] ?? $spec['name'] ?? '') : (string) $key;
                if (preg_match('/^(объ[её]м|вместимость)( номинальный| полезный)?( бака| емкости| ёмкости)?$/u', mb_strtolower(trim($name)))) {
                    continue 2;
                }
            }
            $specs[] = ['key' => 'Объем бака', 'value' => $litres.' л'];
            DB::table('products')->where('id', $product->id)->update(['specs' => json_encode($specs, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
        }
        if (! app()->runningUnitTests()) {
            Artisan::call('catalog:sort-boiler-automation', ['--apply' => true]);
        }
    }

    public function down(): void {}
};
