<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReportBaniaMissingAssetsCommand extends Command
{
    protected $signature = 'supplier:report-bania-missing-assets
        {--missing=images : images, content, either, both}
        {--brand= : Filter by brand name fragment}
        {--category= : Filter by category slug}
        {--limit= : Limit rows}
        {--show=25 : Rows to print in the console}';

    protected $description = 'Build a CSV selection of BANIA products missing photos/content with safe source hints.';

    private const SUPPLIER_CODE = 'bania';

    private const SOURCE_HINTS = [
        'vezuviy' => 'https://vezuviy.su/',
        'teplodar' => 'https://www.teplodar.ru/',
        'tmf' => 'https://tmf-shop.ru/',
        'doorwood' => 'https://doorwood.ru/',
        'harvia' => 'https://www.harvia.com/',
        'prosept' => 'https://www.prosept.ru/',
        'aston' => 'https://aston-pech.ru/',
        'mangaly' => 'https://bania.by/piknik-dosug-shashlyk-gril/mangaly',
        'kazany' => 'https://bania.by/piknik-dosug-shashlyk-gril/kazany',
        'pechi-dlya-kazana' => 'https://bania.by/piknik-dosug-shashlyk-gril/pechi-dlja-kazana',
        'komplektuyushchie-dlya-mangala' => 'https://bania.by/piknik-dosug-shashlyk-gril/komplektuyushchie-dlya-mangala',
        'mobilnaja-banja' => 'https://bania.by/piknik-dosug-shashlyk-gril/mobilnaja-banja',
        'bania' => 'https://bania.by/',
    ];

    public function handle(): int
    {
        $missing = strtolower((string) $this->option('missing'));
        if (! in_array($missing, ['images', 'content', 'either', 'both'], true)) {
            $this->error('--missing must be one of: images, content, either, both');
            return self::FAILURE;
        }

        $supplierId = (int) DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id');
        if ($supplierId <= 0) {
            $this->error('Supplier BANIA.by not found.');
            return self::FAILURE;
        }

        $query = DB::table('supplier_products as sp')
            ->join('products as p', 'p.id', '=', 'sp.product_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->where('sp.supplier_id', $supplierId)
            ->where('p.is_archived', false)
            ->select([
                'p.id',
                'p.sku',
                'p.name',
                'p.slug',
                'p.images',
                'p.content',
                'p.short_description',
                'p.is_active',
                'b.name as brand',
                'c.slug as category_slug',
                'c.name as category_name',
                'sp.supplier_article',
                'sp.supplier_name',
                'sp.match_status',
                'sp.stock_text',
                'sp.source_url',
                'sp.updated_at as supplier_updated_at',
            ]);

        $this->applyMissingFilter($query, $missing);

        if ($brand = trim((string) $this->option('brand'))) {
            $needle = '%' . mb_strtolower($brand) . '%';
            $query->where(function ($q) use ($needle) {
                $q->whereRaw('LOWER(b.name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(p.name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(sp.supplier_name) LIKE ?', [$needle]);
            });
        }

        if ($category = trim((string) $this->option('category'))) {
            $query->where('c.slug', $category);
        }

        if ($this->option('limit') !== null) {
            $query->limit(max(1, (int) $this->option('limit')));
        }

        $rows = $query
            ->orderBy('b.name')
            ->orderBy('c.slug')
            ->orderBy('p.id')
            ->get()
            ->map(fn ($row) => $this->formatRow($row));

        $reportDir = storage_path('app/reports/bania');
        if (! is_dir($reportDir)) {
            mkdir($reportDir, 0775, true);
        }

        $path = $reportDir . '/bania-missing-assets-' . now()->format('Y-m-d-H-i') . '.csv';
        $this->writeCsv($path, $rows->all());

        $this->info('Rows: ' . $rows->count());
        $this->info('Report: ' . $path);

        $this->newLine();
        $this->table(
            ['source_hint', 'count'],
            $rows->groupBy('source_hint')->map(fn ($items, $source) => [$source, $items->count()])->values()->all()
        );

        $show = max(0, (int) $this->option('show'));
        if ($show > 0 && $rows->isNotEmpty()) {
            $this->newLine();
            $this->table(
                ['sku', 'brand', 'category', 'name', 'source_hint'],
                $rows->take($show)->map(fn ($row) => [
                    $row['sku'],
                    $row['brand'],
                    $row['category_slug'],
                    Str::limit($row['name'], 70),
                    $row['source_hint'],
                ])->all()
            );
        }

        return self::SUCCESS;
    }

    private function applyMissingFilter($query, string $missing): void
    {
        $images = function ($q) {
            $q->whereNull('p.images')
                ->orWhere('p.images', '')
                ->orWhere('p.images', '[]')
                ->orWhereRaw('JSON_LENGTH(p.images) = 0');
        };

        $content = function ($q) {
            $q->whereNull('p.content')
                ->orWhere('p.content', '')
                ->orWhereRaw('CHAR_LENGTH(TRIM(p.content)) < 180');
        };

        match ($missing) {
            'images' => $query->where($images),
            'content' => $query->where($content),
            'either' => $query->where(fn ($q) => $q->where($images)->orWhere($content)),
            'both' => $query->where($images)->where($content),
        };
    }

    private function formatRow(object $row): array
    {
        $name = (string) ($row->supplier_name ?: $row->name);
        $brand = (string) ($row->brand ?: $this->guessBrand($name));

        return [
            'id' => (string) $row->id,
            'sku' => (string) $row->sku,
            'brand' => $brand,
            'category_slug' => (string) $row->category_slug,
            'category_name' => (string) $row->category_name,
            'name' => (string) $row->name,
            'supplier_article' => (string) $row->supplier_article,
            'supplier_name' => (string) $row->supplier_name,
            'match_status' => (string) $row->match_status,
            'stock_text' => (string) $row->stock_text,
            'has_images' => $this->hasImages($row->images) ? 'yes' : 'no',
            'has_content' => trim(strip_tags((string) $row->content)) !== '' ? 'yes' : 'no',
            'source_url' => (string) $row->source_url,
            'source_hint' => $this->sourceHint($brand, $name),
            'supplier_updated_at' => (string) $row->supplier_updated_at,
        ];
    }

    private function guessBrand(string $name): string
    {
        $normalized = $this->normalize($name);

        return match (true) {
            str_contains($normalized, 'vezuv') || str_contains($normalized, 'везув') => 'Везувий',
            str_contains($normalized, 'teplodar') || str_contains($normalized, 'теплодар') => 'Теплодар',
            str_contains($normalized, 'tmf') || str_contains($normalized, 'термофор') => 'TMF',
            str_contains($normalized, 'doorwood') || str_contains($normalized, 'door wood') => 'DoorWood',
            str_contains($normalized, 'harvia') => 'Harvia',
            str_contains($normalized, 'prosept') || str_contains($normalized, 'просепт') => 'PROSEPT',
            str_contains($normalized, 'aston') || str_contains($normalized, 'астон') => 'ASTON',
            default => '',
        };
    }

    private function sourceHint(string $brand, string $name): string
    {
        $normalized = $this->normalize($brand . ' ' . $name);

        return match (true) {
            str_contains($normalized, 'vezuv') || str_contains($normalized, 'везув') => self::SOURCE_HINTS['vezuviy'],
            str_contains($normalized, 'teplodar') || str_contains($normalized, 'теплодар') => self::SOURCE_HINTS['teplodar'],
            str_contains($normalized, 'tmf') || str_contains($normalized, 'термофор') => self::SOURCE_HINTS['tmf'],
            str_contains($normalized, 'doorwood') || str_contains($normalized, 'door wood') => self::SOURCE_HINTS['doorwood'],
            str_contains($normalized, 'harvia') => self::SOURCE_HINTS['harvia'],
            str_contains($normalized, 'prosept') || str_contains($normalized, 'просепт') => self::SOURCE_HINTS['prosept'],
            str_contains($normalized, 'aston') || str_contains($normalized, 'астон') => self::SOURCE_HINTS['aston'],
            str_contains($normalized, 'мобильн') && str_contains($normalized, 'бан') => self::SOURCE_HINTS['mobilnaja-banja'],
            str_contains($normalized, 'комплект') && str_contains($normalized, 'мангал') => self::SOURCE_HINTS['komplektuyushchie-dlya-mangala'],
            str_contains($normalized, 'печ') && str_contains($normalized, 'казан') => self::SOURCE_HINTS['pechi-dlya-kazana'],
            str_contains($normalized, 'казан') => self::SOURCE_HINTS['kazany'],
            str_contains($normalized, 'мангал') || str_contains($normalized, 'грил') || str_contains($normalized, 'шашлык') => self::SOURCE_HINTS['mangaly'],
            default => self::SOURCE_HINTS['bania'],
        };
    }

    private function hasImages(mixed $value): bool
    {
        if (is_array($value)) {
            return array_values(array_filter($value)) !== [];
        }

        if (! is_string($value) || trim($value) === '' || trim($value) === '[]') {
            return false;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) && array_values(array_filter($decoded)) !== [];
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function writeCsv(string $path, array $rows): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Could not write report: ' . $path);
        }

        if ($rows === []) {
            fputcsv($handle, ['id', 'sku', 'brand', 'category_slug', 'category_name', 'name', 'supplier_article', 'supplier_name', 'match_status', 'stock_text', 'has_images', 'has_content', 'source_url', 'source_hint', 'supplier_updated_at']);
            fclose($handle);
            return;
        }

        fputcsv($handle, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
    }
}
