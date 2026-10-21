<?php

namespace App\Console\Commands;

use App\Services\PriceList\BelomoPriceList;
use App\Services\SupplierProductAvailabilityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SyncBelomoPriceListCommand extends Command
{
    private const MINIMUM_RETAIL_MARKUP_PERCENT = 20.0;

    protected $signature = 'supplier:sync-belomo-pricelist
        {--apply : Записать поставщика, оптовые цены и связи}
        {--dry-run : Только аудит без записи}
        {--complete-cards : Заполнить и активировать карточки, используя безопасную розничную цену и изображения той же серии}
        {--sync-retail-prices : Рассчитать розницу из оптовой цены с НДС и наценкой 20%}
        {--archive-artifacts : Перенести полезные связи и архивировать карточки БелОМО, которых нет в прайсе}
        {--report= : Путь CSV-отчёта}';

    protected $description = 'Import the BelOMO wholesale price list and calculate retail with VAT and a 20% markup.';

    private const SUPPLIER_CODE = 'sanbusinessgroup';

    private const SYNC_KEY = 'belomo_static_pricelist';

    public function handle(BelomoPriceList $priceList, SupplierProductAvailabilityService $availability): int
    {
        $apply = (bool) $this->option('apply') && ! (bool) $this->option('dry-run');
        $rows = $priceList->rows();
        $supplierId = (int) (DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id') ?? 0);
        $classified = $this->classify($rows, $priceList, $supplierId);
        $artifacts = $this->catalogArtifacts($classified);
        $retail = (bool) $this->option('sync-retail-prices')
            ? $this->wholesaleRetailPlan($classified)
            : ['rows' => [], 'metrics' => []];
        $reportPath = $this->writeReport($classified, $retail['rows'], $artifacts);
        $counts = collect($classified)->countBy('status');
        $quality = $this->qualityMetrics($classified);
        $quality['artifact_extra'] = count($artifacts);

        $this->line($apply ? 'APPLY: закупочный прайс БелОМО будет привязан к поставщику и складу ООО «СанБизнесГруп».' : 'DRY RUN: база не изменяется.');
        $this->line('metric.belomo.price_rows='.count($rows));
        $this->line('metric.belomo.matched='.$counts->get('matched', 0));
        $this->line('metric.belomo.create='.$counts->get('create', 0));
        $this->line('metric.belomo.ambiguous='.$counts->get('ambiguous', 0));
        foreach ($quality as $metric => $value) {
            $this->line('metric.belomo.'.$metric.'='.$value);
        }
        if ((bool) $this->option('complete-cards')) {
            $completion = $this->completionPlan($classified);
            foreach ($completion['metrics'] as $metric => $value) {
                $this->line('metric.belomo.completion_'.$metric.'='.$value);
            }
        }
        foreach ($retail['metrics'] as $metric => $value) {
            $this->line('metric.belomo.retail_'.$metric.'='.$value);
        }
        $this->line('metric.belomo.report='.$reportPath);

        if (! $apply) {
            return self::SUCCESS;
        }

        try {
            $now = now();
            $supplierId = $this->ensureSupplier($now);
            $syncId = $this->ensureSync($now);
            $sourceId = $this->ensureB2bSource($supplierId, $now);
            $brandId = $this->brandId();
            $categoryId = $this->categoryId();
            $touched = [];
            $created = 0;
            $applied = 0;

            DB::transaction(function () use ($classified, $supplierId, $syncId, $sourceId, $brandId, $categoryId, $now, &$touched, &$created, &$applied): void {
                foreach ($classified as $item) {
                    if ($item['status'] === 'ambiguous') {
                        continue;
                    }
                    $product = $item['product'];
                    if ($product === null) {
                        $product = $this->createDraft($item['row'], $brandId, $categoryId, $now);
                        $created++;
                    }
                    $this->upsertSupplierProduct($supplierId, $syncId, $product, $item['row'], $now);
                    $this->upsertPartnerOffer($sourceId, $product, $item['row'], $now);
                    $touched[] = (int) $product->id;
                    $applied++;
                }
            });

            $availability->refreshMany($touched, $now);

            if ((bool) $this->option('complete-cards')) {
                $fresh = $this->classify($rows, $priceList, $supplierId);
                $completion = $this->completionPlan($fresh);
                $this->applyCompletionPlan($completion['rows'], $now);
                $availability->refreshMany(array_column($completion['rows'], 'product_id'), $now);
                $this->info(sprintf(
                    'Карточки: активировано %d; отсутствующие цены рассчитаны с безопасной маржой %d; изображения серии добавлены %d.',
                    $completion['metrics']['activate'],
                    $completion['metrics']['price_update'],
                    $completion['metrics']['image_copy'],
                ));
            }

            if ((bool) $this->option('sync-retail-prices')) {
                $fresh = $this->classify($rows, $priceList, $supplierId);
                $retail = $this->wholesaleRetailPlan($fresh);
                $this->applyRetailPlan($retail['rows'], $now);
                $this->info(sprintf(
                    'Розница: рассчитано от оптовой цены с НДС и наценкой 20%% — %d; изменено %d; без изменений %d.',
                    $retail['metrics']['wholesale'],
                    $retail['metrics']['update'],
                    $retail['metrics']['unchanged'],
                ));
            }

            if ((bool) $this->option('archive-artifacts')) {
                $fresh = $this->classify($rows, $priceList, $supplierId);
                $cleanup = $this->archiveCatalogArtifacts($this->catalogArtifacts($fresh), $fresh, $priceList, $now);
                $this->info(sprintf(
                    'Артефакты: архивировано %d; связей поставщиков перенесено %d.',
                    $cleanup['archived'],
                    $cleanup['links_reassigned'],
                ));
                $this->line('metric.belomo.artifacts_archived='.$cleanup['archived']);
                $this->line('metric.belomo.artifact_links_reassigned='.$cleanup['links_reassigned']);
            }

            DB::table('supplier_sources')->where('supplier_id', $supplierId)->where('name', 'Прайс БелОМО 15.09.2026')->update(['last_run_at' => $now, 'updated_at' => $now]);
            DB::table('supplier_syncs')->where('id', $syncId)->update(['last_run_at' => $now, 'updated_at' => $now]);
            $this->info("Применено {$applied}; создано неактивных черновиков {$created}.");
            $this->line('metric.belomo.applied='.$applied);
            $this->line('metric.belomo.created='.$created);
        } catch (\Throwable $exception) {
            $safeMessage = preg_replace('/\s+/u', ' ', $exception->getMessage()) ?? 'unknown';
            $safeMessage = preg_replace('/\([^)]*(?:password|secret|token|email)[^)]*\)/iu', '(redacted)', $safeMessage) ?? $safeMessage;
            $this->line('metric.belomo.failure_class='.class_basename($exception));
            $this->line('metric.belomo.failure='.Str::limit($safeMessage, 500, ''));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function classify(array $rows, BelomoPriceList $priceList, int $supplierId): array
    {
        $links = DB::table('supplier_products as sp')
            ->join('products as p', 'p.id', '=', 'sp.product_id')
            ->whereNotNull('sp.product_id')
            ->get(['sp.supplier_id', 'sp.supplier_article', 'sp.supplier_article_normalized', 'sp.supplier_article_compact', 'p.*']);

        return array_map(function (array $row) use ($links, $priceList, $supplierId): array {
            $compact = $priceList->compactArticle($row['article']);
            $short = $priceList->shortArticle($row['article']);
            $candidates = $links->filter(function (object $link) use ($compact, $short, $priceList): bool {
                $values = array_filter([
                    $link->supplier_article ?? null,
                    $link->supplier_article_normalized ?? null,
                    $link->supplier_article_compact ?? null,
                ]);
                foreach ($values as $value) {
                    $candidate = $priceList->compactArticle((string) $value);
                    if ($candidate === $compact || ($candidate === $short && $short !== '')) {
                        return true;
                    }
                }

                return false;
            })->unique('id');

            if ($candidates->count() > 1) {
                $direct = $candidates->where('supplier_id', $supplierId)->first();
                if ($direct) {
                    $candidates = collect([$direct]);
                }
            }

            return [
                'row' => $row,
                'status' => $candidates->count() === 1 ? 'matched' : ($candidates->count() > 1 ? 'ambiguous' : 'create'),
                'product' => $candidates->first(),
            ];
        }, $rows);
    }

    private function writeReport(array $classified, array $retailRows = [], array $artifacts = []): string
    {
        $retailByProduct = collect($retailRows)->keyBy('product_id');
        $path = trim((string) $this->option('report')) ?: storage_path('app/reports/suppliers/belomo/current-audit.csv');
        File::ensureDirectoryExists(dirname($path));
        $handle = fopen($path, 'wb');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'status', 'article', 'name', 'price_without_vat_byn', 'partner_price_with_vat_byn',
            'product_id', 'sku', 'retail_price_byn', 'margin_byn', 'is_active',
            'images_count', 'has_description', 'specs_count', 'card_ready',
            'target_retail_price_byn', 'retail_price_method', 'derived_markup_percent',
            'minimum_retail_price_byn', 'minimum_markup_applied',
        ], ';');
        foreach ($classified as $item) {
            $product = $item['product'];
            $retail = $product ? $retailByProduct->get((int) $product->id) : null;
            fputcsv($handle, [
                $item['status'], $item['row']['article'], $item['row']['name'],
                number_format($item['row']['partner_price_byn'], 2, '.', ''),
                number_format($this->priceWithVat($item['row']['partner_price_byn']), 2, '.', ''),
                $product->id ?? '', $product->sku ?? '', isset($product->price) ? number_format((float) $product->price, 2, '.', '') : '',
                isset($product->price) ? number_format((float) $product->price - $this->priceWithVat($item['row']['partner_price_byn']), 2, '.', '') : '',
                $product ? ((bool) $product->is_active ? '1' : '0') : '',
                $product ? count($this->jsonArray($product->images ?? null)) : 0,
                $product && (filled($product->short_description ?? null) || filled($product->content ?? null)) ? '1' : '0',
                $product ? count($this->jsonArray($product->specs ?? null)) : 0,
                $this->cardIsReady($product) ? '1' : '0',
                $retail ? number_format((float) $retail['target_price'], 2, '.', '') : '',
                $retail['method'] ?? '',
                isset($retail['markup_percent']) ? number_format((float) $retail['markup_percent'], 2, '.', '') : '',
                isset($retail['minimum_price']) ? number_format((float) $retail['minimum_price'], 2, '.', '') : '',
                ! empty($retail['minimum_floor_applied']) ? '1' : '0',
            ], ';');
        }
        foreach ($artifacts as $product) {
            fputcsv($handle, [
                'artifact_extra', '', $product->name, '', '', $product->id, $product->sku,
                number_format((float) $product->price, 2, '.', ''), '', (bool) $product->is_active ? '1' : '0',
                count($this->jsonArray($product->images ?? null)),
                (filled($product->short_description ?? null) || filled($product->content ?? null)) ? '1' : '0',
                count($this->jsonArray($product->specs ?? null)), '0', '', '', '', '', '',
            ], ';');
        }
        fclose($handle);

        return $path;
    }

    private function ensureSupplier($now): int
    {
        $existing = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->first();
        $priceListNote = 'БелОМО — производитель и источник закупочного прайса счетчиков. Поставщик и склад для заказов — ООО «СанБизнесГруп». Розница рассчитывается как цена без НДС + НДС 20% + наценка 20%.';
        if ($existing) {
            $notes = trim((string) $existing->notes);
            if (! str_contains($notes, $priceListNote)) {
                $notes = trim($notes.' '.$priceListNote);
            }
            DB::table('suppliers')->where('id', $existing->id)->update([
                'name' => 'ООО «СанБизнесГруп»', 'currency' => 'BYN', 'currency_rate' => 1,
                'notes' => $notes, 'is_active' => true, 'updated_at' => $now,
            ]);
        } else {
            DB::table('suppliers')->insert([
                'code' => self::SUPPLIER_CODE, 'name' => 'ООО «СанБизнесГруп»',
                'currency' => 'BYN', 'currency_rate' => 1, 'notes' => $priceListNote,
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $id = (int) DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id');
        DB::table('supplier_sources')->updateOrInsert(['supplier_id' => $id, 'name' => 'Прайс БелОМО 15.09.2026'], [
            'type' => 'csv',
            'config' => json_encode(['document' => 'Цены БелОМО.pdf', 'effective_date' => '2026-09-15', 'vat_included' => false, 'price_column' => 'Цена за единицу до 10 шт', 'purchase_mode' => 'procurement'], JSON_UNESCAPED_UNICODE),
            'column_mappings' => json_encode(['article' => 'article', 'name' => 'name', 'price' => 'partner_price_byn'], JSON_UNESCAPED_UNICODE),
            'schedule_cron' => 'Вручную при получении нового прайса', 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return $id;
    }

    private function ensureSync($now): int
    {
        DB::table('supplier_syncs')->updateOrInsert(['key' => self::SYNC_KEY], [
            'supplier_id' => DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id'),
            'name' => 'ООО «СанБизнесГруп»', 'code' => self::SUPPLIER_CODE, 'title' => 'СанБизнесГруп: закупочный прайс БелОМО',
            'description' => 'БелОМО — производитель и источник закупочной цены. Операционный поставщик и склад — ООО «СанБизнесГруп».',
            'command' => 'supplier:sync-belomo-pricelist', 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return (int) DB::table('supplier_syncs')->where('key', self::SYNC_KEY)->value('id');
    }

    private function ensureB2bSource(int $supplierId, $now): int
    {
        $categoryId = $this->categoryId();
        DB::table('integration_sources')->updateOrInsert(['code' => 'belomo_wholesale'], [
            'supplier_id' => $supplierId, 'name' => 'СанБизнесГруп · закупочный прайс БелОМО', 'driver' => 'static_pricelist',
            'price_currency' => 'BYN', 'price_currency_rate' => 1, 'is_active' => true,
            'create_products' => false, 'update_prices' => true, 'update_stock' => false,
            'settings' => json_encode([
                'price_tax_mode' => 'exclusive', 'vat_rate' => 20, 'b2b_enabled' => true,
                'b2b_price_role' => 'sale',
                'b2b_category_ids' => [$categoryId], 'partner_name' => 'ООО «СанБизнесГруп»',
                'manufacturer' => 'БелОМО', 'procurement_source' => 'БелОМО',
                'procurement_mode' => true, 'warehouse_label' => 'ООО «СанБизнесГруп»',
                'stock_validity_minutes' => 525600,
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return (int) DB::table('integration_sources')->where('code', 'belomo_wholesale')->value('id');
    }

    private function upsertSupplierProduct(int $supplierId, int $syncId, object $product, array $row, $now): void
    {
        $compact = mb_strtolower(preg_replace('/[^\pL\pN]+/u', '', $row['article']) ?? '');
        $identity = ['supplier_id' => $supplierId, 'supplier_article' => $row['article']];
        $existing = DB::table('supplier_products')->where($identity)->first();
        $payload = [
            'supplier_sync_id' => $syncId, 'product_id' => $product->id, 'product_sku' => $product->sku,
            'supplier_article_normalized' => mb_strtolower($row['article']), 'supplier_article_compact' => $compact,
            'supplier_name' => $row['name'], 'price' => $row['partner_price_byn'], 'currency' => 'BYN', 'currency_rate' => 1,
            'price_byn' => $row['partner_price_byn'], 'warehouse_name' => 'ООО «СанБизнесГруп»',
            'match_status' => 'matched', 'match_confidence' => '100',
            'raw' => json_encode(['manufacturer' => 'БелОМО', 'procurement_source' => 'БелОМО', 'supplier' => 'ООО «СанБизнесГруп»', 'vat_included' => false, 'price_column' => 'до 10 шт', 'effective_date' => '2026-09-15'], JSON_UNESCAPED_UNICODE),
            'last_synced_at' => $now, 'updated_at' => $now,
        ];

        if ($existing) {
            // The BelOMO file is a purchase-price source. It must never erase
            // real SanBusinessGroup stock populated by 1C or a warehouse feed.
            DB::table('supplier_products')->where('id', $existing->id)->update($payload);

            return;
        }

        DB::table('supplier_products')->insert(array_merge($identity, $payload, [
            'in_stock' => false, 'stock_quantity' => null,
            'stock_status' => 'preorder', 'stock_text' => 'Закупка у производителя · наличие и срок по запросу',
            'created_at' => $now,
        ]));
    }

    private function upsertPartnerOffer(int $sourceId, object $product, array $row, $now): void
    {
        DB::table('integration_products')->updateOrInsert(['integration_source_id' => $sourceId, 'external_id' => $row['article']], [
            'product_id' => $product->id, 'target_category_id' => $product->category_id, 'external_code' => $row['article'],
            'external_sku' => $row['article'], 'name' => $row['name'], 'price' => $row['partner_price_byn'],
            'price_currency' => 'BYN', 'price_currency_rate' => 1, 'price_tax_mode' => 'exclusive', 'price_vat_rate' => 20,
            'price_byn' => $this->priceWithVat($row['partner_price_byn']), 'stock_quantity' => 0,
            'match_status' => 'matched', 'match_method' => 'belomo_article', 'match_confidence' => 1,
            'payload' => json_encode(['manufacturer' => 'БелОМО', 'procurement_source' => 'БелОМО', 'supplier' => 'ООО «СанБизнесГруп»', 'procurement_mode' => true, 'vat_included' => false, 'source_document' => 'Цены БелОМО.pdf'], JSON_UNESCAPED_UNICODE),
            'matched_at' => $now, 'last_seen_at' => $now, 'last_offer_seen_at' => $now,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function createDraft(array $row, int $brandId, int $categoryId, $now): object
    {
        $name = $row['name'];
        $id = (int) DB::table('products')->insertGetId([
            'category_id' => $categoryId, 'brand_id' => $brandId, 'name' => $name, 'h1' => $name,
            'sku' => $this->nextSku(), 'slug' => $this->uniqueSlug($name.' '.$row['article']), 'price' => 0, 'currency' => 'BYN',
            'short_description' => 'Черновик из прямого оптового прайса БелОМО.',
            'specs' => json_encode(array_filter(['Артикул производителя' => $row['article'], 'Межосевое расстояние' => $row['center_distance_mm'] ? $row['center_distance_mm'].' мм' : null, 'Направление потока' => $row['flow_direction']]), JSON_UNESCAPED_UNICODE),
            'images' => json_encode([]), 'unit' => 'шт', 'is_active' => false, 'is_archived' => false,
            'in_stock' => false, 'availability_status' => 'check', 'stock_qty' => null, 'is_new' => true,
            'meta_title' => $name, 'meta_description' => $name, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return DB::table('products')->where('id', $id)->firstOrFail();
    }

    private function brandId(): int
    {
        $id = DB::table('brands')->where('slug', 'belomo')->value('id');
        if (! $id) {
            throw new \RuntimeException('Не найден бренд БелОМО.');
        }

        return (int) $id;
    }

    private function categoryId(): int
    {
        $id = DB::table('categories')->where('slug', 'schetchiki-gaza')->value('id');
        if (! $id) {
            throw new \RuntimeException('Не найдена категория «Счетчики газа».');
        }

        return (int) $id;
    }

    private function nextSku(): string
    {
        $next = ((int) DB::table('products')->where('sku', 'like', 'KOTLOV-%')->pluck('sku')->map(fn ($sku) => preg_match('/^KOTLOV-(\d+)$/', (string) $sku, $m) ? (int) $m[1] : 0)->max()) + 1;
        do {
            $sku = sprintf('KOTLOV-%06d', $next++);
        } while (DB::table('products')->where('sku', $sku)->exists());

        return $sku;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'belomo-gas-meter';
        $slug = $base;
        $i = 2;
        while (DB::table('products')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function priceWithVat(float $priceWithoutVat): float
    {
        return round($priceWithoutVat * 1.20, 2);
    }

    /** @return array<int, object> */
    private function catalogArtifacts(array $classified): array
    {
        $canonicalIds = collect($classified)
            ->pluck('product.id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        return DB::table('products')
            ->where('brand_id', $this->brandId())
            ->where('is_archived', false)
            ->when($canonicalIds !== [], fn ($query) => $query->whereNotIn('id', $canonicalIds))
            ->orderBy('id')
            ->get()
            ->all();
    }

    /** @return array{archived:int, links_reassigned:int} */
    private function archiveCatalogArtifacts(array $artifacts, array $classified, BelomoPriceList $priceList, $now): array
    {
        $canonical = collect($classified)
            ->filter(fn (array $item): bool => $item['product'] !== null && $item['status'] === 'matched')
            ->values();
        $archived = 0;
        $linksReassigned = 0;

        DB::transaction(function () use ($artifacts, $canonical, $priceList, $now, &$archived, &$linksReassigned): void {
            foreach ($artifacts as $artifact) {
                $links = DB::table('supplier_products')->where('product_id', $artifact->id)->get();
                foreach ($links as $link) {
                    $target = $canonical->first(function (array $item) use ($link, $priceList): bool {
                        return $priceList->compactArticle((string) $link->supplier_article) === $priceList->compactArticle($item['row']['article'])
                            || $priceList->shortArticle((string) $link->supplier_article) === $priceList->shortArticle($item['row']['article']);
                    });
                    if (! $target) {
                        continue;
                    }
                    DB::table('supplier_products')->where('id', $link->id)->update([
                        'product_id' => $target['product']->id,
                        'product_sku' => $target['product']->sku,
                        'updated_at' => $now,
                    ]);
                    DB::table('supplier_product_mappings')
                        ->where('product_id', $artifact->id)
                        ->where('supplier_article', $link->supplier_article)
                        ->update([
                            'product_id' => $target['product']->id,
                            'product_sku' => $target['product']->sku,
                            'updated_at' => $now,
                        ]);
                    $linksReassigned++;
                }

                DB::table('products')->where('id', $artifact->id)->update([
                    'is_active' => false,
                    'is_archived' => true,
                    'in_stock' => false,
                    'stock_qty' => null,
                    'availability_status' => 'out_of_stock',
                    'updated_at' => $now,
                ]);
                $archived++;
            }
        });

        return ['archived' => $archived, 'links_reassigned' => $linksReassigned];
    }

    /** @return array{rows: array<int, array<string, mixed>>, metrics: array<string, int>} */
    private function wholesaleRetailPlan(array $classified): array
    {
        $plans = [];
        $metrics = ['wholesale' => 0, 'update' => 0, 'unchanged' => 0];

        foreach ($classified as $item) {
            if ($item['product'] === null || $item['status'] !== 'matched') {
                continue;
            }

            $plan = $this->retailPlanRow(
                $item,
                'wholesale_minimum_markup_20',
            );
            $plans[] = $plan;
            $metrics['wholesale']++;
            $metrics[$plan['changed'] ? 'update' : 'unchanged']++;
        }

        return ['rows' => $plans, 'metrics' => $metrics];
    }

    private function applyRetailPlan(array $rows, $now): void
    {
        DB::transaction(function () use ($rows, $now): void {
            foreach ($rows as $row) {
                if (! $row['changed']) {
                    continue;
                }
                DB::table('products')->where('id', $row['product_id'])->update([
                    'price' => $row['target_price'],
                    'currency' => 'BYN',
                    'updated_at' => $now,
                ]);
            }
        });
    }

    private function retailPlanRow(array $item, string $method): array
    {
        $current = (float) ($item['product']->price ?? 0);
        $minimumPrice = $this->minimumRetailPrice((float) $item['row']['partner_price_byn']);
        $minimumFloorApplied = $current < $minimumPrice;
        $target = max($current, $minimumPrice);

        return [
            'product_id' => (int) $item['product']->id,
            'article' => $item['row']['article'],
            'current_price' => $current,
            'target_price' => $target,
            'method' => $method,
            'markup_percent' => self::MINIMUM_RETAIL_MARKUP_PERCENT,
            'minimum_price' => $minimumPrice,
            'minimum_floor_applied' => $minimumFloorApplied,
            'changed' => abs($current - $target) >= 0.01,
        ];
    }

    private function minimumRetailPrice(float $priceWithoutVat): float
    {
        $costWithVat = $this->priceWithVat($priceWithoutVat);

        return ceil(($costWithVat * (1 + self::MINIMUM_RETAIL_MARKUP_PERCENT / 100)) * 100) / 100;
    }

    /** @return array{rows: array<int, array<string, mixed>>, metrics: array<string, int>} */
    private function completionPlan(array $classified): array
    {
        $minimumMargin = max(0.0, min(99.0, (float) config('shop.order_management.minimum_margin_percent', 10)));
        $donors = collect($classified)
            ->filter(fn (array $item): bool => $item['product'] !== null
                && count($this->jsonArray($item['product']->images ?? null)) > 0)
            ->values();

        $metrics = [
            'rows' => 0,
            'activate' => 0,
            'price_update' => 0,
            'image_copy' => 0,
            'description_update' => 0,
            'blocked_no_image' => 0,
        ];
        $plan = [];

        foreach ($classified as $item) {
            $product = $item['product'];
            if ($product === null || $item['status'] !== 'matched') {
                continue;
            }

            $currentImages = $this->jsonArray($product->images ?? null);
            $donor = $currentImages === [] ? $this->bestImageDonor($item, $donors->all()) : null;
            $images = $currentImages !== [] ? $currentImages : $this->jsonArray($donor['product']->images ?? null);
            $needsDescription = (! filled($product->content ?? null) && ! filled($product->short_description ?? null))
                || trim((string) ($product->short_description ?? '')) === 'Черновик из прямого оптового прайса БелОМО.';
            $safeRetail = $this->safeRetailPrice((float) $item['row']['partner_price_byn'], $minimumMargin);
            $currentRetail = (float) ($product->price ?? 0);
            // Elicon remains the owner of every retail price it publishes.
            // The calculated margin-safe price is used only where Elicon has
            // no price at all; existing retail prices are never overwritten.
            $targetRetail = $currentRetail > 0 ? $currentRetail : $safeRetail;
            $canActivate = $images !== [] && $targetRetail > 0;

            $row = [
                'product_id' => (int) $product->id,
                'article' => $item['row']['article'],
                'name' => $item['row']['name'],
                'price' => $targetRetail,
                'price_changed' => abs($targetRetail - $currentRetail) >= 0.01,
                'images' => $images,
                'image_changed' => $currentImages === [] && $images !== [],
                'description_changed' => $needsDescription,
                'activate' => ! (bool) ($product->is_active ?? false) && $canActivate,
                'can_activate' => $canActivate,
                'specs' => $this->completionSpecs($item['row'], $product->specs ?? null),
            ];
            $plan[] = $row;
            $metrics['rows']++;
            if ($row['activate']) {
                $metrics['activate']++;
            }
            if ($row['price_changed']) {
                $metrics['price_update']++;
            }
            if ($row['image_changed']) {
                $metrics['image_copy']++;
            }
            if ($row['description_changed']) {
                $metrics['description_update']++;
            }
            if (! $canActivate) {
                $metrics['blocked_no_image']++;
            }
        }

        return ['rows' => $plan, 'metrics' => $metrics];
    }

    private function applyCompletionPlan(array $rows, $now): void
    {
        DB::transaction(function () use ($rows, $now): void {
            foreach ($rows as $row) {
                $update = [
                    'price' => $row['price'],
                    'specs' => json_encode($row['specs'], JSON_UNESCAPED_UNICODE),
                    'updated_at' => $now,
                ];

                if ($row['image_changed']) {
                    $update['images'] = json_encode($row['images'], JSON_UNESCAPED_UNICODE);
                }
                if ($row['description_changed']) {
                    $update['short_description'] = $row['name'].'. Поставка под заказ через ООО «СанБизнесГруп»; наличие и срок подтверждает менеджер.';
                    $update['content'] = '<p>'.$row['name'].' производства БелОМО. Поставщик и склад — ООО «СанБизнесГруп». Наличие, срок поставки и комплектацию подтвердит менеджер перед оформлением.</p><p>Изображение показывает соответствующую серию счетчика; точное исполнение определяется артикулом производителя.</p>';
                    $update['meta_title'] = $row['name'].' купить в %city%';
                    $update['meta_description'] = $row['name'].' БелОМО — цена с НДС, поставка под заказ.';
                }
                if ($row['can_activate']) {
                    $update['is_active'] = true;
                    $update['is_archived'] = false;
                }
                if ($row['activate']) {
                    $update['availability_status'] = 'check';
                    $update['in_stock'] = false;
                    $update['stock_qty'] = null;
                }

                DB::table('products')->where('id', $row['product_id'])->update($update);
            }
        });
    }

    private function safeRetailPrice(float $priceWithoutVat, float $minimumMargin): float
    {
        $costWithVat = $this->priceWithVat($priceWithoutVat);
        $divisor = 1 - ($minimumMargin / 100);

        return ceil(($costWithVat / $divisor) * 100) / 100;
    }

    private function bestImageDonor(array $target, array $donors): ?array
    {
        $family = $this->articleFamily($target['row']['article']);
        $targetTokens = $this->modelTokens($target['row']['name']);
        $best = null;
        $bestScore = -1;

        foreach ($donors as $donor) {
            if ($this->articleFamily($donor['row']['article']) !== $family) {
                continue;
            }
            $shared = count(array_intersect($targetTokens, $this->modelTokens($donor['row']['name'])));
            $score = $shared * 100 - abs($this->articleSuffix($target['row']['article']) - $this->articleSuffix($donor['row']['article']));
            if ($score > $bestScore) {
                $best = $donor;
                $bestScore = $score;
            }
        }

        return $best;
    }

    private function articleFamily(string $article): string
    {
        return explode('.', $article, 2)[0];
    }

    private function articleSuffix(string $article): int
    {
        return preg_match('/-(\d+)$/', $article, $match) ? (int) $match[1] : 0;
    }

    /** @return array<int, string> */
    private function modelTokens(string $name): array
    {
        $normalized = mb_strtolower(preg_replace('/[^\pL\pN]+/u', ' ', $name) ?? '');

        return array_values(array_unique(array_filter(preg_split('/\s+/u', trim($normalized)) ?: [], fn (string $token): bool => mb_strlen($token) > 1)));
    }

    private function completionSpecs(array $row, mixed $current): array
    {
        $specs = $this->jsonArray($current);
        $specs['Производитель'] = 'БелОМО';
        $specs['Артикул производителя'] = $row['article'];
        $specs['Тип счетчика'] = str_contains(mb_strtolower($row['name']), 'ультразвуковой') ? 'Ультразвуковой' : 'Диафрагменный';
        if (preg_match('/G\s*([0-9]+(?:[,.][0-9]+)?)/iu', $row['name'], $match)) {
            $specs['Типоразмер'] = 'G'.str_replace('.', ',', $match[1]);
        }
        if (! empty($row['center_distance_mm'])) {
            $specs['Межосевое расстояние'] = $row['center_distance_mm'].' мм';
        }
        if (! empty($row['flow_direction'])) {
            $specs['Направление потока'] = $row['flow_direction'];
        }

        return $specs;
    }

    private function cardIsReady(?object $product): bool
    {
        return $product !== null
            && (bool) ($product->is_active ?? false)
            && ! (bool) ($product->is_archived ?? false)
            && (float) ($product->price ?? 0) > 0
            && count($this->jsonArray($product->images ?? null)) > 0
            && (filled($product->short_description ?? null) || filled($product->content ?? null));
    }

    /** @return array<string, int> */
    private function qualityMetrics(array $classified): array
    {
        $metrics = ['card_ready' => 0, 'inactive' => 0, 'retail_missing' => 0, 'images_missing' => 0, 'description_missing' => 0, 'margin_conflict' => 0];
        foreach ($classified as $item) {
            $product = $item['product'];
            if ($this->cardIsReady($product)) {
                $metrics['card_ready']++;
            }
            if (! $product || ! (bool) ($product->is_active ?? false)) {
                $metrics['inactive']++;
            }
            if (! $product || (float) ($product->price ?? 0) <= 0) {
                $metrics['retail_missing']++;
            }
            if (! $product || count($this->jsonArray($product->images ?? null)) === 0) {
                $metrics['images_missing']++;
            }
            if (! $product || (! filled($product->short_description ?? null) && ! filled($product->content ?? null))) {
                $metrics['description_missing']++;
            }
            if ($product && (float) ($product->price ?? 0) > 0 && (float) $product->price < $this->minimumRetailPrice((float) $item['row']['partner_price_byn'])) {
                $metrics['margin_conflict']++;
            }
        }

        return $metrics;
    }

    private function jsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
