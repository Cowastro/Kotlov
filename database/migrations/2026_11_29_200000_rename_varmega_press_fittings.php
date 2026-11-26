<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * 269 Varmega press-fitting cards were named only by article and size («Varmega VM710002828 28x28»). They take the
 * manufacturer's names from varmega.ru («Угольник 90° двухраструбный Varmega Inox Press VM710002828, 28×28 мм»), kept
 * in database/data/varmega_press_fitting_names_2026-11.json; the slugs stay. Then the press-fitting filters are refilled.
 */
return new class extends Migration
{
    public function up(): void
    {
        $names = json_decode((string) file_get_contents(database_path('data/varmega_press_fitting_names_2026-11.json')), true) ?: [];
        foreach ($names as $old => $new) {
            foreach (DB::table('products')->where('name', $old)->get(['id', 'h1', 'meta_title', 'meta_description']) as $product) {
                $swap = fn (?string $text) => $text === null ? null : str_replace($old, $new, $text);
                DB::table('products')->where('id', $product->id)->update([
                    'name' => $new,
                    'h1' => $product->h1 === null || $product->h1 === $old ? $new : $swap($product->h1),
                    'meta_title' => $swap($product->meta_title),
                    'meta_description' => $swap($product->meta_description),
                    'updated_at' => now(),
                ]);
            }
        }
        if (! app()->runningUnitTests()) {
            Artisan::call('catalog:sort-boiler-automation', ['--apply' => true]);
        }
    }

    public function down(): void {}
};
