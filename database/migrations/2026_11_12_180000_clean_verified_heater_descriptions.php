<?php

use App\Models\Product;
use App\Support\Catalog\HeaterCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    private const BACKUP = 'maintenance/heater-descriptions-2026-11-12.json';

    public function up(): void
    {
        $audit = json_decode(file_get_contents(resource_path('data/heater-specification-audit.json')), true, flags: JSON_THROW_ON_ERROR);
        $slugs = array_unique([...array_keys(HeaterCatalog::records()), ...array_column($audit['unresolved'], 'slug')]);
        $products = Product::whereIn('slug', $slugs)->with('category')->get()
            ->filter(fn (Product $product): bool => isset(HeaterCatalog::NAMES[$product->category?->slug]));
        if ($products->isEmpty()) return;

        $disk = Storage::disk('local');
        $backup = $disk->exists(self::BACKUP) ? json_decode($disk->get(self::BACKUP), true, flags: JSON_THROW_ON_ERROR) : [];
        $catalog = app(HeaterCatalog::class);
        $updates = [];
        foreach ($products as $product) {
            // Preserve the first snapshot and any later manual edits on a repeated run.
            if (isset($backup[$product->id])) {
                $saved = $backup[$product->id];
                if ($saved['slug'] === $product->slug && $product->only(array_keys($saved['original'])) === $saved['original']) {
                    $updates[$product->id] = $saved['replacement'];
                }
                continue;
            }
            $replacement = $catalog->description($product);
            $backup[$product->id] = [
                'slug' => $product->slug,
                'original' => $product->only(['short_description', 'content', 'meta_description']),
                'replacement' => $replacement,
            ];
            $updates[$product->id] = $replacement;
        }
        if (! $disk->put(self::BACKUP, json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))) {
            throw new RuntimeException('Heater description backup could not be saved.');
        }
        DB::transaction(function () use ($products, $updates): void {
            foreach ($products as $product) {
                if (isset($updates[$product->id])) $product->forceFill($updates[$product->id])->saveQuietly();
            }
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
                if (! $product) continue;
                if ($product->only(array_keys($row['replacement'])) === $row['replacement']) {
                    $product->forceFill($row['original'])->saveQuietly();
                }
            }
        });
        Cache::flush();
    }
};
