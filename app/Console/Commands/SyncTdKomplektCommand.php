<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierSource;
use App\Models\SupplierSync;
use App\Services\SupplierProductAvailabilityService;
use App\Services\Suppliers\TdKomplektFeed;
use App\Services\Suppliers\TdKomplektMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class SyncTdKomplektCommand extends Command
{
    protected $signature = 'supplier:sync-td-komplekt {--file= : Local CSV snapshot} {--apply : Save offers and exact matches} {--dry-run : Preview only} {--create-new : Create confidently classified, unambiguous new products} {--report= : Private JSON report path}';

    protected $description = 'ТД Комплект: CSV tools.by, exact article matching, wholesale/retail and stock synchronization.';

    public function handle(TdKomplektFeed $feed): int
    {
        $lock = Cache::lock('supplier:sync-td-komplekt', 1800);
        if (! $lock->get()) {
            $this->error('Обмен ТД Комплект уже запущен.');

            return self::FAILURE;
        }
        $sync = SupplierSync::where('key', 'td_komplekt_csv')->first();
        $transaction = false;
        try {
            $rows = $feed->load($this->option('file'));
            $names = array_count_values(array_map(fn ($r) => TdKomplektFeed::key($r['name']), $rows));
            $barcodes = array_count_values(array_filter(array_column($rows, 'barcode')));
            $matcher = new TdKomplektMatcher;
            $supplier = Supplier::where('code', 'td_komplekt')->first();
            $old = $supplier ? SupplierProduct::where('supplier_id', $supplier->id)->get()->keyBy('source_wp_id') : collect();
            // A partial local input must never remove stock from a full supplier feed.
            if ($old->count() && count($rows) < $old->count() * .8) {
                throw new RuntimeException('Число строк уменьшилось более чем на 20%. Обмен остановлен.');
            }
            $categories = Category::pluck('id', 'slug');
            $report = [];
            $stats = ['rows' => count($rows), 'matched' => 0, 'ambiguous' => 0, 'created' => 0, 'offers' => 0, 'retail_updated' => 0, 'retail_review' => 0, 'missing' => 0];
            foreach ($rows as $id => $row) {
                $row['_duplicate_name'] = ($names[TdKomplektFeed::key($row['name'])] ?? 0) > 1;
                $link = $old->get($id);
                $match = $matcher->match($row);
                if (($barcodes[$row['barcode'] ?? ''] ?? 0) > 1) {
                    $match = ['product_id' => null, 'candidates' => $match['candidates'], 'reason' => 'duplicate_feed_barcode'];
                }
                if ($link?->product_id) {
                    // Stable supplier ID is authoritative; never remap it automatically.
                    $p = Product::find($link->product_id);
                    $changedArticle = ($link->raw['identity_vendor_code'] ?? $link->raw['vendor_code'] ?? '') !== $row['vendor_code']
                        || TdKomplektFeed::key($link->raw['identity_brand'] ?? $link->raw['brand'] ?? '') !== TdKomplektFeed::key($row['brand']);
                    $match = ($p && ! $p->is_archived && ! $changedArticle)
                        ? ['product_id' => $p->id, 'candidates' => [$p->id], 'reason' => 'existing_supplier_id']
                        : ['product_id' => null, 'candidates' => [], 'reason' => 'linked_identity_changed'];
                }
                $slug = $feed->category($row);
                $category = $slug ? ($categories[$slug] ?? null) : null;
                $report[$id] = ['id' => (string) $id, 'article' => $row['vendor_code'], 'name' => $row['name'], 'category' => $slug, 'category_id' => $category] + $match;
                if ($match['product_id']) {
                    $stats['matched']++;
                }
                if (in_array($match['reason'], ['ambiguous', 'conflicting_identifiers', 'linked_identity_changed', 'duplicate_feed_barcode', 'brand_unconfirmed'])) {
                    $stats['ambiguous']++;
                }
            }
            if ($this->option('apply') && ! $this->option('dry-run')) {
                DB::beginTransaction();
                $transaction = true;
                $supplier ??= Supplier::create(['code' => 'td_komplekt', 'name' => 'ООО «ТД Комплект»', 'currency' => 'BYN', 'currency_rate' => 1, 'contact' => 'https://tools.by', 'is_active' => true, 'notes' => 'CSV tools.by: отдельные закупочная и розничная цены. Срок в днях — под заказ, не остаток.']);
                $sync = SupplierSync::updateOrCreate(['key' => 'td_komplekt_csv'], ['supplier_id' => $supplier->id, 'code' => 'td_komplekt', 'name' => 'ТД Комплект', 'title' => 'ТД Комплект — каталог tools.by', 'command' => 'supplier:sync-td-komplekt', 'source_url' => 'https://tools.by', 'description' => 'Ежечасно: артикулы, цены, остатки и сроки. Неоднозначные связи — в отчёте.', 'is_active' => true]);
                $touched = [];
                $brandIds = [];
                foreach ($rows as $id => $row) {
                    $result = $report[$id];
                    $data = $row['_data'];
                    $p = $result['product_id'] ? Product::find($result['product_id']) : null;
                    $previous = $old->get($id);
                    $canCreate = $this->option('create-new') && ! $p && $result['reason'] === 'unmatched' && $result['category_id'] && $row['vendor_code'] !== '' && $row['brand'] !== '' && $data['retail'] > 0 && $data['retail'] >= $data['wholesale'] && TdKomplektFeed::image($row['media_img']);
                    if ($canCreate) {
                        $p = $this->createProduct($row, $result['category_id'], $supplier, $feed);
                        $report[$id]['product_id'] = $p->id;
                        $report[$id]['reason'] = 'created';
                        $stats['created']++;
                    }
                    // Keep old links pending review on identifier changes, but withdraw their stock.
                    $quarantined = $result['reason'] === 'linked_identity_changed';
                    $productId = $p?->id ?? ($quarantined ? $previous?->product_id : null);
                    SupplierProduct::updateOrCreate(['supplier_id' => $supplier->id, 'source_wp_id' => (int) $id], [
                        'supplier_sync_id' => $sync->id, 'product_id' => $productId, 'product_sku' => $p?->sku ?? $previous?->product_sku,
                        'supplier_article' => $row['vendor_code'] ?: $row['okdp'], 'supplier_article_normalized' => TdKomplektFeed::key($row['vendor_code'] ?: $row['okdp']),
                        'supplier_article_compact' => TdKomplektFeed::key($row['vendor_code'] ?: $row['okdp']), 'supplier_name' => $row['name'],
                        'source_url' => 'https://tools.by', 'price' => $data['wholesale'], 'price_byn' => $data['wholesale'], 'currency' => 'BYN', 'currency_rate' => 1,
                        'in_stock' => ! $quarantined && $data['in_stock'], 'stock_quantity' => $quarantined ? 0 : $data['quantity'], 'stock_status' => $quarantined ? 'unknown' : $data['stock_status'],
                        'stock_text' => $quarantined ? 'Изменился артикул — требуется проверка' : $row['warehouse_all'], 'warehouse_name' => 'ТД Комплект — все склады (без суммирования дублей)',
                        'delivery_days' => $data['delivery_days'], 'last_stock_synced_at' => now(), 'last_synced_at' => now(),
                        'match_status' => $quarantined ? 'review' : ($p ? 'matched' : 'unmatched'), 'match_confidence' => $report[$id]['reason'],
                        'raw' => array_diff_key($row, ['_data' => true]) + ['retail' => $data['retail'], 'wholesale' => $data['wholesale'], 'source' => 'tools.by CSV', 'identity_review' => $quarantined, 'identity_vendor_code' => $previous?->raw['identity_vendor_code'] ?? ($previous?->product_id ? ($previous->raw['vendor_code'] ?? $row['vendor_code']) : $row['vendor_code']), 'identity_brand' => $previous?->raw['identity_brand'] ?? ($previous?->product_id ? ($previous->raw['brand'] ?? $row['brand']) : $row['brand'])],
                    ]);
                    $stats['offers']++;
                    if ($productId) {
                        $touched[] = $productId;
                    }
                    if (! $p || $quarantined) {
                        continue;
                    }
                    if ((int) $p->supplier_id === (int) $supplier->id && $previous && isset($previous->raw['vendor_code'])) {
                        $before = $feed->catalogFields($previous->raw);
                        $after = $feed->catalogFields($row);
                        $updates = [];
                        foreach ($after as $key => $value) {
                            if ($before[$key] === $p->$key && $value !== $p->$key) {
                                $updates[$key] = $value;
                            }
                        }
                        if ($updates !== []) {
                            $p->update($updates);
                        } // Preserve manual editorial changes.
                    }
                    if ((int) $p->supplier_id === (int) $supplier->id && $data['retail'] > 0 && $data['retail'] >= $data['wholesale'] && abs((float) $p->price - $data['retail']) > .005) {
                        if ((float) $p->price > 0 && abs($data['retail'] / (float) $p->price - 1) > .3) {
                            $stats['retail_review']++;
                            $report[$id]['price_review'] = true;
                        } else {
                            $p->update(['price' => $data['retail']]);
                            $stats['retail_updated']++;
                        }
                    }
                    if ($p->brand_id) {
                        $brandIds[$p->brand_id] = $p->brand_id;
                    }
                }
                $missing = SupplierProduct::where('supplier_id', $supplier->id)->where('supplier_sync_id', $sync->id)->whereNotIn('source_wp_id', array_keys($rows))->get();
                foreach ($missing as $offer) {
                    $offer->update(['in_stock' => false, 'stock_quantity' => 0, 'stock_status' => 'out_of_stock', 'stock_text' => 'Отсутствует в последней полной выгрузке', 'last_stock_synced_at' => now()]);
                    if ($offer->product_id) {
                        $touched[] = $offer->product_id;
                    }
                    $stats['missing']++;
                }
                app(SupplierProductAvailabilityService::class)->refreshMany($touched);
                $supplier->brands()->syncWithoutDetaching(array_values($brandIds));
                SupplierSource::updateOrCreate(['supplier_id' => $supplier->id, 'name' => 'tools.by CSV'], ['type' => 'api', 'config' => ['url' => 'https://tools.by', 'private_config' => TdKomplektFeed::DIRECTORY.'/config.json', 'retail_column' => 'price', 'purchase_column' => 'price_wholesale', 'stock_column' => 'warehouse_all', 'vat_column' => 'vat', 'report' => TdKomplektFeed::DIRECTORY.'/report.json'], 'column_mappings' => ['article' => 'vendor_code', 'external_id' => 'id', 'barcode' => 'barcode', 'retail' => 'price', 'purchase' => 'price_wholesale', 'stock' => 'warehouse_all'], 'schedule_cron' => '30 * * * *', 'is_active' => true, 'last_run_at' => now()]);
                $sync->update(['last_run_at' => now(), 'last_status' => 'updated', 'last_exit_code' => 0]);
                DB::commit();
                $transaction = false;
            }
            $output = ['generated_at' => now()->toIso8601String(), 'stats' => $stats, 'items' => array_values($report)];
            $path = $this->option('report') ?: Storage::disk('local')->path(TdKomplektFeed::DIRECTORY.'/report.json');
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0775, true);
            }
            file_put_contents($path, json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            foreach ($stats as $key => $value) {
                $this->line("metric.td_komplekt.{$key}={$value}");
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            if ($transaction) {
                DB::rollBack();
            }
            if ($this->option('apply') && ! $this->option('dry-run')) {
                $sync?->update(['last_run_at' => now(), 'last_status' => 'failed', 'last_exit_code' => 1]);
            }
            if (app()->runningUnitTests()) {
                throw $e;
            }
            $this->error('Обмен ТД Комплект остановлен. '.$this->safeError($e));

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }

    private function safeError(\Throwable $e): string
    {
        return $e instanceof RuntimeException && ! str_contains($e->getMessage(), 'http') ? $e->getMessage() : 'Проверьте приватный источник и журнал ошибок.';
    }

    private function createProduct(array $row, int $categoryId, Supplier $supplier, TdKomplektFeed $feed): Product
    {
        $brand = Brand::whereRaw('LOWER(name) = ?', [mb_strtolower($row['brand'])])->first();
        if (! $brand && $row['brand'] !== '') {
            $slug = Str::slug($row['brand']) ?: 'td-brand-'.substr(sha1($row['brand']), 0, 8);
            if (Brand::where('slug', $slug)->exists()) {
                $slug .= '-td';
            }
            $brand = Brand::create(['name' => $row['brand'], 'slug' => $slug, 'is_active' => true]);
        }
        $slug = Str::limit(Str::slug($row['name']), 180, '').'-td-'.$row['id'];

        return Product::create(['supplier_id' => $supplier->id, 'category_id' => $categoryId, 'brand_id' => $brand?->id, 'sku' => 'TDK-'.$row['id'], 'slug' => $slug, 'name' => $row['name'], 'h1' => $row['name'],
            'price' => $row['_data']['retail'], 'currency' => 'BYN', 'unit' => $row['prop_unit'] ?: 'шт.',
            'content_source_url' => 'https://tools.by',
            'meta_title' => Str::limit($row['name'], 120, '').' — купить в %city% | KOTLOV', 'meta_description' => Str::limit($row['name'].'. Цена, наличие и условия поставки — KOTLOV. Заказ и доставка по Беларуси.', 175, ''),
            'is_active' => true, 'is_archived' => false, 'in_stock' => false, 'availability_status' => Product::AVAILABILITY_CHECK, 'requires_price_confirmation' => false] + $feed->catalogFields($row));
    }
}
