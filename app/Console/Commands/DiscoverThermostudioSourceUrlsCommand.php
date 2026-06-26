<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DiscoverThermostudioSourceUrlsCommand extends Command
{
    protected $signature = 'supplier:discover-thermostudio-sources
        {--apply : Write discovered source_url values to supplier_products}
        {--brand=* : Process only selected brands, repeatable or comma-separated}
        {--limit=50 : Max supplier rows to inspect, 0 means all}
        {--offset=0 : Skip supplier rows}
        {--force : Re-check rows that already have source_url}
        {--refresh-index : Rebuild cached teplo.by product URL index}';

    protected $description = 'Discover safe Thermostudio source URLs from known card sources without creating products.';

    private const SUPPLIER_CODE = 'thermostudio';
    private const CACHE_PATH = 'supplier-cache/thermostudio-teplo-source-index.json';

    /**
     * @var array<int,array{url:string, slug:string, key:string}>
     */
    private array $sourceIndex = [];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->line($apply
            ? '<fg=red;options=bold>APPLY: discovered Thermostudio source URLs will be written.</>'
            : '<fg=yellow;options=bold>DRY RUN: Thermostudio source URL discovery preview only.</>');

        $this->sourceIndex = $this->loadSourceIndex((bool) $this->option('refresh-index'));
        if ($this->sourceIndex === []) {
            $this->warn('Source index is empty.');
            return self::FAILURE;
        }

        $brands = $this->brandOptions();
        $limit = max(0, (int) $this->option('limit'));
        $offset = max(0, (int) $this->option('offset'));
        $force = (bool) $this->option('force');

        $query = DB::table('supplier_products as sp')
            ->join('suppliers as s', 's.id', '=', 'sp.supplier_id')
            ->join('products as p', 'p.id', '=', 'sp.product_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->where('s.code', self::SUPPLIER_CODE)
            ->where('p.is_archived', false)
            ->select([
                'sp.id as supplier_product_id',
                'sp.source_url',
                'sp.supplier_article',
                'sp.supplier_name',
                'p.id as product_id',
                'p.name as product_name',
                'p.sku',
                'b.name as brand_name',
            ]);

        if (! $force) {
            $query->where(function ($q): void {
                $q->whereNull('sp.source_url')
                    ->orWhere('sp.source_url', '')
                    ->orWhere('sp.source_url', 'like', '%docs.google.com/spreadsheets%');
            });
        }

        if ($brands !== []) {
            $query->where(function ($q) use ($brands): void {
                foreach ($brands as $brand) {
                    $q->orWhere('b.name', 'like', '%' . $brand . '%')
                        ->orWhere('sp.supplier_name', 'like', '%' . $brand . '%');
                }
            });
        }

        $total = (clone $query)->count();
        if ($limit > 0) {
            $query->limit($limit);
        }
        if ($offset > 0) {
            $query->offset($offset);
        }

        $rows = $query->orderBy('p.id')->get();
        $this->info(sprintf('Thermostudio supplier rows: %d (processing %d, offset %d)', $total, $rows->count(), $offset));
        $this->info(sprintf('Source index: %d teplo.by product URLs', count($this->sourceIndex)));

        $stats = array_fill_keys(['processed', 'matched', 'written', 'skipped', 'ambiguous'], 0);
        $preview = [];
        $now = now();

        foreach ($rows as $row) {
            $stats['processed']++;
            $match = $this->matchRow($row);

            if (! $match) {
                $stats['skipped']++;
                continue;
            }

            if (($match['confidence'] ?? '') === 'ambiguous') {
                $stats['ambiguous']++;
                continue;
            }

            $stats['matched']++;
            $preview[] = [
                $row->product_id,
                mb_substr((string) ($row->brand_name ?? '-'), 0, 14),
                mb_substr((string) $row->supplier_article, 0, 22),
                mb_substr((string) $row->product_name, 0, 42),
                $match['confidence'],
                mb_substr($match['url'], 0, 72),
            ];

            if ($apply) {
                DB::table('supplier_products')->where('id', $row->supplier_product_id)->update([
                    'source_url' => $match['url'],
                    'updated_at' => $now,
                ]);
                $stats['written']++;
            }
        }

        if ($preview !== []) {
            $this->table(['product', 'brand', 'article', 'name', 'confidence', 'source_url'], array_slice($preview, 0, 30));
            if (count($preview) > 30) {
                $this->line('... ' . (count($preview) - 30) . ' more matched rows');
            }
        }

        $this->table(['metric', 'count'], array_map(
            fn (string $key, int $value): array => [$key, $value],
            array_keys($stats),
            array_values($stats)
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<int,array{url:string, slug:string, key:string}>
     */
    private function loadSourceIndex(bool $refresh): array
    {
        if (! $refresh && Storage::exists(self::CACHE_PATH)) {
            $cached = json_decode((string) Storage::get(self::CACHE_PATH), true);
            if (is_array($cached) && $cached !== []) {
                return array_values(array_filter($cached, fn ($row): bool => is_array($row) && ! empty($row['url'])));
            }
        }

        $urls = [];
        foreach ([
            'https://teplo.by/product-sitemap1.xml',
            'https://teplo.by/product-sitemap2.xml',
            'https://teplo.by/product-sitemap3.xml',
        ] as $sitemap) {
            $this->line('Loading ' . $sitemap);
            $xml = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->timeout(60)
                ->retry(2, 500)
                ->get($sitemap)
                ->body();

            if (! preg_match_all('~<loc>(https://teplo\.by/product/[^<]+)</loc>~iu', $xml, $matches)) {
                continue;
            }

            foreach ($matches[1] as $url) {
                $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $slug = trim((string) basename(parse_url($url, PHP_URL_PATH) ?: ''), '/');
                if ($slug === '') {
                    continue;
                }

                $urls[$url] = [
                    'url' => $url,
                    'slug' => $slug,
                    'key' => $this->slugKey($slug),
                ];
            }
        }

        $index = array_values($urls);
        Storage::put(self::CACHE_PATH, json_encode($index, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $index;
    }

    /**
     * @return array{url:string, confidence:string}|null
     */
    private function matchRow(object $row): ?array
    {
        $brand = (string) ($row->brand_name ?: '');
        $article = (string) ($row->supplier_article ?: '');
        $name = trim((string) $row->product_name . ' ' . (string) $row->supplier_name);

        if ($brand !== '' && str_contains(mb_strtolower($brand), 'kermi')) {
            $kermi = $this->matchKermi($article);
            if ($kermi) {
                return $kermi;
            }
        }

        foreach ($this->articleCandidates($article) as $candidate) {
            $matches = $this->findByNeedle($candidate, $brand);
            if (count($matches) === 1) {
                return ['url' => $matches[0]['url'], 'confidence' => 'article_url'];
            }
        }

        $modelNeedles = $this->modelNeedles($name, $brand);
        foreach ($modelNeedles as $needle) {
            $matches = $this->findByNeedle($needle, $brand);
            if (count($matches) === 1) {
                return ['url' => $matches[0]['url'], 'confidence' => 'model_url'];
            }
        }

        return null;
    }

    /**
     * @return array{url:string, confidence:string}|null
     */
    private function matchKermi(string $article): ?array
    {
        $compact = strtoupper(preg_replace('/[^A-Z0-9]+/i', '', $article) ?? '');
        if (! preg_match('/^F(?:KO|TV)(\d{2})(\d{4})(\d{3,4})/i', $compact, $match)) {
            return null;
        }

        $type = (int) $match[1];
        $height = (int) $match[2];
        $lengthCode = $match[3];
        $length = (int) substr($lengthCode, 0, -1) * 10;
        if ($type <= 0 || $height <= 0 || $length <= 0) {
            return null;
        }

        $needle = 'kermi-kompakt-' . $type . '-' . $height . '-' . $length;
        $matches = $this->findByNeedle($needle, 'Kermi');
        if (count($matches) === 1) {
            return ['url' => $matches[0]['url'], 'confidence' => 'kermi_article_dimensions'];
        }

        return null;
    }

    /**
     * @return array<int,array{url:string, slug:string, key:string}>
     */
    private function findByNeedle(string $needle, string $brand = ''): array
    {
        $needle = $this->slugKey($needle);
        if ($needle === '' || mb_strlen($needle) < 4) {
            return [];
        }

        $brandKey = $this->slugKey($brand);

        return array_values(array_filter($this->sourceIndex, function (array $row) use ($needle, $brandKey): bool {
            $key = (string) $row['key'];
            if ($brandKey !== '' && ! str_contains($key, $brandKey)) {
                return false;
            }

            return str_contains($key, $needle);
        }));
    }

    /**
     * @return array<int,string>
     */
    private function articleCandidates(string $article): array
    {
        $article = trim($article);
        if ($article === '') {
            return [];
        }

        $candidates = [$article];
        $compact = preg_replace('/[^A-Za-zА-Яа-яЁё0-9]+/u', '', $article) ?? '';
        if ($compact !== '' && $compact !== $article) {
            $candidates[] = $compact;
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @return array<int,string>
     */
    private function modelNeedles(string $name, string $brand): array
    {
        $name = $this->slugKey($name);
        $brand = $this->slugKey($brand);
        if ($brand !== '') {
            $name = trim(preg_replace('/\b' . preg_quote($brand, '/') . '\b/u', ' ', $name) ?? $name);
        }

        $parts = array_values(array_filter(explode('-', $name), function (string $part): bool {
            return (mb_strlen($part) >= 2 || in_array($part, ['x', 'w', 's'], true))
                && ! in_array($part, ['kotel', 'kotly', 'gazovyj', 'gazovyi', 'gazovyye', 'radiator', 'stalnoj', 'vodonagrevatel', 'bojler', 'konturnyi'], true);
        }));

        $needles = [];
        for ($size = min(5, count($parts)); $size >= 2; $size--) {
            $slice = array_slice($parts, 0, $size);
            $needle = implode('-', $slice);
            if (mb_strlen($needle) >= 6) {
                $needles[] = $needle;
            }
        }

        if ($this->slugKey($brand) === 'viessmann') {
            foreach ($needles as $needle) {
                if (str_contains($needle, 'vitopend-100-a1')) {
                    $needles[] = str_replace('vitopend-100-a1', 'vitopend-100-w-a1', $needle);
                }
            }
        }

        return array_values(array_unique($needles));
    }

    private function slugKey(string $value): string
    {
        return trim(Str::slug($value));
    }

    /**
     * @return array<int,string>
     */
    private function brandOptions(): array
    {
        $brands = [];
        foreach ((array) $this->option('brand') as $value) {
            foreach (explode(',', (string) $value) as $brand) {
                $brand = trim($brand);
                if ($brand !== '') {
                    $brands[] = $brand;
                }
            }
        }

        return array_values(array_unique($brands));
    }
}
