<?php

namespace App\Console\Commands;

use App\Services\AiContentEnricher;
use App\Services\ProductSourceEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SanitizeProductContentHtmlCommand extends Command
{
    protected $signature = 'products:sanitize-content-html
        {--apply : Write sanitized content}
        {--brand= : Brand name or slug}
        {--supplier= : Supplier code filter}
        {--sku= : Single product SKU}
        {--slug-like= : Product slug substring filter}
        {--id=* : Product ID filter, can be repeated}
        {--active-only : Only active products}
        {--not-archived : Only not archived products}
        {--with-source-only : Only products linked to supplier source URLs}
        {--created-from= : Only products created at or after this date}
        {--created-to= : Only products created at or before this date}
        {--extract-media : Move video and document links from content to product fields}
        {--overwrite-media : Replace existing video/documents while extracting media}
        {--rewrite-seo : Regenerate short_description, content and meta_description with AI}
        {--show-samples=0 : Show first N rows with detected media links}
        {--sleep=300 : Delay between AI requests, ms}
        {--limit=100 : Rows to process, 0 means all}';

    protected $description = 'Sanitize stored product HTML descriptions and remove foreign markup, images and inline styles.';

    public function handle(ProductSourceEnricher $enricher, AiContentEnricher $ai): int
    {
        $apply = (bool) $this->option('apply');
        $limit = max(0, (int) $this->option('limit'));
        $extractMedia = (bool) $this->option('extract-media');
        $overwriteMedia = (bool) $this->option('overwrite-media');
        $rewriteSeo = (bool) $this->option('rewrite-seo');
        $showSamples = max(0, (int) $this->option('show-samples'));
        $sleep = max(0, (int) $this->option('sleep'));

        if ($apply && ! $this->hasScope()) {
            $this->error('Refusing broad apply. Add --brand, --supplier, --sku, --slug-like, --id, --with-source-only, --created-from or --created-to.');

            return self::FAILURE;
        }

        if ($rewriteSeo && ! $ai->isAvailable()) {
            $this->error('No AI provider configured for --rewrite-seo.');

            return self::FAILURE;
        }

        $query = DB::table('products as p')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->select(
                'p.id',
                'p.sku',
                'p.slug',
                'p.name',
                'p.content',
                'p.short_description',
                'p.meta_description',
                'p.specs',
                'p.video_url',
                'p.documents',
                'b.name as brand',
                'c.name as category'
            )
            ->whereNotNull('p.content')
            ->where('p.content', '<>', '')
            ->orderBy('p.id');

        if ($supplier = trim((string) $this->option('supplier'))) {
            $query
                ->join('supplier_products as sp', 'sp.product_id', '=', 'p.id')
                ->join('suppliers as s', 's.id', '=', 'sp.supplier_id')
                ->where('s.code', $supplier);
        }

        if ((bool) $this->option('with-source-only')) {
            $query
                ->join('supplier_products as sp_source_filter', 'sp_source_filter.product_id', '=', 'p.id')
                ->whereNotNull('sp_source_filter.source_url')
                ->where('sp_source_filter.source_url', '<>', '');
        }

        if ($brand = trim((string) $this->option('brand'))) {
            $query->where(function ($q) use ($brand) {
                $q->where('b.name', $brand)->orWhere('b.slug', $brand);
            });
        }

        if ($sku = trim((string) $this->option('sku'))) {
            $query->where('p.sku', $sku);
        }

        if ($slugLike = trim((string) $this->option('slug-like'))) {
            $query->where('p.slug', 'like', '%' . $slugLike . '%');
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

        if ($createdFrom = trim((string) $this->option('created-from'))) {
            $query->where('p.created_at', '>=', $createdFrom);
        }

        if ($createdTo = trim((string) $this->option('created-to'))) {
            $query->where('p.created_at', '<=', $createdTo);
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
            'videos_extracted' => 0,
            'documents_extracted' => 0,
            'seo_rewritten' => 0,
        ];
        $changedRows = [];
        $sampleRows = [];

        foreach ($rows as $row) {
            $stats['checked']++;

            $original = (string) $row->content;
            $sanitized = $enricher->sanitizeDescriptionHtml($original);
            $media = $extractMedia ? $this->extractMediaLinks($original) : ['video_url' => '', 'documents' => []];
            if ($showSamples > 0 && count($sampleRows) < $showSamples && ($media['video_url'] !== '' || $media['documents'] !== [] || $this->contentLinks($original) !== [])) {
                $sampleRows[] = [
                    $row->id,
                    $row->sku,
                    $row->slug,
                    $media['video_url'] ?: '-',
                    implode(', ', array_slice(array_map(fn (array $document): string => $document['url'], $media['documents']), 0, 3)) ?: '-',
                    implode(', ', array_slice($this->contentLinks($original), 0, 3)) ?: '-',
                ];
            }
            $updates = [];
            $rewroteSeo = false;

            if (trim($original) !== trim($sanitized)) {
                $updates['content'] = $sanitized;
            }

            if ($extractMedia && $media['video_url'] !== '' && ($overwriteMedia || trim((string) $row->video_url) === '')) {
                $updates['video_url'] = $media['video_url'];
                $stats['videos_extracted']++;
            }

            if ($extractMedia && $media['documents'] !== []) {
                $documents = $overwriteMedia ? [] : $this->decodeDocuments($row->documents);
                $before = count($documents);
                $documents = $this->mergeDocuments($documents, $media['documents']);
                if (count($documents) > $before || $overwriteMedia) {
                    $updates['documents'] = json_encode($documents, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $stats['documents_extracted'] += max(0, count($documents) - $before);
                }
            }

            if ($updates === []) {
                if ($rewriteSeo) {
                    $updates = $this->seoUpdates($ai, $row);
                    $rewroteSeo = $updates !== [];
                    if ($updates !== [] && $sleep > 0) {
                        usleep($sleep * 1000);
                    }
                }

                if ($updates === []) {
                    continue;
                }
            } elseif ($rewriteSeo) {
                $seoUpdates = $this->seoUpdates($ai, $row);
                $updates = array_merge($updates, $seoUpdates);
                $rewroteSeo = $seoUpdates !== [];
                if ($sleep > 0) {
                    usleep($sleep * 1000);
                }
            }

            if ($updates === []) {
                continue;
            }

            if ($rewroteSeo) {
                $stats['seo_rewritten']++;
            }

            $stats['changed']++;
            $stats['images_removed'] += max(0, $this->countMatches('/<img\b/iu', $original) - $this->countMatches('/<img\b/iu', $sanitized));
            $stats['styles_removed'] += max(0, $this->countMatches('/\sstyle\s*=/iu', $original) - $this->countMatches('/\sstyle\s*=/iu', $sanitized));
            $stats['bad_blocks_removed'] += max(0, $this->countMatches('/<(script|style|iframe|object|embed|svg|canvas|picture|video|audio|form|button|input|select|textarea|table)\b/iu', $original));

            if ($apply) {
                $updates['updated_at'] = now();
                DB::table('products')->where('id', $row->id)->update($updates);
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

        if ($sampleRows !== []) {
            $this->table(['ID', 'SKU', 'Slug', 'Video', 'Documents', 'Raw links'], $sampleRows);
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string,mixed>
     */
    private function seoUpdates(AiContentEnricher $ai, object $row): array
    {
        $seo = $ai->generateSeo(
            (string) $row->name,
            (string) ($row->brand ?? ''),
            (string) ($row->category ?? ''),
            $this->specsForProduct((int) $row->id, $row->specs)
        );

        if (! $seo) {
            return [];
        }

        $updates = [];
        if (trim((string) ($seo['content'] ?? '')) !== '') {
            $updates['content'] = (string) $seo['content'];
        }
        if (trim((string) ($seo['short'] ?? '')) !== '') {
            $updates['short_description'] = (string) $seo['short'];
            $updates['meta_description'] = Str::limit(strip_tags((string) $seo['short']), 250, '');
        }

        return $updates;
    }

    /**
     * @return array<string,string>
     */
    private function specsForProduct(int $productId, mixed $productSpecs): array
    {
        $decoded = is_string($productSpecs) ? json_decode($productSpecs, true) : $productSpecs;
        if (is_array($decoded) && $decoded !== []) {
            $flat = [];
            foreach ($decoded as $key => $value) {
                if (is_array($value)) {
                    $name = trim((string) ($value['name'] ?? $value['key'] ?? $key));
                    $val = trim((string) ($value['value'] ?? ''));
                    $unit = trim((string) ($value['unit'] ?? ''));
                    if ($name !== '' && $val !== '') {
                        $flat[$name] = trim($val . ' ' . $unit);
                    }
                } elseif (is_scalar($value) && trim((string) $value) !== '') {
                    $flat[(string) $key] = (string) $value;
                }
            }

            if ($flat !== []) {
                return $flat;
            }
        }

        $query = DB::table('product_attribute_values as pav')
            ->join('attributes as a', 'a.id', '=', 'pav.attribute_id')
            ->where('pav.product_id', $productId)
            ->whereNotNull('pav.value')
            ->where('pav.value', '<>', '')
            ->limit(40);

        if (Schema::hasColumn('product_attribute_values', 'sort_order')) {
            $query->orderBy('pav.sort_order');
        } else {
            $query->orderBy('pav.id');
        }

        return $query->pluck('pav.value', 'a.name')
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->all();
    }

    private function countMatches(string $pattern, string $value): int
    {
        return preg_match_all($pattern, $value) ?: 0;
    }

    private function hasScope(): bool
    {
        return trim((string) $this->option('brand')) !== ''
            || trim((string) $this->option('supplier')) !== ''
            || trim((string) $this->option('sku')) !== ''
            || trim((string) $this->option('slug-like')) !== ''
            || trim((string) $this->option('created-from')) !== ''
            || trim((string) $this->option('created-to')) !== ''
            || (bool) $this->option('with-source-only')
            || array_values(array_filter((array) $this->option('id'))) !== [];
    }

    /**
     * @return array{video_url: string, documents: array<int, array{label: string, url: string}>}
     */
    private function extractMediaLinks(string $html): array
    {
        $links = [];
        if (preg_match_all('~<(?:a|iframe|embed|source|video)\b[^>]*(?:href|src|data-src)=["\']([^"\']+)["\'][^>]*>(.*?)</(?:a|iframe|embed|source|video)>~isu', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $url = $this->normalizeLink(html_entity_decode(trim($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if (! filter_var($url, FILTER_VALIDATE_URL)) {
                    continue;
                }

                $label = trim(strip_tags(html_entity_decode($match[2] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                $links[] = ['url' => $url, 'label' => $label];
            }
        }

        if (preg_match_all('~<(?:a|iframe|embed|source|video)\b[^>]*(?:href|src|data-src)=["\']([^"\']+)["\'][^>]*\/?>~isu', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $url = $this->normalizeLink(html_entity_decode(trim($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if (! filter_var($url, FILTER_VALIDATE_URL)) {
                    continue;
                }

                $label = trim(strip_tags(html_entity_decode($match[0] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                $links[] = ['url' => $url, 'label' => $label];
            }
        }

        if (preg_match_all('~\bhttps?://[^\s"\'<>]+~iu', $html, $matches)) {
            foreach ($matches[0] as $url) {
                $links[] = ['url' => $this->normalizeLink(rtrim($url, '.,);]')), 'label' => ''];
            }
        }

        $videoUrl = '';
        $documents = [];
        foreach ($links as $link) {
            $url = $link['url'];
            $label = $link['label'] !== '' ? $link['label'] : $this->labelFromUrl($url);

            if ($videoUrl === '' && $this->isVideoUrl($url)) {
                $videoUrl = $url;
                continue;
            }

            if ($this->isDocumentUrl($url, $label)) {
                $documents[] = [
                    'label' => Str::limit($label, 120, ''),
                    'url' => $url,
                ];
            }
        }

        return [
            'video_url' => $videoUrl,
            'documents' => $this->mergeDocuments([], $documents),
        ];
    }

    /**
     * @return string[]
     */
    private function contentLinks(string $html): array
    {
        $links = [];
        if (preg_match_all('~(?:href|src|data-src)=["\']([^"\']+)["\']~iu', $html, $matches)) {
            foreach ($matches[1] as $url) {
                $url = $this->normalizeLink(html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if (filter_var($url, FILTER_VALIDATE_URL)) {
                    $links[] = $url;
                }
            }
        }
        if (preg_match_all('~\bhttps?://[^\s"\'<>]+~iu', $html, $matches)) {
            foreach ($matches[0] as $url) {
                $url = $this->normalizeLink(rtrim($url, '.,);]'));
                if (filter_var($url, FILTER_VALIDATE_URL)) {
                    $links[] = $url;
                }
            }
        }

        return array_values(array_unique($links));
    }

    private function normalizeLink(string $url): string
    {
        $url = trim($url);
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        if (str_starts_with($url, '/')) {
            return 'https://kotlov.by' . $url;
        }

        return $url;
    }

    private function isVideoUrl(string $url): bool
    {
        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));

        return str_contains($host, 'youtube.com')
            || str_contains($host, 'youtu.be')
            || str_contains($host, 'vimeo.com')
            || str_contains($host, 'rutube.ru')
            || str_contains($host, 'vk.com')
            || preg_match('/\.(mp4|webm|mov)(?:\?|$)/iu', $url);
    }

    private function isDocumentUrl(string $url, string $label): bool
    {
        if (preg_match('/\.(pdf|doc|docx|xls|xlsx|ppt|pptx|zip|rar)(?:\?|$)/iu', $url)) {
            return true;
        }

        if (preg_match('/\b(скачать|паспорт|инструкц|сертификат|документ|монтаж|руководство|manual|catalog|catalogue|pdf)\b/iu', $label) === 1) {
            return true;
        }

        return preg_match('/\b(скачать|паспорт|инструкц|сертификат|документ|монтаж|руководство|manual|catalog|catalogue|pdf)\b/iu', $label) === 1;
    }

    private function labelFromUrl(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $basename = $path !== '' ? basename($path) : 'Документ';
        $label = preg_replace('/[-_]+/u', ' ', pathinfo($basename, PATHINFO_FILENAME)) ?: $basename;

        return trim($label) !== '' ? trim($label) : 'Документ';
    }

    /**
     * @return array<int, array{label: string, url: string}>
     */
    private function decodeDocuments(mixed $documents): array
    {
        $decoded = is_string($documents) ? json_decode($documents, true) : $documents;
        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($item): ?array {
            if (is_string($item) && filter_var($item, FILTER_VALIDATE_URL)) {
                return ['label' => $this->labelFromUrl($item), 'url' => $item];
            }

            if (! is_array($item)) {
                return null;
            }

            $url = trim((string) ($item['url'] ?? ''));
            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                return null;
            }

            return [
                'label' => trim((string) ($item['label'] ?? '')) ?: $this->labelFromUrl($url),
                'url' => $url,
            ];
        }, $decoded)));
    }

    /**
     * @param array<int, array{label: string, url: string}> $existing
     * @param array<int, array{label: string, url: string}> $new
     * @return array<int, array{label: string, url: string}>
     */
    private function mergeDocuments(array $existing, array $new): array
    {
        $merged = [];
        foreach ([...$existing, ...$new] as $document) {
            $url = trim((string) ($document['url'] ?? ''));
            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }

            $merged[$url] = [
                'label' => trim((string) ($document['label'] ?? '')) ?: $this->labelFromUrl($url),
                'url' => $url,
            ];
        }

        return array_values(array_slice($merged, 0, 20));
    }
}
