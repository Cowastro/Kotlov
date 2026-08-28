<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One-off: link the remaining Теплодар products that genuinely lack photos
 * (images=0, generic 192-char placeholder content — see
 * debug:brand-stats --brand=Теплодар --specs-check) to their real
 * teplodvor.by page, hand-verified.
 *
 * Most of brand Теплодар is already well-enriched; automatic matching
 * (supplier:enrich-teplodvor --brand=Теплодар) only found 5/88 and one of
 * those was WRONG — door-type codes ("ДС"/"ДЧ") are stripped as stopwords
 * in the shared matcher, so "ТОП-200 ДЧ" silently matched the "ТОП-200 ДС"
 * page. Rather than loosen the shared heuristic further (used by every
 * other brand) this is a small, hand-checked list instead — 8 products,
 * covering the genuine gap. The rest of the "missing" query hits are
 * accessories (baks, ТЭНы, гидроразделители, КУППЕР СПУТНИК эл. котлы) not
 * addressed here — out of scope for this pass.
 *
 *   php artisan supplier:seed-teplodar-teplodvor-source            # dry run
 *   php artisan supplier:seed-teplodar-teplodvor-source --apply
 */
class SeedTeplodarTeplodvorSourceCommand extends Command
{
    protected $signature = 'supplier:seed-teplodar-teplodvor-source {--apply}';

    protected $description = 'Link the 8 Теплодар products missing photos to their teplodvor.by page';

    private const BASE = 'https://www.teplodvor.by/shop/kotly/otopitelnye-pechi/';
    private const BASE_BATH = 'https://www.teplodvor.by/shop/pech-dlya-bani/';

    /** @var array<int,string> product id => teplodvor slug (BASE-relative unless prefixed BATH|) */
    private const MAP = [
        16843 => 'pech-otopitelno-varochnaya-teplodar-pechurka/',
        16844 => 'pech-otopitelno-varochnaya-teplodar-pechurka-plyus/',
        16845 => 'otopitelnaya-pech-teplodar-top-140-ds/',
        16846 => 'otopitelnaya-pech-teplodar-top-140-dch/',
        16847 => 'otopitelnaya-pech-teplodar-top-200-ds/',
        16849 => 'otopitelnaya-pech-teplodar-top-300-dch/',
        16906 => 'BATH|pech-dlya-bani-teplodar-rus-12-lu/',
        16925 => 'BATH|pech-dlya-bani-teplodar-bylina-18ch-panorama-13/',
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

        foreach (self::MAP as $id => $slug) {
            $url = str_starts_with($slug, 'BATH|')
                ? self::BASE_BATH . substr($slug, 5)
                : self::BASE . $slug;

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
