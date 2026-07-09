<?php

namespace App\Console\Commands;

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
        {--id=* : Product ID filter, can be repeated}
        {--active-only : Only active products}
        {--not-archived : Only not archived products}
        {--with-source-only : Only products linked to supplier source URLs}
        {--created-from= : Only products created at or after this date}
        {--created-to= : Only products created at or before this date}
        {--extract-media : Move video and document links from content to product fields}
        {--overwrite-media : Replace existing video/documents while extracting media}
        {--limit=100 : Rows to process, 0 means all}';

    protected $description = 'Sanitize stored product HTML descriptions and remove foreign markup, images and inline styles.';

    public function handle(ProductSourceEnricher $enricher): int
    {
        $apply = (bool) $this->option('apply');
        $limit = max(0, (int) $this->option('limit'));
        $extractMedia = (bool) $this->option('extract-media');
        $overwriteMedia = (bool) $this->option('overwrite-media');

        if ($apply && ! $this->hasScope()) {
            $this->error('Refusing broad apply. Add --brand, --supplier, --sku, --id, --with-source-only, --created-from or --created-to.');

            return self::FAILURE;
        }

        $query = DB::table('products as p')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->select('p.id', 'p.sku', 'p.name', 'p.content', 'p.video_url', 'p.documents', 'b.name as brand')
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
        ];
        $changedRows = [];

        foreach ($rows as $row) {
            $stats['checked']++;

            $original = (string) $row->content;
            $sanitized = $enricher->sanitizeDescriptionHtml($original);
            $media = $extractMedia ? $this->extractMediaLinks($original) : ['video_url' => '', 'documents' => []];
            $updates = [];

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
                continue;
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

        return self::SUCCESS;
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
        if (preg_match_all('~<(?:a|iframe|embed|source|video)\b[^>]*(?:href|src)=["\']([^"\']+)["\'][^>]*>(.*?)</(?:a|iframe|embed|source|video)>~isu', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $url = html_entity_decode(trim($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (! filter_var($url, FILTER_VALIDATE_URL)) {
                    continue;
                }

                $label = trim(strip_tags(html_entity_decode($match[2] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                $links[] = ['url' => $url, 'label' => $label];
            }
        }

        if (preg_match_all('~\bhttps?://[^\s"\'<>]+~iu', $html, $matches)) {
            foreach ($matches[0] as $url) {
                $links[] = ['url' => rtrim($url, '.,);]'), 'label' => ''];
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
