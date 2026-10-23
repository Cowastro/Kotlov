<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierProductMapping;
use App\Models\SupplierPriceImport;
use App\Models\SupplierPriceItem;
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
            ->get(['id', 'category_id', 'brand_id', 'supplier_id', 'sku', 'slug', 'name', 'price', 'price_old', 'is_active', 'is_archived', 'created_via'])
            ->toArray();
        $ids = array_column($products, 'id');
        $sourceArticles = array_values(array_unique(array_filter(array_column($workbook['items'], 'article'))));
        $sourceNames = array_values(array_unique(array_filter(array_column($workbook['items'], 'name'))));
        $crossSupplierLinks = SupplierProduct::query()
            ->whereIn('supplier_article', $sourceArticles)
            ->orderBy('supplier_article')->orderBy('id')
            ->get(['id', 'supplier_id', 'product_id', 'product_sku', 'supplier_article', 'supplier_name', 'match_status'])
            ->toArray();
        $crossSupplierMappings = SupplierProductMapping::query()
            ->whereIn('supplier_article', $sourceArticles)
            ->orderBy('supplier_article')->orderBy('id')
            ->get(['id', 'supplier_code', 'supplier_article', 'product_id', 'product_sku', 'supplier_name', 'confidence', 'is_active'])
            ->toArray();
        $exactNameProducts = Product::query()
            ->whereIn('name', $sourceNames)
            ->orderBy('id')
            ->get(['id', 'category_id', 'brand_id', 'sku', 'name', 'is_active', 'is_archived'])
            ->toArray();
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

        $linkIdsByArticle = collect($crossSupplierLinks)->groupBy('supplier_article')
            ->map(fn ($rows) => $rows->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all());
        $mappingIdsByArticle = collect($crossSupplierMappings)->groupBy('supplier_article')
            ->map(fn ($rows) => $rows->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all());
        $exactNameIds = collect($exactNameProducts)->groupBy(fn ($product) => $audit->normalize($product['name']))
            ->map(fn ($rows) => $rows->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all());
        $creationCandidates = collect($items)->map(function (array $item) use (
            $audit, $linkIdsByArticle, $mappingIdsByArticle, $exactNameIds
        ): array {
            $article = (string) $item['article'];
            $crossIds = collect($linkIdsByArticle->get($article, []))
                ->merge($mappingIdsByArticle->get($article, []))
                ->merge($exactNameIds->get($audit->normalize($item['name']), []))
                ->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
            $blocked = [];
            if ($item['duplicate_article'] || $item['missing_article']) {
                $blocked[] = 'supplier_identity_conflict';
            }
            if ($item['match_status'] !== 'unmatched') {
                $blocked[] = $item['match_status'];
            }
            if ($crossIds !== []) {
                $blocked[] = 'existing_catalog_or_supplier_reference';
            }

            return [
                'row' => $item['row'], 'article' => $article, 'name' => $item['name'],
                'retail_rub' => $item['retail_rub'], 'purchase_rub' => $item['purchase_rub'],
                'retail_byn' => $item['retail_byn'], 'purchase_byn' => $item['purchase_byn'],
                'existing_product_ids' => $crossIds, 'blocked_reasons' => array_values(array_unique($blocked)),
                'eligible_for_hidden_creation_review' => $blocked === [],
            ];
        })->values()->all();

        $receiptPath = storage_path('app/reports/suppliers/karina/apply.json');
        $receipt = is_file($receiptPath) ? json_decode(file_get_contents($receiptPath), true, 512, JSON_THROW_ON_ERROR) : [];

        // Explicit projections only: no CRM messages, metadata, contacts, raw offers or logs.
        $report = [
            'schema' => 'karina-audit-v1', 'generated_at' => now()->toIso8601String(),
            'mode' => 'read_only', 'applied' => false, 'discount_percent' => 25,
            'currency_confirmed' => true, 'source_currency' => 'RUB', 'site_currency' => 'BYN',
            'last_application' => array_intersect_key($receipt, array_flip(['applied', 'report_blob', 'workbook_sha256', 'generated_at', 'already_applied', 'offers', 'matched', 'unmatched', 'quarantined', 'supplier_id', 'import_id'])),
            'currency_confirmation' => 'User confirmed RUB; site retail equals converted RRP.',
            'rate' => $rate,
            'warnings' => [
                'RUB confirmed by user; conversion uses dated NBRB official rate divided by scale.',
                'KARINA site retail uses source RRP; procurement remains discounted by 25%.',
                'Exact candidates require review of model, power and configuration.',
                'Unmatched is not authorization to create a new product.',
                'Existing mappings and supplier links are reported separately and are not automatically trusted.',
            ],
            'imports' => SupplierPriceImport::whereIn('supplier_id', $supplierIds)->orderBy('id')->get(['id', 'supplier_id', 'filename', 'total_rows', 'matched', 'unmatched', 'updated', 'status'])->toArray(),
            'import_item_counts' => SupplierPriceItem::whereIn('supplier_id', $supplierIds)->selectRaw('import_id, match_status, COUNT(*) as row_count')->groupBy('import_id', 'match_status')->get()->toArray(),
            'workbook' => $workbook, 'suppliers' => $suppliers, 'products' => $products,
            'supplier_links' => $links, 'existing_mappings' => $mappings, 'items' => $items,
            'cross_supplier_article_links' => $crossSupplierLinks,
            'cross_supplier_article_mappings' => $crossSupplierMappings,
            'exact_name_products' => $exactNameProducts,
            'creation_candidates' => $creationCandidates,
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
                'eligible_for_hidden_creation_review' => count(array_filter($creationCandidates, fn ($row) => $row['eligible_for_hidden_creation_review'])),
                'creation_blocked' => count(array_filter($creationCandidates, fn ($row) => ! $row['eligible_for_hidden_creation_review'])),
            ],
        ];
        File::put($output, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
        $this->line('metric.audit_items='.count($items));
        $this->line('metric.audit_products='.count($products));

        return self::SUCCESS;
    }
}
