<?php

use App\Models\Category;
use App\Models\Product;
use App\Support\Catalog\RadiatorCatalog;
use App\Support\Catalog\RadiatorCategoryClassifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    private const BACKUP = 'maintenance/radiator-catalog-2026-11-12.json';

    public function up(): void
    {
        $categories = Category::active()->whereIn('slug', RadiatorCategoryClassifier::CATEGORIES)->pluck('id', 'slug');
        $products = Product::whereIn('category_id', $categories->values())->where('is_archived', false)->with(['category', 'brand'])->get();
        if ($products->isEmpty()) return;
        $disk = Storage::disk('local');
        $backup = $disk->exists(self::BACKUP) ? json_decode($disk->get(self::BACKUP), true, flags: JSON_THROW_ON_ERROR) : [];
        $updates = [];
        $catalog = app(RadiatorCatalog::class);
        foreach ($products as $product) {
            if (isset($backup[$product->id])) continue;
            $replacement = [];
            $slug = RadiatorCategoryClassifier::slug($product->name, $product->category->slug, $product->brand?->name ?? '');
            if ($slug && isset($categories[$slug]) && (int) $categories[$slug] !== (int) $product->category_id) $replacement['category_id'] = (int) $categories[$slug];
            if (RadiatorCatalog::verified($product) !== []) {
                $specs = $catalog->details($product, []);
                if ($product->specs !== $specs) $replacement['specs'] = $specs;
                // A copied Biliner-style description was attached to different PianoForte/Tower models.
                // Replace only this positively identified defective template; retain other editorial text.
                $text = strip_tags((string) $product->short_description.' '.(string) $product->content);
                if (str_contains(mb_strtolower($product->name), 'royal') && str_contains($text, '1026') && str_contains($text, '574')) {
                    $replacement += $catalog->cleanDescription($product);
                }
            }
            if ($replacement === []) continue;
            $backup[$product->id] = ['slug' => $product->slug, 'original' => $product->only(array_keys($replacement)), 'replacement' => $replacement];
            $updates[$product->id] = $replacement;
        }
        if (! $disk->put(self::BACKUP, json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))) throw new RuntimeException('Radiator backup could not be saved.');
        DB::transaction(function () use ($products, $updates): void {
            foreach ($products as $product) if (isset($updates[$product->id])) $product->forceFill($updates[$product->id])->saveQuietly();
        });
        Cache::flush();
    }

    public function down(): void
    {
        $disk = Storage::disk('local');
        if (! $disk->exists(self::BACKUP)) return;
        $backup = json_decode($disk->get(self::BACKUP), true, flags: JSON_THROW_ON_ERROR);
        DB::transaction(function () use ($backup): void {
            foreach ($backup as $id => $row) {
                $product = Product::whereKey($id)->where('slug', $row['slug'])->first();
                // MySQL normalizes JSON object key order; compare associative values, not insertion order.
                if ($product && $product->only(array_keys($row['replacement'])) == $row['replacement']) $product->forceFill($row['original'])->saveQuietly();
            }
        });
        Cache::flush();
    }
};
