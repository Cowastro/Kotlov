<?php

namespace App\Console\Commands;

use App\Services\ProductSourceEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SanitizeProductContentHtmlCommand extends Command
{
    protected $signature = 'products:sanitize-content-html
        {--apply : Write sanitized content}
        {--brand= : Brand name or slug}
        {--supplier= : Supplier code filter}
        {--sku= : Single product SKU}
        {--id=* : Product ID filter, can be repeated}
        {--active-only : Only active products}
        {--not-archived : Only not archived products}
        {--limit=100 : Rows to process, 0 means all}';

    protected $description = 'Sanitize stored product HTML descriptions and remove foreign markup, images and inline styles.';

    public function handle(ProductSourceEnricher $enricher): int
    {
        $apply = (bool) $this->option('apply');
        $limit = max(0, (int) $this->option('limit'));

        $query = DB::table('products as p')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->select('p.id', 'p.sku', 'p.name', 'p.content', 'b.name as brand')
            ->whereNotNull('p.content')
            ->where('p.content', '<>', '')
            ->orderBy('p.id');

        if ($supplier = trim((string) $this->option('supplier'))) {
            $query
                ->join('supplier_products as sp', 'sp.product_id', '=', 'p.id')
                ->join('suppliers as s', 's.id', '=', 'sp.supplier_id')
                ->where('s.code', $supplier);
        }

        if ($brand = trim((string) $this->option('brand'))) {
            $query->where(function ($q) use ($brand) {
                $q->where('b.name', $brand)->orWhere('b.slug', $brand);
            });
        }

        if ($sku = trim((string) $this->option('sku'))) {
            $query->where('p.sku', $sku);
        }

        $ids = array_values(array_filter(array_map('intval', (array) $this->option('id'))));
        if ($ids !== []) {
            $query->whereIn('p.id', $ids);
        }

        if ((bool) $this->option('not-archived')) {
            $query->where('p.is_archived', false);
        }

        if ((bool) $this->option('active-only') && Schema::hasColumn('products', 'is_active')) {
            $query->where('p.is_active', true);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $rows = $query->get();
        $stats = [
            'checked' => 0,
            'changed' => 0,
            'written' => 0,
            'images_removed' => 0,
            'styles_removed' => 0,
            'bad_blocks_removed' => 0,
        ];
        $changedRows = [];

        foreach ($rows as $row) {
            $stats['checked']++;

            $original = (string) $row->content;
            $sanitized = $enricher->sanitizeDescriptionHtml($original);

            if (trim($original) === trim($sanitized)) {
                continue;
            }

            $stats['changed']++;
            $stats['images_removed'] += max(0, $this->countMatches('/<img\b/iu', $original) - $this->countMatches('/<img\b/iu', $sanitized));
            $stats['styles_removed'] += max(0, $this->countMatches('/\sstyle\s*=/iu', $original) - $this->countMatches('/\sstyle\s*=/iu', $sanitized));
            $stats['bad_blocks_removed'] += max(0, $this->countMatches('/<(script|style|iframe|object|embed|svg|canvas|picture|video|audio|form|button|input|select|textarea|table)\b/iu', $original));

            if ($apply) {
                DB::table('products')->where('id', $row->id)->update([
                    'content' => $sanitized,
                    'updated_at' => now(),
                ]);
                $stats['written']++;
            }

            if (count($changedRows) < 80) {
                $changedRows[] = [
                    $row->id,
                    $row->sku,
                    $row->brand ?: '-',
                    mb_strimwidth((string) $row->name, 0, 70, '...'),
                ];
            }
        }

        $this->line($apply ? 'APPLY: sanitized content was written.' : 'DRY RUN: database will not be changed.');
        $this->table(['metric', 'count'], collect($stats)->map(fn ($count, $metric) => [$metric, $count])->all());

        if ($changedRows !== []) {
            $this->table(['ID', 'SKU', 'Brand', 'Product'], $changedRows);
        }

        return self::SUCCESS;
    }

    private function countMatches(string $pattern, string $value): int
    {
        return preg_match_all($pattern, $value) ?: 0;
    }
}
