<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditBaniaFallbackBrandCommand extends Command
{
    protected $signature = 'supplier:audit-bania-fallback-brand
        {--apply : Apply suggested brand/category moves}
        {--limit= : Limit rows for preview}';

    protected $description = 'Audit BANIA-linked products that were parked under the fallback Bania brand and suggest real brands/categories.';

    private const SUPPLIER_CODE = 'bania';
    private const FALLBACK_BRAND_SLUG = 'bania';

    private const BRAND_RULES = [
        ['ASTON', 'aston', ['aston', 'астон']],
        ['DoorWood', 'doorwood', ['doorwood', 'door wood']],
        ['Везувий', 'vezuvij', ['vezuviy', 'vezuvij', 'везувий']],
        ['Теплодар', 'teplodar', ['teplodar', 'теплодар', 'былина', 'сибирский утес', 'сибирский утёс', 'сиеста']],
        ['НМК', 'nmk', ['novmk', 'нмк', 'сибирь']],
        ['TMF', 'tmf', ['tmf', 'тмф', 'термофор', 'termofor']],
        ['Эверест', 'everest', ['everest', 'эверест']],
        ['ЭТНА', 'etna', ['etna', 'этна']],
        ['Harvia', 'harvia', ['harvia', 'харвия']],
        ['Grillver', 'grillver', ['grillver', 'гриллвер']],
        ['Факел', 'fakel', ['факел', 'fakel']],
    ];

    private const CATEGORY_RULES = [
        ['pechnoe-i-kaminnoe-lite', ['дверь.*печ', 'дверца.*печ', 'чугунн.*двер']],
        ['dveri-dlya-ban-i-saun', ['doorwood', 'дверь.*саун', 'дверь.*бани']],
        ['elektrokamenki', ['электрокамен', 'электр.*печ', 'harvia']],
        ['mangaly', ['мангал', 'казан', 'грил', 'шашлык', 'печь.*казан', 'мобильная баня']],
        ['kotly', ['котел', 'котёл', 'купер']],
        ['topki', ['топка']],
        ['pechi-kaminy', ['печь-камин', 'печь камин']],
        ['aksessuary-dlya-bani', ['шапк', 'мочал', 'коврик', 'ведро', 'обруч', 'средств', 'камень', 'жадеит', 'нефрит', 'талько', 'стекло']],
        ['drovyanye-pechi-dlya-bani', ['печь.*бан', 'банн.*печ', 'aston', 'былина', 'сибирь']],
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $limit = $this->option('limit') !== null ? max(0, (int) $this->option('limit')) : null;

        $supplierId = (int) DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id');
        $fallbackBrandId = (int) DB::table('brands')->where('slug', self::FALLBACK_BRAND_SLUG)->value('id');

        if ($supplierId <= 0 || $fallbackBrandId <= 0) {
            $this->error('BANIA supplier or fallback brand was not found.');
            return self::FAILURE;
        }

        $query = DB::table('products as p')
            ->join('supplier_products as sp', 'sp.product_id', '=', 'p.id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->where('sp.supplier_id', $supplierId)
            ->where('p.brand_id', $fallbackBrandId)
            ->where('p.is_archived', false)
            ->select([
                'p.id',
                'p.sku',
                'p.name',
                'p.price',
                'p.category_id',
                'c.slug as category_slug',
                'sp.id as supplier_product_id',
                'sp.supplier_article',
                'sp.supplier_name',
                'sp.source_url',
                'sp.price_byn',
            ])
            ->orderBy('p.name');

        if ($limit) {
            $query->limit($limit);
        }

        $rows = $query->get();
        $reportRows = [];
        $groups = [];
        $updated = 0;

        foreach ($rows as $row) {
            $suggestedBrand = $this->suggestBrand($row);
            $suggestedCategory = $this->suggestCategory($row);
            $action = $suggestedBrand['slug'] || $suggestedCategory['slug'] ? 'move_candidate' : 'manual_review';

            $groups[$suggestedBrand['name'] ?: 'manual_review'] = ($groups[$suggestedBrand['name'] ?: 'manual_review'] ?? 0) + 1;

            $reportRows[] = [
                'action' => $action,
                'product_id' => $row->id,
                'sku' => $row->sku,
                'name' => $row->name,
                'current_category' => $row->category_slug,
                'suggested_brand' => $suggestedBrand['name'],
                'suggested_brand_slug' => $suggestedBrand['slug'],
                'suggested_category_slug' => $suggestedCategory['slug'],
                'reason' => trim($suggestedBrand['reason'] . '; ' . $suggestedCategory['reason'], '; '),
                'supplier_article' => $row->supplier_article,
                'supplier_name' => $row->supplier_name,
                'source_url' => $row->source_url,
                'supplier_cost' => $row->price_byn,
                'retail_price' => $row->price,
            ];

            if ($apply && $action === 'move_candidate') {
                $payload = ['updated_at' => now()];
                if ($suggestedBrand['slug'] !== '') {
                    $payload['brand_id'] = $this->ensureBrand($suggestedBrand['name'], $suggestedBrand['slug']);
                }
                if ($suggestedCategory['slug'] !== '') {
                    $categoryId = (int) DB::table('categories')->where('slug', $suggestedCategory['slug'])->value('id');
                    if ($categoryId > 0) {
                        $payload['category_id'] = $categoryId;
                    }
                }

                if (count($payload) > 1) {
                    DB::table('products')->where('id', $row->id)->update($payload);
                    $updated++;
                }
            }
        }

        $path = $this->writeReport($reportRows);

        $this->table(['suggested_brand', 'count'], collect($groups)->sortKeys()->map(fn ($count, $brand) => [$brand, $count])->values()->all());
        $this->table([
            'metric',
            'count',
        ], [
            ['fallback_bania_products', $rows->count()],
            ['move_candidates', collect($reportRows)->where('action', 'move_candidate')->count()],
            ['manual_review', collect($reportRows)->where('action', 'manual_review')->count()],
            ['updated', $updated],
        ]);

        $this->line('Report: ' . $path);

        if (! $apply) {
            $this->line('Review the CSV, then run with --apply if the suggestions are correct.');
        }

        return self::SUCCESS;
    }

    private function suggestBrand(object $row): array
    {
        $haystack = $this->normalize($row->name . ' ' . $row->supplier_name . ' ' . $row->source_url);

        foreach (self::BRAND_RULES as [$name, $slug, $needles]) {
            foreach ($needles as $needle) {
                if ($this->matches($haystack, $needle)) {
                    if ($slug === 'nmk' && $this->matches($haystack, 'сибирский ут')) {
                        continue;
                    }

                    return ['name' => $name, 'slug' => $slug, 'reason' => 'brand matched by "' . $needle . '"'];
                }
            }
        }

        return ['name' => '', 'slug' => '', 'reason' => 'brand not detected'];
    }

    private function suggestCategory(object $row): array
    {
        $haystack = $this->normalize($row->name . ' ' . $row->supplier_name . ' ' . $row->source_url);

        foreach (self::CATEGORY_RULES as [$slug, $patterns]) {
            foreach ($patterns as $pattern) {
                if ($this->matches($haystack, $pattern)) {
                    return ['slug' => $slug, 'reason' => 'category matched by "' . $pattern . '"'];
                }
            }
        }

        return ['slug' => '', 'reason' => 'category not detected'];
    }

    private function matches(string $haystack, string $pattern): bool
    {
        $pattern = $this->normalize($pattern);

        if (! str_contains($pattern, '.*')) {
            return str_contains($haystack, $pattern);
        }

        return (bool) preg_match('/' . str_replace('\.\*', '.*', preg_quote($pattern, '/')) . '/u', $haystack);
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower($value);
        $value = str_replace(['ё', '-', '_', '/', '\\'], ['е', ' ', ' ', ' ', ' '], $value);
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function ensureBrand(string $name, string $slug): int
    {
        $brand = DB::table('brands')->where('slug', $slug)->orWhere('name', $name)->first();
        if ($brand) {
            DB::table('brands')->where('id', $brand->id)->update([
                'name' => $name,
                'slug' => $slug,
                'h1' => $brand->h1 ?: $name,
                'is_active' => true,
                'updated_at' => now(),
            ]);

            return (int) $brand->id;
        }

        return (int) DB::table('brands')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'h1' => $name,
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function writeReport(array $rows): string
    {
        $dir = storage_path('app/reports/bania');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $path = $dir . '/bania-fallback-brand-audit-' . now()->format('Y-m-d-H-i') . '.csv';
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Cannot write report: ' . $path);
        }

        if ($rows === []) {
            fputcsv($handle, ['empty']);
            fclose($handle);
            return $path;
        }

        fputcsv($handle, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return $path;
    }
}
