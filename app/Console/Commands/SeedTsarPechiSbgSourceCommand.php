<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One-off, second pass for brand «Царь-Печи»: link the products that had NO
 * counterpart on teplodvor.by (see SeedTsarPechiTeplodvorSourceCommand) to
 * their sbg.by (deal.by storefront) product page instead, so the same
 * generic `supplier:enrich-source-products --domain=sbg.by` can fill in
 * photos + specs + AI content for them too.
 *
 * sbg.by product URL scheme: https://sbg.by/p{deal.by uid}-x.html — the
 * numeric id is deal.by's own internal product id (same id visible as
 * Уникальный_идентификатор in their own export, see
 * project_sbg_dealby_price_sync.md), the trailing slug text is ignored by
 * their router. Every id below was hand-matched from the deal.by export
 * used earlier in this session for the Царь-Печи price sync.
 *
 *   php artisan supplier:seed-tsarpechi-sbg-source            # dry run
 *   php artisan supplier:seed-tsarpechi-sbg-source --apply
 */
class SeedTsarPechiSbgSourceCommand extends Command
{
    protected $signature = 'supplier:seed-tsarpechi-sbg-source {--apply}';

    protected $description = 'Link remaining Царь-Печи products (no teplodvor match) to sbg.by via supplier_products.source_url';

    /** @var array<int,int> product id => deal.by (sbg.by) uid */
    private const MAP = [
        9945 => 96364018,  // Забава Декор (8мм)
        11982 => 225426439, // Емеля Лайт (3мм)
        11981 => 225426767, // Емеля мини Лайт (3мм)
        5888 => 100573150, // Добрыня 1 (8мм)
        9947 => 35833972,  // Добрыня 2 (8мм)
        9953 => 38648436,  // Малуша (8мм)
        9954 => 38649006,  // Святогор (8мм)
        9964 => 68296391,  // Любаня Премиум (8мм)
        9965 => 68298913,  // Святогор Премиум (8мм)
        9955 => 74174377,  // Царь-Вольга (8мм)
        9957 => 74174409,  // Царь-Забава (8мм)
        9928 => 127760285, // Буржуйка мини с конфоркой (dup of 9929, same listing)
        12112 => 47672028, // Пеллетная горелка для печи Потапыч
        12111 => 47672032, // Отопительная печь Потапыч (3мм)
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->line($apply ? '<fg=red;options=bold>APPLY</>' : '<fg=yellow>DRY-RUN</>');

        $supplierId = $this->manualSourceSupplierId($apply);
        $ids = array_keys(self::MAP);
        $products = DB::table('products')->whereIn('id', $ids)->get(['id', 'sku', 'name'])->keyBy('id');

        $linked = 0;
        $missing = 0;

        foreach (self::MAP as $id => $uid) {
            $url = "https://sbg.by/p{$uid}-x.html";

            $product = $products->get($id);
            if (! $product) {
                $this->warn("  id={$id} not found in DB, skip");
                $missing++;
                continue;
            }

            $this->line(sprintf('  id=%d %s -> %s', $id, mb_substr($product->name, 0, 55), $url));

            if (! $apply) {
                $linked++;
                continue;
            }

            $existing = DB::table('supplier_products')->where('product_id', $id)->orderBy('id')->first();
            if ($existing) {
                DB::table('supplier_products')->where('id', $existing->id)->update([
                    'source_url' => $url,
                    'updated_at' => now(),
                ]);
            } else {
                $article = 'manual-source-' . $id;
                DB::table('supplier_products')->insert([
                    'supplier_id' => $supplierId,
                    'product_id' => $id,
                    'product_sku' => $product->sku,
                    'supplier_article' => $article,
                    'supplier_article_normalized' => Str::lower($article),
                    'supplier_name' => $product->name,
                    'source_url' => $url,
                    'match_status' => 'manual',
                    'match_confidence' => 'source-url-edit',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $linked++;
        }

        $this->info(sprintf('%s linked=%d missing=%d', $apply ? 'APPLIED' : 'WOULD LINK', $linked, $missing));

        return self::SUCCESS;
    }

    private function manualSourceSupplierId(bool $apply): int
    {
        if (! $apply) {
            return (int) (DB::table('suppliers')->where('code', 'manual-source')->value('id') ?? 0);
        }

        DB::table('suppliers')->updateOrInsert(
            ['code' => 'manual-source'],
            [
                'name' => 'Manual source URL',
                'currency' => 'BYN',
                'currency_rate' => 1,
                'contact' => null,
                'notes' => 'Системный поставщик для ссылок, добавленных вручную из карточки товара.',
                'is_active' => false,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return (int) DB::table('suppliers')->where('code', 'manual-source')->value('id');
    }
}
