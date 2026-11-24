<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

/**
 * «/Sh» is the built-in damper version of a firebox: never the base card. ДЕЛЬТА 1000 BV/Sh came in the stock of 24.11
 * (1 pc) — its own card like the base ДЕЛЬТА 1000 BV (12371), and the stock article tied to it.
 */
return new class extends Migration
{
    private const TITLE = 'Каминная топка Экокамин ДЕЛЬТА 1000 BV/Sh три стекла, шибер встроенный, принтинг, черный шамот, вертикальная ламель';

    public function up(): void
    {
        $base = Product::query()->find(12371);
        if (! $base || app()->runningUnitTests()) {
            return;
        }
        $slug = Str::slug(self::TITLE);
        if (! Product::query()->where('slug', $slug)->exists()) {
            Artisan::call('integration:create-onec-card', [
                'code' => 'TDN1000BV/Sh', 'category' => '-', '--like' => $base->id, '--without-row' => true,
                '--title' => self::TITLE, '--price' => (string) $base->price,
                '--replace' => ['ДЕЛЬТА 1000 BV::ДЕЛЬТА 1000 BV/Sh', 'три стекла принтинг::три стекла, шибер встроенный, принтинг,'],
                '--apply' => true,
            ]);
        }
        $path = database_path('data/ecokamin_moscow_stock_2026-11-24.json');
        if (is_file($path) && Product::query()->where('slug', $slug)->exists()) {
            Artisan::call('supplier:import-ecokamin-moscow-stock', ['file' => $path, '--link' => ['TDN1000BV/Sh='.$slug], '--apply' => true]);
        }
    }

    public function down(): void
    {
        // The card stays: it may already be in orders.
    }
};
