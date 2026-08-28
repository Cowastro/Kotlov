<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One-off: link kotlov.by «Царь-Печи» products to their teplodvor.by product
 * pages via supplier_products.source_url, so the existing generic
 * `supplier:enrich-source-products --domain=teplodvor.by` can enrich them
 * (photos + specs + service_info + AI content) exactly like every other
 * source-linked brand.
 *
 * Why not the automatic teplodvor matchers (supplier:enrich-teplodvor,
 * supplier:import-catalog-teplodvor --fix-empty)? Both derive our brand's
 * expected teplodvor slug via Laravel's Str::slug(), which transliterates
 * Cyrillic "Царь-Печи" to "car-peci" — teplodvor's real slug is "tsar-pechi"
 * (a proper-noun transliteration Laravel's ASCII table doesn't produce).
 * That mismatch made every automatic match fail (0/49), so every pair below
 * was matched by hand against teplodvor's two Царь-Печи categories:
 *   https://www.teplodvor.by/shop/kotly/otopitelnye-pechi/tsar-pechi/  (heating)
 *   https://www.teplodvor.by/shop/pech-dlya-bani/tsar-pechi/          (bath)
 * 14 of the 49 active kotlov.by products have no teplodvor counterpart at
 * all (Декор/Лайт/Элит variants, "Добрыня 1/2" numbering, Потапыч line) —
 * deliberately left out, to be sourced from sbg.by instead.
 *
 *   php artisan supplier:seed-tsarpechi-teplodvor-source            # dry run
 *   php artisan supplier:seed-tsarpechi-teplodvor-source --apply
 */
class SeedTsarPechiTeplodvorSourceCommand extends Command
{
    protected $signature = 'supplier:seed-tsarpechi-teplodvor-source {--apply}';

    protected $description = 'Link Царь-Печи products to their teplodvor.by page via supplier_products.source_url';

    private const BASE_HEATING = 'https://www.teplodvor.by/shop/kotly/otopitelnye-pechi/';
    private const BASE_BATH    = 'https://www.teplodvor.by/shop/pech-dlya-bani/';

    /** @var array<int,string> product id => teplodvor slug (relative to its category base) */
    private const MAP = [
        // ── Банные (pech-dlya-bani) ──────────────────────────────────────────
        9906 => 'BATH|pech-dlya-bani-tsar-pechi-vasilisa-8-mm/',
        5312 => 'BATH|pech-dlya-bani-tsar-pechi-gorynya-8-mm/',
        9907 => 'BATH|pech-dlya-bani-tsar-pechi-dobrynya-8-mm/',
        5313 => 'BATH|pech-dlya-bani-tsar-pechi-zabava-8-mm/',
        5887 => 'BATH|pech-dlya-bani-tsar-pechi-zateya-8-mm/',
        9908 => 'BATH|pech-dlya-bani-tsar-pechi-lyubanya-8-mm/',
        9946 => 'BATH|pech-dlya-bani-tsar-pechi-volga-8-mm/',
        9948 => 'BATH|pech-dlya-bani-tsar-pechi-elisey-8-mm/',
        9949 => 'BATH|pech-dlya-bani-tsar-pechi-emelya-mini/',
        9950 => 'BATH|pech-dlya-bani-tsar-pechi-emelya-plyus/',
        9951 => 'BATH|pech-dlya-bani-tsar-pechi-emelya/',
        9952 => 'BATH|pech-dlya-bani-tsar-pechi-lyubanya-komfort-8-mm/',
        9958 => 'BATH|pech-dlya-bani-tsar-pechi-volga-premium-8-mm/',
        9959 => 'BATH|pech-dlya-bani-tsar-pechi-gorynya-premium-8-mm/',
        9960 => 'BATH|pech-dlya-bani-tsar-pechi-dobrynya-premium/',
        9961 => 'BATH|pech-dlya-bani-tsar-pechi-elisey-premium-8-mm/',
        9962 => 'BATH|pech-dlya-bani-tsar-pechi-emelya-premium/',
        9963 => 'BATH|pech-dlya-bani-tsar-pechi-zabava-premium-8-mm/',
        // ── Отопительные (otopitelnye-pechi) ─────────────────────────────────
        9929 => 'HEAT|otopitelnaya-pech-tsar-pechi-burzhuyka-mini-s-konforkoj/',
        9930 => 'HEAT|otopitelnaya-pech-tsar-pechi-burzhuyka-mini/',
        9931 => 'HEAT|otopitelnaya-pech-tsar-pechi-burzhuyka-s-konforkoy/',
        9932 => 'HEAT|otopitelnaya-pech-tsar-pechi-burzhuyka/',
        5624 => 'HEAT|otopitelnaya-pech-tsar-pechi-zlata/',
        5797 => 'HEAT|otopitelnaya-pech-tsar-pechi-zolovka-8-mm/',
        9933 => 'HEAT|otopitelnaya-pech-tsar-pechi-matryoshka-bolshaya-1-chds/',
        9935 => 'HEAT|otopitelnaya-pech-tsar-pechi-matryoshka-bolshaya1/',
        9934 => 'HEAT|otopitelnaya-pech-tsar-pechi-matryoshka-bolshaya-2-chds/',
        9936 => 'HEAT|otopitelnaya-pech-tsar-pechi-matryoshka-bolshaya-2/',
        9937 => 'HEAT|otopitelnaya-pech-tsar-pechi-matryoshka-malaya-1-chds/',
        5321 => 'HEAT|otopitelnaya-pech-tsar-pechi-matryoshka-malaya-1/',
        9938 => 'HEAT|otopitelnaya-pech-tsar-pechi-matryoshka-malaya-2-chds/',
        7224 => 'HEAT|otopitelnaya-pech-tsar-pechi-matryoshka-malaya-2/',
        8881 => 'HEAT|otopitelnaya-pech-tsar-pechi-milana/',
        9939 => 'HEAT|pech-otopitelnaya-czar-pechi-yarilo-dekor-8-mm/',
        9940 => 'HEAT|pech-otopitelnaya-czar-pechi-yarilo-8-mm/',
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

        foreach (self::MAP as $id => $encoded) {
            [$cat, $slug] = explode('|', $encoded, 2);
            $base = $cat === 'BATH' ? self::BASE_BATH : self::BASE_HEATING;
            $url = $base . $slug;

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
