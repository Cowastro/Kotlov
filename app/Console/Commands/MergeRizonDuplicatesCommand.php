<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Two cards per Теплоприбор Rizon M model made the Thermokraft price row
 * ambiguous. Keeps the newer card (full specs), takes over the larger photo
 * gallery, reviews, attribute values and supplier links of the old card,
 * archives the old card and 301-redirects its URL to the kept card.
 * Pairs approved by the owner on 22.10.2026.
 *
 *   php artisan catalog:merge-rizon-duplicates            # dry run
 *   php artisan catalog:merge-rizon-duplicates --apply
 */
class MergeRizonDuplicatesCommand extends Command
{
    protected $signature = 'catalog:merge-rizon-duplicates {--apply : Merge and archive the duplicates}';

    protected $description = 'Merge the duplicate Rizon M boiler cards (keep the newer card, redirect the old one)';

    /** kept product id => duplicate product id */
    public const PAIRS = [
        12639 => 10068, // Rizon M 10 A
        12636 => 6794,  // Rizon M 16
        12641 => 6835,  // Rizon M 20
        12637 => 6891,  // Rizon M 25
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $stats = ['pairs' => 0, 'skipped' => 0, 'images_taken' => 0, 'reviews_moved' => 0, 'attributes_moved' => 0, 'links_moved' => 0, 'archived' => 0, 'redirects' => 0];

        $run = function () use (&$stats, $apply): void {
            foreach (self::PAIRS as $keepId => $dupeId) {
                $keep = Product::query()->with('category')->find($keepId);
                $dupe = Product::query()->with('category')->find($dupeId);
                if (! $keep || ! $dupe || $dupe->is_archived || ! str_contains(mb_strtolower($keep->name.' '.$dupe->name), 'rizon')) {
                    $stats['skipped']++;
                    $this->line("audit.rizon_merge.pair={$keepId}:{$dupeId}:skipped");

                    continue;
                }
                $stats['pairs']++;
                $this->line("audit.rizon_merge.pair={$keepId}:{$dupeId}:{$keep->slug}:{$dupe->slug}");

                $keepImages = array_values(array_filter((array) $keep->images));
                $dupeImages = array_values(array_filter((array) $dupe->images));
                $takeImages = count($dupeImages) > count($keepImages);
                $stats['images_taken'] += (int) $takeImages;

                $reviews = DB::table('reviews')->where('reviewable_type', Product::class)->where('reviewable_id', $dupeId);
                $stats['reviews_moved'] += (clone $reviews)->count();
                $keepAttributes = DB::table('product_attribute_values')->where('product_id', $keepId)->pluck('attribute_id');
                $attributes = DB::table('product_attribute_values')->where('product_id', $dupeId)->whereNotIn('attribute_id', $keepAttributes);
                $stats['attributes_moved'] += (clone $attributes)->count();
                $links = DB::table('supplier_products')->where('product_id', $dupeId);
                $stats['links_moved'] += (clone $links)->count();

                if (! $apply) {
                    continue;
                }

                if ($takeImages) {
                    $keep->forceFill(['images' => array_values(array_unique(array_merge($dupeImages, $keepImages)))])->save();
                }
                $reviews->update(['reviewable_id' => $keepId, 'updated_at' => now()]);
                $attributes->update(['product_id' => $keepId, 'updated_at' => now()]);
                $links->update(['product_id' => $keepId, 'product_sku' => $keep->sku, 'updated_at' => now()]);

                $from = '/'.$dupe->category?->slug.'/'.$dupe->slug;
                $to = '/'.$keep->category?->slug.'/'.$keep->slug;
                DB::table('redirects')->updateOrInsert(['from_url' => $from], ['to_url' => $to, 'status_code' => 301, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()]);
                $stats['redirects']++;
                $dupe->forceFill(['is_active' => false, 'is_archived' => true])->save();
                $stats['archived']++;
            }
        };

        $apply ? DB::transaction($run) : $run();

        foreach ($stats as $key => $value) {
            $this->line("metric.rizon_merge.{$key}={$value}");
        }
        if (! $apply) {
            $this->warn('Предпросмотр: база не изменена. Для записи добавьте --apply.');
        }

        return self::SUCCESS;
    }
}
