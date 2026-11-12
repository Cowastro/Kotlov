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
    private const BACKUP = 'maintenance/radiator-families-2026-11-12.json';

    public function up(): void
    {
        $categories = Category::active()->whereIn('slug', RadiatorCategoryClassifier::CATEGORIES)->pluck('id', 'slug');
        if (! isset($categories['radiatory'])) return;
        $disk = Storage::disk('local');
        $backup = $disk->exists(self::BACKUP) ? json_decode($disk->get(self::BACKUP), true, flags: JSON_THROW_ON_ERROR) : [];
        $updates = [];
        $catalog = app(RadiatorCatalog::class);
        foreach (Product::whereIn('category_id', $categories->values())->where('is_archived', false)->with(['brand', 'category'])->get() as $product) {
            if (isset($backup[$product->id])) continue;
            $slug = RadiatorCategoryClassifier::slug($product->name, '', $product->brand?->name ?? '');
            $replacement = [];
            if ($slug && $slug !== 'radiatory' && isset($categories[$slug]) && (int) $categories[$slug] !== (int) $product->category_id) $replacement['category_id'] = (int) $categories[$slug];
            $fact = RadiatorCatalog::verified($product);
            if ($fact !== []) {
                $specs = $catalog->details($product, []);
                if ($product->specs != $specs) $replacement['specs'] = $specs;
                if (($fact['material'] ?? '') === 'aluminium' && preg_match('~(?:^|это\s+[^.]{0,80})биметаллическ~ui', trim(strip_tags((string) $product->short_description)))) $replacement += $catalog->cleanDescription($product);
            }
            if ($replacement === []) continue;
            $backup[$product->id] = ['slug' => $product->slug, 'original' => $product->only(array_keys($replacement)), 'replacement' => $replacement];
            $updates[$product->id] = $replacement;
        }
        if ($updates === []) return;
        if (! $disk->put(self::BACKUP, json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))) throw new RuntimeException('Radiator family backup could not be saved.');
        DB::transaction(function () use ($updates): void {
            foreach ($updates as $id => $fields) Product::findOrFail($id)->forceFill($fields)->saveQuietly();
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
                if ($product && $product->only(array_keys($row['replacement'])) == $row['replacement']) $product->forceFill($row['original'])->saveQuietly();
            }
        });
        Cache::flush();
    }
};
