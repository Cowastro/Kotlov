<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InspectProductPriceCommand extends Command
{
    protected $signature = 'products:inspect-price
        {--sku= : Product SKU}
        {--article= : Supplier article}
        {--brand= : Product brand name}
        {--limit=20 : Maximum rows to show}';

    protected $description = 'Read-only product and supplier price inspection.';

    public function handle(): int
    {
        $sku = trim((string) $this->option('sku'));
        $article = trim((string) $this->option('article'));
        $brand = trim((string) $this->option('brand'));
        $limit = max(1, min(200, (int) $this->option('limit')));

        if ($sku === '' && $article === '' && $brand === '') {
            $this->error('Pass --sku, --article or --brand.');

            return self::FAILURE;
        }

        $query = DB::table('products as p')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoin('supplier_products as sp', 'sp.product_id', '=', 'p.id')
            ->leftJoin('suppliers as s', 's.id', '=', 'sp.supplier_id')
            ->select([
                'p.id',
                'p.sku',
                'p.name',
                'p.price as product_price',
                'p.in_stock as product_in_stock',
                'p.is_active',
                'p.is_archived',
                'b.name as brand',
                'c.name as category',
                's.code as supplier_code',
                's.name as supplier_name',
                'sp.supplier_article',
                'sp.supplier_name as supplier_product_name',
                'sp.price as supplier_price',
                'sp.price_byn',
                'sp.in_stock as supplier_in_stock',
                'sp.quantity',
                'sp.source_url',
            ])
            ->orderBy('p.id');

        if ($sku !== '') {
            $query->where('p.sku', $sku);
        }

        if ($article !== '') {
            $needle = $this->normalizeArticle($article);
            $query->where(function ($sub) use ($article, $needle): void {
                $sub->where('sp.supplier_article', $article)
                    ->orWhere('sp.supplier_article', 'like', '%' . $article . '%');

                if ($needle !== $article) {
                    $sub->orWhere('sp.supplier_article', 'like', '%' . $needle . '%')
                        ->orWhere('p.name', 'like', '%' . $needle . '%');
                }

                $sub->orWhere('p.name', 'like', '%' . $article . '%');
            });
        }

        if ($brand !== '') {
            $query->where('b.name', $brand);
        }

        $rows = $query->limit($limit)->get();

        if ($rows->isEmpty()) {
            $this->warn('No rows found.');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'sku', 'brand', 'category', 'product_price', 'supplier', 'article', 'supplier_price', 'price_byn', 'qty', 'stock', 'source', 'name'],
            $rows->map(fn ($row): array => [
                $row->id,
                $row->sku,
                $row->brand,
                $row->category,
                $this->money($row->product_price),
                $row->supplier_code ?: $row->supplier_name ?: '-',
                $row->supplier_article ?: '-',
                $this->money($row->supplier_price),
                $this->money($row->price_byn),
                $row->quantity ?? '-',
                $this->stockText($row),
                $row->source_url ?: '-',
                mb_substr((string) $row->name, 0, 70),
            ])->all()
        );

        return self::SUCCESS;
    }

    private function normalizeArticle(string $value): string
    {
        return strtoupper(preg_replace('/[^A-ZА-ЯЁ0-9]+/iu', '', $value) ?? '');
    }

    private function money(mixed $value): string
    {
        return $value !== null && $value !== '' ? number_format((float) $value, 2, '.', '') : '-';
    }

    private function stockText(object $row): string
    {
        $supplierStock = $row->supplier_in_stock === null ? null : (bool) $row->supplier_in_stock;
        $productStock = $row->product_in_stock === null ? null : (bool) $row->product_in_stock;

        return sprintf(
            'product=%s supplier=%s active=%s archived=%s',
            $productStock === null ? '-' : ($productStock ? 'yes' : 'no'),
            $supplierStock === null ? '-' : ($supplierStock ? 'yes' : 'no'),
            (bool) $row->is_active ? 'yes' : 'no',
            (bool) $row->is_archived ? 'yes' : 'no',
        );
    }
}
