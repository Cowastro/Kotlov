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
    protected $signature = 'supplier:sync-belomo-pricelist
        {--apply : Записать поставщика, оптовые цены и связи}
        {--dry-run : Только аудит без записи}
        {--report= : Путь CSV-отчёта}';

    protected $description = 'Import the static BelOMO wholesale price list without VAT, preserving Elicon retail prices.';

    private const SUPPLIER_CODE = 'sanbusinessgroup';
    private const SYNC_KEY = 'belomo_static_pricelist';

    public function handle(BelomoPriceList $priceList, SupplierProductAvailabilityService $availability): int
    {
        $apply = (bool) $this->option('apply') && ! (bool) $this->option('dry-run');
        $rows = $priceList->rows();
        $supplierId = (int) (DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id') ?? 0);
        $classified = $this->classify($rows, $priceList, $supplierId);
        $reportPath = $this->writeReport($classified);
        $counts = collect($classified)->countBy('status');

        $this->line($apply ? 'APPLY: прайс БелОМО будет привязан к поставщику СанБизнесГруп.' : 'DRY RUN: база не изменяется.');
        $this->line('metric.belomo.price_rows='.count($rows));
        $this->line('metric.belomo.matched='.$counts->get('matched', 0));
        $this->line('metric.belomo.create='.$counts->get('create', 0));
        $this->line('metric.belomo.ambiguous='.$counts->get('ambiguous', 0));
        $this->line('metric.belomo.report='.$reportPath);

        if (! $apply) {
            return self::SUCCESS;
        }

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
        DB::table('supplier_sources')->where('supplier_id', $supplierId)->where('name', 'Прайс БелОМО 15.09.2026')->update(['last_run_at' => $now, 'updated_at' => $now]);
        DB::table('supplier_syncs')->where('id', $syncId)->update(['last_run_at' => $now, 'updated_at' => $now]);
        $this->info("Применено {$applied}; создано неактивных черновиков {$created}. Розничные цены Эликона не изменялись.");
        $this->line('metric.belomo.applied='.$applied);
        $this->line('metric.belomo.created='.$created);

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
            $candidates = $links->filter(function (object $link) use ($compact, $short, $priceList, $supplierId): bool {
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

    private function writeReport(array $classified): string
    {
        $path = trim((string) $this->option('report')) ?: storage_path('app/reports/suppliers/belomo/current-audit.csv');
        File::ensureDirectoryExists(dirname($path));
        $handle = fopen($path, 'wb');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['status', 'article', 'name', 'price_without_vat_byn', 'partner_price_with_vat_byn', 'product_id', 'sku', 'retail_price_byn'], ';');
        foreach ($classified as $item) {
            $product = $item['product'];
            fputcsv($handle, [
                $item['status'], $item['row']['article'], $item['row']['name'],
                number_format($item['row']['partner_price_byn'], 2, '.', ''),
                number_format($this->priceWithVat($item['row']['partner_price_byn']), 2, '.', ''),
                $product->id ?? '', $product->sku ?? '', isset($product->price) ? number_format((float) $product->price, 2, '.', '') : '',
            ], ';');
        }
        fclose($handle);
        return $path;
    }

    private function ensureSupplier($now): int
    {
        $existing = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->first();
        $priceListNote = 'Прайс БелОМО от 15.09.2026 используется для оптовых цен счетчиков; розница поступает отдельно с сайта Эликона.';
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
            'name' => 'СанБизнесГруп', 'code' => self::SUPPLIER_CODE, 'title' => 'СанБизнесГруп: оптовый прайс счетчиков БелОМО',
            'description' => 'Статический закупочный прайс без НДС; первая ценовая колонка до 10 шт. Розница Эликона сохраняется отдельно.',
            'command' => 'supplier:sync-belomo-pricelist', 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        return (int) DB::table('supplier_syncs')->where('key', self::SYNC_KEY)->value('id');
    }

    private function ensureB2bSource(int $supplierId, $now): int
    {
        $categoryId = $this->categoryId();
        DB::table('integration_sources')->updateOrInsert(['code' => 'belomo_wholesale'], [
            'supplier_id' => $supplierId, 'name' => 'СанБизнесГруп · оптовые счетчики БелОМО', 'driver' => 'static_pricelist',
            'price_currency' => 'BYN', 'price_currency_rate' => 1, 'is_active' => true,
            'create_products' => false, 'update_prices' => true, 'update_stock' => false,
            'settings' => json_encode([
                'price_tax_mode' => 'exclusive', 'vat_rate' => 20, 'b2b_enabled' => true,
                'b2b_category_ids' => [$categoryId], 'partner_name' => 'ООО «СанБизнесГруп»',
                'procurement_mode' => true, 'warehouse_label' => 'Закупка БелОМО',
                'stock_validity_minutes' => 525600,
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => $now, 'updated_at' => $now,
        ]);
        return (int) DB::table('integration_sources')->where('code', 'belomo_wholesale')->value('id');
    }

    private function upsertSupplierProduct(int $supplierId, int $syncId, object $product, array $row, $now): void
    {
        $compact = mb_strtolower(preg_replace('/[^\pL\pN]+/u', '', $row['article']) ?? '');
        DB::table('supplier_products')->updateOrInsert(['supplier_id' => $supplierId, 'supplier_article' => $row['article']], [
            'supplier_sync_id' => $syncId, 'product_id' => $product->id, 'product_sku' => $product->sku,
            'supplier_article_normalized' => mb_strtolower($row['article']), 'supplier_article_compact' => $compact,
            'supplier_name' => $row['name'], 'price' => $row['partner_price_byn'], 'currency' => 'BYN', 'currency_rate' => 1,
            'price_byn' => $row['partner_price_byn'], 'in_stock' => false, 'stock_quantity' => null,
            'stock_status' => 'preorder', 'stock_text' => 'Закупка у производителя · наличие и срок по запросу',
            'match_status' => 'matched', 'match_confidence' => '100',
            'raw' => json_encode(['vat_included' => false, 'price_column' => 'до 10 шт', 'effective_date' => '2026-09-15'], JSON_UNESCAPED_UNICODE),
            'last_synced_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function upsertPartnerOffer(int $sourceId, object $product, array $row, $now): void
    {
        DB::table('integration_products')->updateOrInsert(['integration_source_id' => $sourceId, 'external_id' => $row['article']], [
            'product_id' => $product->id, 'target_category_id' => $product->category_id, 'external_code' => $row['article'],
            'external_sku' => $row['article'], 'name' => $row['name'], 'price' => $row['partner_price_byn'],
            'price_currency' => 'BYN', 'price_currency_rate' => 1, 'price_tax_mode' => 'exclusive', 'price_vat_rate' => 20,
            'price_byn' => $this->priceWithVat($row['partner_price_byn']), 'stock_quantity' => 0,
            'match_status' => 'matched', 'match_method' => 'belomo_article', 'match_confidence' => 100,
            'payload' => json_encode(['procurement_mode' => true, 'vat_included' => false, 'source_document' => 'Цены БелОМО.pdf'], JSON_UNESCAPED_UNICODE),
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
        if (! $id) throw new \RuntimeException('Не найден бренд БелОМО.');
        return (int) $id;
    }

    private function categoryId(): int
    {
        $id = DB::table('categories')->where('slug', 'schetchiki-gaza')->value('id');
        if (! $id) throw new \RuntimeException('Не найдена категория «Счетчики газа».');
        return (int) $id;
    }

    private function nextSku(): string
    {
        $next = ((int) DB::table('products')->where('sku', 'like', 'KOTLOV-%')->pluck('sku')->map(fn ($sku) => preg_match('/^KOTLOV-(\d+)$/', (string) $sku, $m) ? (int) $m[1] : 0)->max()) + 1;
        do { $sku = sprintf('KOTLOV-%06d', $next++); } while (DB::table('products')->where('sku', $sku)->exists());
        return $sku;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'belomo-gas-meter'; $slug = $base; $i = 2;
        while (DB::table('products')->where('slug', $slug)->exists()) $slug = $base.'-'.$i++;
        return $slug;
    }

    private function priceWithVat(float $priceWithoutVat): float
    {
        return round($priceWithoutVat * 1.20, 2);
    }
}
