<?php

use App\Models\Product;
use App\Support\Catalog\HeaterCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    public function up(): void
    {
        // Reuse the idempotent writer with the expanded, model-specific evidence set.
        $writer = require __DIR__.'/2026_11_12_160000_verified_heater_specifications.php';
        $writer->up();

        foreach (HeaterCatalog::records() as $slug => $record) {
            if (! isset($record['consumption_kw'])) continue;
            $product = Product::where('slug', $slug)->with('category')->first();
            if (! $product || ! isset(HeaterCatalog::NAMES[$product->category?->slug])) continue;
            $specs = $product->specs ?: [];
            $specs['Потребляемая мощность'] = str_replace('.', ',', (string) $record['consumption_kw']).' кВт';
            $product->forceFill(['specs' => $specs])->saveQuietly();
        }
        Cache::flush();
    }

    public function down(): void {}
};
