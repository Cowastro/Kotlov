<?php

namespace App\Services\PriceList;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierPriceImport;
use App\Models\SupplierPriceItem;
use App\Models\SupplierProduct;
use App\Models\SupplierProductMapping;
use App\Services\Pricing\KarinaPricing;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ApplyReviewedKarina
{
    public function run(array $report, bool $apply = false): array
    {
        if (($report['schema'] ?? '') !== 'karina-audit-v1' || ($report['source_currency'] ?? '') !== 'RUB'
            || ($report['currency_confirmed'] ?? false) !== true || ($report['applied'] ?? true) !== false) {
            throw new RuntimeException('A confirmed read-only RUB audit is required.');
        }
        $source = (string) ($report['workbook']['sha256'] ?? '');
        if (! preg_match('/^[a-f0-9]{64}$/', $source)) {
            throw new RuntimeException('Workbook hash missing.');
        }
        $filename = 'karina-reviewed-'.$source.'.xlsx';

        return DB::transaction(function () use ($report, $apply, $filename, $source): array {
            $supplier = Supplier::query()->where('code', 'karina')->lockForUpdate()->first();
            $prior = $supplier ? SupplierPriceImport::query()->where('supplier_id', $supplier->id)
                ->where('filename', $filename)->lockForUpdate()->first() : null;
            if ($prior) {
                if ($prior->status !== 'done') {
                    throw new RuntimeException('Existing KARINA batch is incomplete; manual review required.');
                }
                return ['already_applied' => true, 'import_id' => $prior->id, 'matched' => $prior->matched];
            }
            if (Supplier::query()->where('code', '!=', 'karina')
                ->where(fn ($q) => $q->where('name', 'like', '%Тепломаркет%')->orWhere('name', 'like', '%KARINA%'))->exists()) {
                throw new RuntimeException('Potential existing KARINA supplier; refusing a duplicate.');
            }
            if ($supplier && ($supplier->currency !== 'RUB' || ! $supplier->is_active)) {
                throw new RuntimeException('Existing KARINA supplier settings require review.');
            }
            $rate = KarinaPricing::rateEvidence([
                'Cur_Abbreviation' => 'RUB', 'Cur_OfficialRate' => $report['rate']['official_rate'],
                'Cur_Scale' => $report['rate']['scale'], 'Date' => $report['rate']['date'],
            ], now()->format('Y-m-d'))['byn_per_rub'];
            if (abs($rate - (float) $report['rate']['byn_per_rub']) > 0.0000001) {
                throw new RuntimeException('Rate snapshot mismatch.');
            }
            $audit = new KarinaPriceAudit;
            $items = $report['items'];
            $current = Product::query()->where(function ($q) use ($items): void {
                $q->where('name', 'like', '%KARINA%')->orWhere('name', 'like', '%КАРИНА%')
                    ->orWhereIn('sku', array_column($items, 'article'));
            })->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $rematched = $audit->match($items, $current->map(fn ($p) => $p->only(['id', 'sku', 'name']))->values()->all());
            $snapshots = collect($report['products'])->keyBy('id');
            $selected = [];
            $offers = [];
            foreach ($items as $i => $item) {
                // Both rows of a conflicting supplier article stay outside the import.
                if ($rematched[$i]['duplicate_article'] || $item['article'] === '') {
                    continue;
                }
                $quote = KarinaPricing::quote((float) $item['retail_rub'], $rate);
                if (abs($quote['purchase_rub'] - (float) $item['purchase_rub']) > 0.001
                    || abs($quote['retail_byn'] - (float) $item['retail_byn']) > 0.001) {
                    throw new RuntimeException('Quote differs from reviewed plan.');
                }
                $offers[] = array_merge($item, $quote);
                if (! ($item['price_plan']['eligible_for_reviewed_apply'] ?? false)) {
                    continue;
                }
                $id = (int) $item['price_plan']['product_id'];
                $product = $current->get($id);
                $snapshot = $snapshots->get($id);
                if ($rematched[$i]['match_status'] !== 'exact_candidate'
                    || $rematched[$i]['candidate_product_ids'] !== [$id]
                    || $rematched[$i]['duplicate_name'] || $rematched[$i]['shared_target']
                    || ! $product || ! $snapshot || ! $product->is_active || $product->is_archived
                    || $product->sku !== $snapshot['sku'] || $product->name !== $snapshot['name']
                    || (int) $product->brand_id !== (int) $snapshot['brand_id']
                    || abs((float) $product->price - (float) $snapshot['price']) > 0.001
                    || $product->price_old != $snapshot['price_old']) {
                    throw new RuntimeException('Catalog changed after audit; refresh and review the plan.');
                }
                if ($supplier) {
                    $link = SupplierProduct::query()->where('supplier_id', $supplier->id)
                        ->where('supplier_article', $item['article'])->lockForUpdate()->first();
                    if ($link && (int) $link->product_id !== $id) {
                        throw new RuntimeException('Existing supplier link conflicts with reviewed target.');
                    }
                }
                $mapping = SupplierProductMapping::query()->where('supplier_code', 'karina')
                    ->where('supplier_article', $item['article'])->lockForUpdate()->first();
                if ($mapping && (int) $mapping->product_id !== $id) {
                    throw new RuntimeException('Existing mapping conflicts with reviewed target.');
                }
                $selected[$item['article']] = ['product' => $product, 'quote' => $quote];
            }
            if ($selected === []) {
                throw new RuntimeException('No reviewed active targets.');
            }
            $result = ['already_applied' => false, 'offers' => count($offers), 'matched' => count($selected),
                'unmatched' => count($offers) - count($selected), 'quarantined' => count($items) - count($offers)];
            if (! $apply) {
                return $result;
            }
            if (! $supplier) {
                $supplier = Supplier::create(['code' => 'karina', 'name' => 'KARINA / ООО «Тепломаркет»',
                    'currency' => 'RUB', 'currency_rate' => $rate, 'is_active' => true,
                    'automatic_order_transfer_enabled' => false]);
            } else {
                $supplier->update(['currency_rate' => $rate]);
            }
            $import = SupplierPriceImport::create(['supplier_id' => $supplier->id, 'filename' => $filename,
                'total_rows' => count($offers), 'matched' => count($selected), 'unmatched' => $result['unmatched'],
                'updated' => count($selected), 'skipped' => $result['unmatched'], 'status' => 'done', 'imported_at' => now()]);
            foreach ($offers as $item) {
                $target = $selected[$item['article']] ?? null;
                $raw = ['karina' => ['retail_rub' => $item['retail_rub'], 'workbook_sha256' => $source,
                    'source_row' => $item['row'], 'reviewed' => $target !== null]];
                SupplierPriceItem::create([
                    'supplier_id' => $supplier->id, 'import_id' => $import->id, 'article' => $item['article'],
                    'name' => $item['name'], 'price' => $item['purchase_rub'], 'currency' => 'RUB',
                    'currency_rate' => $rate, 'price_byn' => $item['purchase_byn'], 'raw' => $raw,
                    'in_stock' => false, 'stock_qty' => null, // The workbook does not confirm stock.
                    'product_id' => $target ? $target['product']->id : null,
                    'product_sku' => $target ? $target['product']->sku : null,
                    'match_status' => $target ? 'matched' : 'unmatched', 'match_method' => $target ? 'manual' : null,
                ]);
                if (! $target) {
                    continue;
                }
                $product = $target['product'];
                SupplierProductMapping::updateOrCreate(['supplier_code' => 'karina', 'supplier_article' => $item['article']], [
                    'product_id' => $product->id, 'product_sku' => $product->sku, 'supplier_name' => $item['name'],
                    'confidence' => 'manual', 'is_active' => true, 'notes' => 'Issue #34 reviewed exact active target',
                ]);
                $link = SupplierProduct::firstOrNew(['supplier_id' => $supplier->id, 'supplier_article' => $item['article']]);
                $link->fill(['product_id' => $product->id, 'product_sku' => $product->sku,
                    'supplier_name' => $item['name'], 'price' => $item['purchase_rub'], 'currency' => 'RUB',
                    'currency_rate' => $rate, 'price_byn' => $item['purchase_byn'], 'raw' => $raw,
                    'match_status' => 'matched', 'match_confidence' => 'manual_price_list', 'last_synced_at' => now()]);
                if (! $link->exists) {
                    $link->fill(['in_stock' => false, 'stock_status' => 'unknown', 'stock_quantity' => null]);
                }
                $link->save();
                // Preserve all stock, content, identity, media and archive fields.
                DB::table('products')->where('id', $product->id)->update([
                    'price' => $item['retail_byn'], 'price_old' => null, 'updated_at' => now(),
                ]);
            }

            return $result + ['supplier_id' => $supplier->id, 'import_id' => $import->id];
        });
    }
}
