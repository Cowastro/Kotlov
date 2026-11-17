<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * New Русклимат air purifiers/humidifiers (17.11.2026) got «Макс. потребляемая мощность» from rusklimat.ru in watts
 * (50, 12.5) while the category attribute is in kW — the card showed «50 кВт». Values above 5 on these cards are
 * watts: they become kW (0.05). Only the cards created that day are touched.
 */
return new class extends Migration
{
    private const CARDS = [43845, 43846, 43847, 43848, 43849, 43850, 43851, 43852, 43853];

    public function up(): void
    {
        $attributeIds = DB::table('attributes')->where('name', 'Макс. потребляемая мощность')
            ->whereRaw('LOWER(TRIM(suffix)) = ?', ['квт'])->pluck('id');
        $rows = DB::table('product_attribute_values')->whereIn('product_id', self::CARDS)->whereIn('attribute_id', $attributeIds)->get(['id', 'value']);
        foreach ($rows as $row) {
            $value = (float) str_replace(',', '.', (string) $row->value);
            if ($value > 5) {
                DB::table('product_attribute_values')->where('id', $row->id)->update([
                    'value' => rtrim(rtrim(number_format($value / 1000, 4, '.', ''), '0'), '.'),
                    'updated_at' => now(),
                ]);
            }
        }
        Cache::flush();
    }

    public function down(): void {}
};
