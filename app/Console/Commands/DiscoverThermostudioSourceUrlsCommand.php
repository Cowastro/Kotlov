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
        {--max-current-attrs= : Process only products with this many or fewer current attribute rows}
        {--source=teplo : Source index to use: teplo, teplodvor, or all}
        {--teplodvor-index=teplodvor_index.json : Local Teplodvor slug index path relative to storage/}
        {--refresh-index : Rebuild cached teplo.by product URL index}';

    protected $description = 'Discover safe Thermostudio source URLs from known card sources without creating products.';

    private const SUPPLIER_CODE = 'thermostudio';
    private const CACHE_PATH = 'supplier-cache/thermostudio-teplo-source-index.json';

    /**
     * @var array<int,array{url:string, slug:string, key:string}>
     */
    private array $sourceIndex = [];
    private string $sourceName = 'teplo.by';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $source = mb_strtolower(trim((string) $this->option('source')));
        if (! in_array($source, ['teplo', 'teplodvor', 'all'], true)) {
            $this->error('Unsupported --source. Use teplo, teplodvor, or all.');
            return self::FAILURE;
        }

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: discovered Thermostudio source URLs will be written.</>'
            : '<fg=yellow;options=bold>DRY RUN: Thermostudio source URL discovery preview only.</>');

        $this->sourceIndex = match ($source) {
            'teplodvor' => $this->loadTeplodvorSourceIndex(),
            'all' => $this->loadCombinedSourceIndex((bool) $this->option('refresh-index')),
            default => $this->loadTeploSourceIndex((bool) $this->option('refresh-index')),
        };

        if ($this->sourceIndex === []) {
            $this->warn('Source index is empty.');
            return self::FAILURE;
        }

        $brands = $this->brandOptions();
        $limit = max(0, (int) $this->option('limit'));
        $offset = max(0, (int) $this->option('offset'));
        $force = (bool) $this->option('force');

        $attributeCounts = DB::table('product_attribute_values')
            ->select('product_id', DB::raw('COUNT(*) as attribute_rows'))
            ->groupBy('product_id');

        $query = DB::table('supplier_products as sp')
            ->join('suppliers as s', 's.id', '=', 'sp.supplier_id')
            ->join('products as p', 'p.id', '=', 'sp.product_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoinSub($attributeCounts, 'pav', 'pav.product_id', '=', 'p.id')
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

        $maxCurrentAttrs = trim((string) $this->option('max-current-attrs'));
        if ($maxCurrentAttrs !== '') {
            $query->whereRaw('COALESCE(pav.attribute_rows, 0) <= ?', [max(0, (int) $maxCurrentAttrs)]);
        }

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
        } elseif ($offset > 0) {
            $query->limit(2147483647);
        }
        if ($offset > 0) {
            $query->offset($offset);
        }

        $rows = $query->orderBy('p.id')->get();
        $this->info(sprintf('Thermostudio supplier rows: %d (processing %d, offset %d)', $total, $rows->count(), $offset));
        $this->info(sprintf('Source index: %d %s product URLs', count($this->sourceIndex), $this->sourceName));

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
    private function loadTeploSourceIndex(bool $refresh): array
    {
        $this->sourceName = 'teplo.by';

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
     * @return array<int,array{url:string, slug:string, key:string}>
     */
    private function loadTeplodvorSourceIndex(): array
    {
        $this->sourceName = 'teplodvor.by';

        $path = storage_path(trim((string) $this->option('teplodvor-index')));
        if (! is_file($path)) {
            $this->warn('Teplodvor index not found: ' . $path);
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            return [];
        }

        $rows = [];
        foreach ($data as $slug => $url) {
            if (! is_string($slug) || ! is_string($url) || $slug === '' || $url === '') {
                continue;
            }

            $rows[] = [
                'url' => $url,
                'slug' => $slug,
                'key' => $this->slugKey($slug),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int,array{url:string, slug:string, key:string}>
     */
    private function loadCombinedSourceIndex(bool $refresh): array
    {
        $teplo = $this->loadTeploSourceIndex($refresh);
        $teplodvor = $this->loadTeplodvorSourceIndex();
        $this->sourceName = 'teplo.by + teplodvor.by';

        $byUrl = [];
        foreach (array_merge($teplo, $teplodvor) as $row) {
            $byUrl[$row['url']] = $row;
        }

        return array_values($byUrl);
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
            $kermi = $this->matchKermi(trim($article . ' ' . $name));
            if ($kermi) {
                return $kermi;
            }
        }

        if ($brand !== '' && str_contains(mb_strtolower($brand), 'buderus')) {
            $buderus = $this->matchBuderusRadiator(trim($article . ' ' . $name));
            if ($buderus) {
                return $buderus;
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
    private function matchKermi(string $text): ?array
    {
        $compact = strtoupper(preg_replace('/[^A-Z0-9]+/i', '', $text) ?? '');
        if (! preg_match('/F(?:KO|TV)(?P<type>\d{2})(?P<height>\d{3})(?P<length>\d{3})\d?/i', $compact, $match)
            && ! preg_match('/F(?:KO|TV)(?P<type>\d{2})(?P<height>\d{4})(?P<length>\d{3,4})/i', $compact, $match)) {
            return null;
        }

        $type = (int) $match['type'];
        $heightCode = (string) $match['height'];
        $lengthCode = (string) $match['length'];
        $height = strlen($heightCode) === 3 ? (int) $heightCode * 10 : (int) $heightCode;
        $length = strlen($lengthCode) === 3 ? (int) $lengthCode * 10 : (int) substr($lengthCode, 0, -1) * 10;
        if ($type <= 0 || $height <= 0 || $length <= 0) {
            return null;
        }

        $needles = [
            'kermi-kompakt-' . $type . '-' . $height . '-' . $length,
            'kermi-kompakt-' . $type . $height . $length,
            'kermi-kompakt-' . $type . str_pad((string) $height, 3, '0', STR_PAD_LEFT) . str_pad((string) $length, 4, '0', STR_PAD_LEFT),
        ];

        foreach (array_unique($needles) as $needle) {
            $matches = $this->findByNeedle($needle, 'Kermi');
            if (count($matches) === 1) {
                return ['url' => $matches[0]['url'], 'confidence' => 'kermi_article_dimensions'];
            }
        }

        foreach ($this->kermiGeneratedUrls($type, $height, $length) as $url) {
            if ($this->sourceUrlExists($url)) {
                return ['url' => $url, 'confidence' => 'kermi_generated_verified'];
            }
        }

        return null;
    }

    /**
     * @return array<int,string>
     */
    private function kermiGeneratedUrls(int $type, int $height, int $length): array
    {
        $compact = sprintf('%d%d%d', $type, $height, $length);

        return [
            sprintf('https://teplo.by/product/stalnoj-radiator-kermi-kompakt-%d-%d-%d/', $type, $height, $length),
            sprintf('https://www.teplodvor.by/shop/raditory/stalnye/radiator-stalnoy-kermi-kompakt-%s', $compact),
        ];
    }

    /**
     * @return array{url:string, confidence:string}|null
     */
    private function matchBuderusRadiator(string $text): ?array
    {
        $normalized = str_replace(['х', 'Х', '×'], 'x', $text);
        if (! preg_match('/\b(?P<type>\d{2})\s*[\/x]\s*(?P<height>\d{3,4})\s*[\/x]\s*(?P<length>\d{3,4})\b.*?\b(?P<profile>VK|K)\s*[- ]?\s*Profil\b/iu', $normalized, $match)
            && ! preg_match('/\b(?P<profile>VK|K)\s*[- ]?\s*Profil\b.*?\b(?P<type>\d{2})\s*[\/x]\s*(?P<height>\d{3,4})\s*[\/x]\s*(?P<length>\d{3,4})\b/iu', $normalized, $match)) {
            return null;
        }

        $type = (int) $match['type'];
        $height = (int) $match['height'];
        $length = (int) $match['length'];
        $profile = mb_strtolower((string) $match['profile']);
        if ($type <= 0 || $height <= 0 || $length <= 0 || ! in_array($profile, ['k', 'vk'], true)) {
            return null;
        }

        $compact = $type . $height . $length;
        $needles = [
            sprintf('buderus-logatrend-%s-profil-%d-%dx%d', $profile, $type, $height, $length),
            sprintf('buderus-logatrend-%s-profil-%d-%d-%d', $profile, $type, $height, $length),
            sprintf('buderus-%s-profil-%s', $profile, $compact),
            sprintf('buderus-%s-profil-%d-%d-%d', $profile, $type, $height, $length),
            sprintf('buderus-%d-%d-%d-%s-profil', $type, $height, $length, $profile),
        ];

        foreach (array_unique($needles) as $needle) {
            $matches = $this->findByNeedle($needle, 'Buderus');
            if (count($matches) === 1) {
                return ['url' => $matches[0]['url'], 'confidence' => 'buderus_radiator_dimensions'];
            }
        }

        $matches = array_values(array_filter($this->sourceIndex, function (array $row) use ($profile, $compact, $type, $height, $length): bool {
            $key = (string) $row['key'];
            if (! str_contains($key, 'buderus') || ! str_contains($key, $profile . '-profil')) {
                return false;
            }

            return str_contains($key, $compact)
                || str_contains($key, sprintf('%d-%dx%d', $type, $height, $length))
                || str_contains($key, sprintf('%d-%d-%d', $type, $height, $length));
        }));

        if (count($matches) === 1) {
            return ['url' => $matches[0]['url'], 'confidence' => 'buderus_radiator_dimensions'];
        }

        return null;
    }

    private function sourceUrlExists(string $url): bool
    {
        try {
            $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->timeout(12)
                ->retry(1, 400)
                ->get($url);
        } catch (\Throwable) {
            return false;
        }

        if (! $response->ok()) {
            return false;
        }

        $body = mb_strtolower(mb_substr($response->body(), 0, 2000));

        return ! str_contains($body, '404')
            && ! str_contains($body, 'страница не найдена')
            && ! str_contains($body, 'page not found');
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
            return (mb_strlen($part) >= 2 || in_array($part, ['e', 'x', 'w', 's'], true))
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
