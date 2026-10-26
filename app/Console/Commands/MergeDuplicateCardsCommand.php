<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Merges pairs of duplicate cards found by a supplier sync (e.g. the
 * `audit.tsk_sync.collision=8564,8696` lines of supplier:sync-tsk-nasosy).
 * Keeps the fuller card (brand, photos, description, specs; older id on a tie),
 * takes over the larger gallery, reviews, attribute values and supplier links,
 * archives the other card and 301-redirects its URL. Same steps as
 * catalog:merge-rizon-duplicates.
 *
 *   php artisan catalog:merge-duplicate-cards 8564,8696 8569,8697            # dry run
 *   php artisan catalog:merge-duplicate-cards 8564,8696 8569,8697 --apply
 */
class MergeDuplicateCardsCommand extends Command
{
    protected $signature = 'catalog:merge-duplicate-cards {pairs* : id,id per duplicate pair} {--apply : Merge and archive the duplicates}';

    protected $description = 'Merge pairs of duplicate product cards (keep the fuller card, redirect the other)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $stats = ['pairs' => 0, 'skipped' => 0, 'images_taken' => 0, 'reviews_moved' => 0, 'attributes_moved' => 0, 'links_moved' => 0, 'integration_moved' => 0, 'archived' => 0, 'redirects' => 0];

        $run = function () use (&$stats, $apply): void {
            foreach ((array) $this->argument('pairs') as $pair) {
                $ids = array_map('intval', explode(',', (string) $pair));
                $cards = Product::query()->with('category')->whereIn('id', $ids)->where('is_archived', false)->get();
                if (count($ids) !== 2 || $cards->count() !== 2 || $this->digits($cards[0]->name) !== $this->digits($cards[1]->name)) {
                    $stats['skipped']++;
                    $this->line('audit.merge_cards.pair='.implode(':', $ids).':skipped');

                    continue;
                }
                [$keep, $dupe] = $cards->sortByDesc(fn (Product $p) => [$this->fullness($p), -$p->id])->values()->all();
                $keepId = (int) $keep->id;
                $dupeId = (int) $dupe->id;
                $stats['pairs']++;
                $this->line("audit.merge_cards.pair={$keepId}:{$dupeId}:{$keep->slug}:{$dupe->slug}");

                $keepImages = array_values(array_filter((array) $keep->images));
                $dupeImages = array_values(array_filter((array) $dupe->images));
                $takeImages = count($dupeImages) > count($keepImages);
                $stats['images_taken'] += (int) $takeImages;

                $reviews = DB::table('reviews')->where('reviewable_type', Product::class)->where('reviewable_id', $dupeId);
                $stats['reviews_moved'] += (clone $reviews)->count();
                $keepAttributes = DB::table('product_attribute_values')->where('product_id', $keepId)->pluck('attribute_id');
                $attributes = DB::table('product_attribute_values')->where('product_id', $dupeId)->whereNotIn('attribute_id', $keepAttributes);
                $stats['attributes_moved'] += (clone $attributes)->count();
                // Every link moves, also a second article of the same supplier (the price lists the model twice).
                $links = DB::table('supplier_products')->where('product_id', $dupeId);
                $stats['links_moved'] += (clone $links)->count();
                // 1C (and other integration) rows of the duplicate move too, so the next import still finds the card.
                $integration = DB::table('integration_products')->where('product_id', $dupeId);
                $stats['integration_moved'] += (clone $integration)->count();

                if (! $apply) {
                    continue;
                }

                if ($takeImages) {
                    $keep->forceFill(['images' => array_values(array_unique(array_merge($dupeImages, $keepImages)))])->save();
                }
                $reviews->update(['reviewable_id' => $keepId, 'updated_at' => now()]);
                $attributes->update(['product_id' => $keepId, 'updated_at' => now()]);
                $links->update(['product_id' => $keepId, 'product_sku' => $keep->sku, 'updated_at' => now()]);
                $integration->update(['product_id' => $keepId, 'updated_at' => now()]);

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
            $this->line("metric.merge_cards.{$key}={$value}");
        }
        if (! $apply) {
            $this->warn('Предпросмотр: база не изменена. Для записи добавьте --apply.');
        }

        return self::SUCCESS;
    }

    /** Both cards must name the same model numbers, so a wrong pair is never merged. */
    private function digits(string $name): string
    {
        preg_match_all('/\d+/', $name, $m);

        return implode(' ', $m[0]);
    }

    private function fullness(Product $p): int
    {
        return ($p->brand_id ? 4 : 0)
            + min(count(array_filter((array) $p->images)), 5)
            + (mb_strlen(strip_tags((string) $p->content)) > 200 ? 3 : 0)
            + min(DB::table('product_attribute_values')->where('product_id', $p->id)->count(), 5);
    }
}
