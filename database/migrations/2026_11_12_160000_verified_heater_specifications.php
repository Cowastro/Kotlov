<?php

use App\Models\Product;
use App\Support\Catalog\HeaterCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    public function up(): void
    {
        $catalog = app(HeaterCatalog::class);
        foreach (Product::whereIn('slug', array_keys(HeaterCatalog::records()))->with('category')->cursor() as $product) {
            if (! isset(HeaterCatalog::NAMES[$product->category?->slug])) continue;
            $record = HeaterCatalog::verified($product);
            $specs = $product->specs ?: [];
            foreach ($catalog->productFacts($product, $product->category->slug, $catalog->dimensions($product->category->slug))['card'] as $key => $value) {
                $specs[$key] = $value;
            }
            $documents = is_array($product->documents) ? $product->documents : [];
            foreach (array_filter([$record['source']['document'] ?? null, $record['source']['url'] ?? null]) as $url) {
                if (! collect($documents)->contains(fn ($doc) => (is_array($doc) ? ($doc['url'] ?? null) : $doc) === $url)) {
                    $documents[] = ['label' => str_ends_with($url, '.pdf') ? 'Паспорт производителя' : 'Источник технических характеристик', 'url' => $url];
                }
            }
            $product->forceFill(['specs' => $specs, 'documents' => $documents])->saveQuietly();
        }
        Cache::flush();
    }

    public function down(): void {}
};
