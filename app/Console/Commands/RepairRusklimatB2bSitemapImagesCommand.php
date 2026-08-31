<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductSourceEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RepairRusklimatB2bSitemapImagesCommand extends Command
{
    protected $signature = 'supplier:repair-rusklimat-b2b-sitemap-images
        {--apply : Download and write images; default is dry-run}
        {--limit=50 : Max products to process, 0 means no limit}
        {--offset=0 : Skip matched products}
        {--brand= : Brand name filter}
        {--sleep=500 : Delay between product requests, ms}';

    protected $description = 'Repair active Rusklimat photos from b2b.rusklimat.com sitemap by conservative slug/model matching.';

    private const SUPPLIER_CODE = 'rusklimat';
    private const SITEMAP_URL = 'https://b2b.rusklimat.com/sitemap.xml';

    private array $stats = [
        'b2b_urls' => 0,
        'broken_products' => 0,
        'matched' => 0,
        'processed' => 0,
        'would_repair' => 0,
        'repaired' => 0,
        'images_found' => 0,
        'images_saved' => 0,
        'no_match' => 0,
        'no_source_images' => 0,
        'errors' => 0,
    ];

    public function handle(ProductSourceEnricher $enricher): int
    {
        $supplierId = (int) DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id');
        if ($supplierId <= 0) {
            $this->error('Supplier not found: ' . self::SUPPLIER_CODE);

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $limit = max(0, (int) $this->option('limit'));
        $offset = max(0, (int) $this->option('offset'));
        $sleep = max(300, (int) $this->option('sleep'));
        $brandFilter = trim((string) $this->option('brand'));

        $sitemap = $this->loadB2bSitemap();
        if ($sitemap === []) {
            $this->error('No B2B product URLs loaded.');

            return self::FAILURE;
        }

        $this->stats['b2b_urls'] = count($sitemap);
        $this->info('B2B product URLs: ' . count($sitemap));

        $products = Product::query()
            ->orderable()
            ->whereIn('products.id', function ($query) use ($supplierId): void {
                $query->from('supplier_products')
                    ->select('product_id')
                    ->where('supplier_id', $supplierId)
                    ->whereNotNull('product_id');
            })
            ->when($brandFilter !== '', fn ($query) => $query->whereHas('brand', fn ($brand) => $brand->where('name', 'like', '%' . $brandFilter . '%')))
            ->with(['brand:id,name'])
            ->orderBy('products.id')
            ->get(['id', 'brand_id', 'name', 'slug', 'sku', 'images'])
            ->filter(fn (Product $product) => $this->imageStatus($product)[0] !== 'ok')
            ->values();

        $this->stats['broken_products'] = $products->count();

        $matches = [];
        foreach ($products as $product) {
            $match = $this->matchProduct($product, $sitemap);
            if ($match === null) {
                $this->stats['no_match']++;
                continue;
            }

            $matches[] = [$product, $match];
            $this->stats['matched']++;
        }

        $totalMatches = count($matches);
        $matches = array_slice($matches, $offset, $limit > 0 ? $limit : null);

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: matched B2B images will be written.</>'
            : '<fg=yellow;options=bold>DRY RUN: no database/filesystem changes.</>');
        $this->info(sprintf(
            'Broken products: %d; matched: %d; no match: %d; processing %d, offset %d',
            $this->stats['broken_products'],
            $totalMatches,
            $this->stats['no_match'],
            count($matches),
            $offset
        ));

        foreach ($matches as [$product, $sourceUrl]) {
            $this->stats['processed']++;
            [$status, $detail] = $this->imageStatus($product);

            $this->line(sprintf(
                '[%d/%d] #%d %s %s (%s)',
                $this->stats['processed'],
                count($matches),
                $product->id,
                $product->slug,
                mb_strimwidth($product->name, 0, 62, '...'),
                $status
            ));
            $this->line('  current: ' . $detail);
            $this->line('  source: ' . $sourceUrl);

            try {
                $result = $enricher->enrich($product, $sourceUrl, [
                    'preview_only' => ! $apply,
                    'replace_images' => true,
                    'update_images' => true,
                    'update_specs' => false,
                    'update_service' => false,
                    'update_documents' => false,
                    'update_video' => false,
                    'update_content' => false,
                ]);

                $imagesFound = (int) ($result['images_found'] ?? 0);
                $imagesSaved = (int) ($result['images_saved'] ?? 0);
                $this->stats['images_found'] += $imagesFound;
                $this->stats['images_saved'] += $imagesSaved;

                if ($imagesFound === 0) {
                    $this->stats['no_source_images']++;
                } elseif (! $apply) {
                    $this->stats['would_repair']++;
                } elseif (in_array('images', $result['updated_fields'] ?? [], true)) {
                    $this->stats['repaired']++;
                    DB::table('supplier_products')
                        ->where('supplier_id', $supplierId)
                        ->where('product_id', $product->id)
                        ->update(['source_url' => $sourceUrl, 'updated_at' => now()]);
                }

                $this->line(sprintf('  found=%d saved=%d', $imagesFound, $imagesSaved));
            } catch (\Throwable $e) {
                $this->stats['errors']++;
                $this->warn('  ERROR: ' . $e->getMessage());
            }

            usleep($sleep * 1000);
        }

        $this->newLine();
        $this->table(['metric', 'count'], collect($this->stats)->map(fn ($count, $metric) => [$metric, $count])->values()->all());

        if ($totalMatches > $offset + count($matches)) {
            $this->line(sprintf(
                '<fg=yellow>%d matched products remain. Run with --offset=%d to continue.</>',
                $totalMatches - ($offset + count($matches)),
                $offset + count($matches)
            ));
        }

        return $this->stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function loadB2bSitemap(): array
    {
        $root = $this->fetch(self::SITEMAP_URL);
        if ($root === null) {
            return [];
        }

        preg_match_all('#<loc>(.*?)</loc>#', $root, $mapMatches);

        $urls = [];
        foreach ($mapMatches[1] as $mapUrl) {
            $xml = $this->fetch($mapUrl);
            if ($xml === null) {
                continue;
            }

            preg_match_all('#<loc>(https://b2b\.rusklimat\.com/catalog/product/[^<]+)</loc>#', $xml, $urlMatches);
            foreach ($urlMatches[1] as $url) {
                $slug = basename(rtrim($url, '/'));
                $urls[$slug] = $url;
            }
        }

        return $urls;
    }

    private function fetch(string $url): ?string
    {
        try {
            $response = Http::timeout(45)
                ->retry(2, 500)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'application/xml,text/xml,text/html',
                ])
                ->get($url);

            return $response->ok() ? $response->body() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, string> $sitemap
     */
    private function matchProduct(Product $product, array $sitemap): ?string
    {
        $productSlug = Str::slug($product->slug ?: $product->name);
        if ($productSlug !== '' && isset($sitemap[$productSlug])) {
            return $sitemap[$productSlug];
        }

        $brandSlug = Str::slug((string) ($product->brand?->name ?? ''));
        $modelTokens = $this->modelTokens($product, $brandSlug);
        if ($brandSlug === '' || count($modelTokens) < 2) {
            return null;
        }

        $best = null;
        $bestLength = PHP_INT_MAX;

        foreach ($sitemap as $slug => $url) {
            if (! str_contains($slug, $brandSlug)) {
                continue;
            }

            $matched = 0;
            foreach ($modelTokens as $token) {
                if (str_contains($slug, $token)) {
                    $matched++;
                }
            }

            if ($matched !== count($modelTokens)) {
                continue;
            }

            $length = strlen($slug);
            if ($length < $bestLength) {
                $best = $url;
                $bestLength = $length;
            }
        }

        if ($best !== null) {
            return $best;
        }

        $compactNeedles = $this->compactModelNeedles($product, $brandSlug);
        if ($compactNeedles === []) {
            return null;
        }

        foreach ($sitemap as $slug => $url) {
            if (! str_contains($slug, $brandSlug)) {
                continue;
            }

            $compactSlug = str_replace('-', '', $slug);
            foreach ($compactNeedles as $needle) {
                if (! str_contains($compactSlug, $needle)) {
                    continue;
                }

                $length = strlen($slug);
                if ($length < $bestLength) {
                    $best = $url;
                    $bestLength = $length;
                }
            }
        }

        return $best;
    }

    /**
     * @return array<int, string>
     */
    private function modelTokens(Product $product, string $brandSlug): array
    {
        $slug = Str::slug($product->slug ?: $product->name);
        if ($brandSlug !== '') {
            foreach (explode('-', $brandSlug) as $token) {
                $slug = preg_replace('/(^|-)' . preg_quote($token, '/') . '(-|$)/', '-', $slug) ?? $slug;
            }
        }

        $generic = [
            'komplekt', 'dlia', 'bez', 'belyi', 'belyy', 'chernyi', 'chernyy',
            'nastennyi', 'napolnyi', 'nagrevatelnyi', 'elektricheskii',
            'radiator', 'kotel', 'konvektor', 'vodonagrevatel', 'nasos',
            'filtr', 'truba', 'klapan', 'dizain', 'sekc',
        ];

        return collect(explode('-', $slug))
            ->map(fn (string $token): string => trim($token))
            ->filter(fn (string $token): bool => strlen($token) >= 2)
            ->reject(fn (string $token): bool => in_array($token, $generic, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function compactModelNeedles(Product $product, string $brandSlug): array
    {
        $sources = array_filter([
            (string) $product->name,
            (string) $product->slug,
            (string) $product->sku,
        ]);

        $brandTokens = $brandSlug !== '' ? explode('-', $brandSlug) : [];
        $generic = [
            'komplekt', 'dlia', 'bez', 'belyi', 'belyy', 'chernyi', 'chernyy',
            'nastennyi', 'napolnyi', 'nagrevatelnyi', 'elektricheskii',
            'radiator', 'kotel', 'konvektor', 'vodonagrevatel', 'nasos',
            'filtr', 'truba', 'klapan', 'dizain', 'sekc', 'sekciia', 'sekts',
            'split', 'sistema', 'tipa', 'blok', 'vnutrennii', 'universalnyi',
        ];

        $needles = [];
        foreach ($sources as $source) {
            $parts = collect(explode('-', Str::slug($source)))
                ->map(fn (string $token): string => trim($token))
                ->filter(fn (string $token): bool => $token !== '')
                ->reject(fn (string $token): bool => in_array($token, $brandTokens, true))
                ->reject(fn (string $token): bool => in_array($token, $generic, true))
                ->values()
                ->all();

            $compact = implode('', $parts);
            if (strlen($compact) >= 6 && preg_match('/\d/', $compact)) {
                $needles[] = $compact;
            }

            $count = count($parts);
            for ($size = min(5, $count); $size >= 2; $size--) {
                for ($i = 0; $i <= $count - $size; $i++) {
                    $candidate = implode('', array_slice($parts, $i, $size));
                    if (strlen($candidate) >= 6 && preg_match('/\d/', $candidate)) {
                        $needles[] = $candidate;
                    }
                }
            }
        }

        return collect($needles)
            ->unique()
            ->sortByDesc(fn (string $needle): int => strlen($needle))
            ->values()
            ->all();
    }

    private function imageStatus(Product $product): array
    {
        $images = $product->images;
        if (is_string($images)) {
            $images = json_decode($images, true);
        }

        if (! is_array($images) || $images === [] || trim((string) ($images[0] ?? '')) === '') {
            return ['empty', 'images is empty'];
        }

        $raw = ltrim((string) $images[0], '/');
        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
            return ['ok', $raw];
        }

        if (str_starts_with($raw, 'img/')) {
            return file_exists(public_path($raw)) ? ['ok', $raw] : ['broken', $raw];
        }

        if (str_starts_with($raw, 'products/')) {
            return Storage::disk('public')->exists($raw) ? ['ok', $raw] : ['broken', 'storage:' . $raw];
        }

        if (str_starts_with($raw, 'product/')) {
            return file_exists(public_path('images/' . $raw)) ? ['ok', 'legacy:' . $raw] : ['broken', 'legacy:' . $raw];
        }

        if (substr_count($raw, '/') >= 2) {
            return file_exists(public_path('images/product/' . $raw)) ? ['ok', 'legacy:' . $raw] : ['broken', 'legacy:' . $raw];
        }

        $idPath = $this->legacyIdPath($product, $raw);

        return file_exists(public_path('images/' . $idPath)) ? ['ok', 'legacy:' . $idPath] : ['broken', 'legacy:' . $idPath];
    }

    private function legacyIdPath(Product $product, string $file): string
    {
        $n1 = (int) floor(((int) $product->id) / 1000);
        $dir1 = sprintf('00%d', $n1);
        $dir2 = str_pad((string) $product->id, 6, '0', STR_PAD_LEFT);

        return 'product/' . $dir1 . '/' . $dir2 . '/' . $file;
    }
}
