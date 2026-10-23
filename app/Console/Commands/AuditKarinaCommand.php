<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierProductMapping;
use App\Services\PriceList\KarinaPriceAudit;
use App\Services\Pricing\KarinaPricing;
use Illuminate\Support\Facades\Http;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class AuditKarinaCommand extends Command
{
    protected $signature = 'supplier:audit-karina';
    protected $description = 'Read-only KARINA staged workbook and catalog audit; never imports or applies prices';

    public function handle(KarinaPriceAudit $audit): int
    {
        $output = storage_path('app/reports/suppliers/karina/audit.json');
        File::ensureDirectoryExists(dirname($output));
        File::delete($output); // A failed run must never publish a previous successful report.
        $path = Storage::disk('local')->path('pricelists/karina-thread-281.xlsx');
        if (! is_file($path)) {
            $this->error('KARINA staged workbook is missing.');
            return self::FAILURE;
        }

        $workbook = $audit->inspect($path);
        $products = Product::query()
            ->where(function ($query) use ($workbook): void {
                $query->where('name', 'like', '%KARINA%')
                    ->orWhereIn('sku', array_values(array_filter(array_column($workbook['items'], 'article'))))
                    ->orWhere('name', 'like', '%КАРИНА%')
                    ->orWhereHas('brand', fn ($brand) => $brand->where('name', 'like', '%KARINA%')->orWhere('name', 'like', '%КАРИНА%'));
            })
            ->orderBy('id')
            ->get(['id', 'brand_id', 'supplier_id', 'sku', 'name', 'price', 'price_old', 'is_active', 'is_archived'])
            ->toArray();
        $ids = array_column($products, 'id');
        $suppliers = Supplier::query()->where(function ($query): void {
            $query->where('code', 'like', '%karina%')
                ->orWhere('name', 'like', '%KARINA%')->orWhere('name', 'like', '%Тепломаркет%');
        })->orderBy('id')->get(['id', 'code', 'name', 'currency', 'currency_rate', 'is_active'])->toArray();
        $supplierIds = array_column($suppliers, 'id');
        $links = SupplierProduct::query()->where(function ($query) use ($ids, $supplierIds): void {
            $query->whereIn('product_id', $ids)->orWhereIn('supplier_id', $supplierIds);
        })->orderBy('id')->get([
            'id', 'supplier_id', 'product_id', 'supplier_article', 'price', 'currency', 'price_byn', 'match_status',
        ])->toArray();
        $mappings = SupplierProductMapping::query()->where(function ($query) use ($ids, $suppliers): void {
            $query->whereIn('product_id', $ids)->orWhereIn('supplier_code', array_column($suppliers, 'code'));
        })->orderBy('id')->get(['id', 'supplier_code', 'supplier_article', 'product_id', 'is_active'])->toArray();
        $items = $audit->match($workbook['items'], $products);
        unset($workbook['items']);
        $rate = KarinaPricing::rateEvidence(
            Http::timeout(15)->get(KarinaPricing::RATE_URL)->throw()->json(),
            now()->format('Y-m-d'),
        );
        $productIndex = collect($products)->keyBy('id');
        foreach ($items as &$item) {
            $item = array_merge($item, KarinaPricing::quote((float) $item['retail'], $rate['byn_per_rub']));
            $product = count($item['candidate_product_ids']) === 1 ? $productIndex->get($item['candidate_product_ids'][0]) : null;
            $blocked = [];
            if ($item['match_status'] !== 'exact_candidate') {
                $blocked[] = $item['match_status'];
            }
            if ($item['duplicate_article'] || $item['duplicate_name'] || $item['shared_target'] || $item['missing_article']) {
                $blocked[] = 'identity_conflict';
            }
            if ($product && (! $product['is_active'] || $product['is_archived'])) {
                $blocked[] = 'inactive_or_archived';
            }
            $item['blocked_reasons'] = $blocked;
            $item['price_plan'] = $product ? [
                'product_id' => $product['id'], 'current_retail_byn' => (float) $product['price'],
                'proposed_retail_byn' => $item['retail_byn'],
                'delta_byn' => round($item['retail_byn'] - (float) $product['price'], 2),
                'eligible_for_reviewed_apply' => $blocked === [],
            ] : null;
        }
        unset($item);

        // Explicit projections only: no CRM messages, metadata, contacts, raw offers or logs.
        $report = [
            'schema' => 'karina-audit-v1', 'generated_at' => now()->toIso8601String(),
            'mode' => 'read_only', 'applied' => false, 'discount_percent' => 25,
            'currency_confirmed' => true, 'source_currency' => 'RUB', 'site_currency' => 'BYN',
            'currency_confirmation' => 'User confirmed RUB; site retail equals converted RRP.',
            'rate' => $rate,
            'warnings' => [
                'RUB confirmed by user; conversion uses dated NBRB official rate divided by scale.',
                'Do not use generic prices:update or currency:fetch-nbrb-rates for KARINA until retail/purchase policy is supported.',
                'Exact candidates require review of model, power and configuration.',
                'Unmatched is not authorization to create a new product.',
                'Existing mappings and supplier links are reported separately and are not automatically trusted.',
            ],
            'workbook' => $workbook, 'suppliers' => $suppliers, 'products' => $products,
            'supplier_links' => $links, 'existing_mappings' => $mappings, 'items' => $items,
            'catalog_duplicate_names' => collect($products)->groupBy(fn ($p) => $audit->normalize($p['name']))->filter(fn ($group) => $group->count() > 1)->map(fn ($group) => $group->pluck('id')->all())->all(),
            'catalog_duplicate_skus' => collect($products)->filter(fn ($p) => trim((string) $p['sku']) !== '')->groupBy(fn ($p) => $audit->normalize((string) $p['sku']))->filter(fn ($group) => $group->count() > 1)->map(fn ($group) => $group->pluck('id')->all())->all(),
            'summary' => [
                'products' => count($products), 'items' => count($items),
                'exact_candidates' => count(array_filter($items, fn ($row) => $row['match_status'] === 'exact_candidate')),
                'ambiguous' => count(array_filter($items, fn ($row) => $row['match_status'] === 'ambiguous')),
                'unmatched' => count(array_filter($items, fn ($row) => $row['match_status'] === 'unmatched')),
                'duplicate_articles' => count(array_filter($items, fn ($row) => $row['duplicate_article'])),
                'duplicate_names' => count(array_filter($items, fn ($row) => $row['duplicate_name'])),
                'shared_targets' => count(array_filter($items, fn ($row) => $row['shared_target'])),
            ],
        ];
        File::put($output, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
        $this->line('metric.audit_items='.count($items));
        $this->line('metric.audit_products='.count($products));

        return self::SUCCESS;
    }
}
