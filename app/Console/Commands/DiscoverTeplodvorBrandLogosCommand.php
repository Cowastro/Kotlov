<?php

namespace App\Console\Commands;

use App\Models\Brand;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DiscoverTeplodvorBrandLogosCommand extends Command
{
    protected $signature = 'brands:discover-teplodvor-logos
        {--apply : Download logos and update empty/broken brand logo fields}
        {--brand= : One brand name or slug}
        {--limit=100 : Maximum brands, 0 means no limit}
        {--force : Replace existing working logos too}';

    protected $description = 'Fill missing or broken brand logos from Teplodvor brand index without overwriting good local logos.';

    private const SOURCE_URL = 'https://www.teplodvor.by/brands/';
    private const TARGET_DIR = 'img/brands/teplodvor';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $force = (bool) $this->option('force');
        $limit = max(0, (int) $this->option('limit'));

        $this->line($apply ? 'APPLY: missing/broken brand logos will be downloaded.' : 'DRY RUN: no brand logos will be changed.');
        $this->line('Source: ' . self::SOURCE_URL);

        $index = $this->loadTeplodvorIndex();
        if ($index === []) {
            $this->error('No Teplodvor brand logo index could be built.');

            return self::FAILURE;
        }

        $query = Brand::query()
            ->where('is_active', true)
            ->withCount(['products' => fn ($q) => $q->orderable()])
            ->orderBy('name');

        if ($brand = trim((string) $this->option('brand'))) {
            $query->where(function ($q) use ($brand): void {
                $q->where('name', 'like', '%' . $brand . '%')
                    ->orWhere('slug', 'like', '%' . Str::slug($brand) . '%');
            });
        }

        $brands = $query->get(['id', 'name', 'slug', 'logo']);
        if ($limit > 0) {
            $brands = $brands->take($limit);
        }

        $summary = ['checked' => 0, 'matched' => 0, 'downloaded' => 0, 'updated' => 0, 'skipped_existing' => 0, 'skipped_missing_source' => 0, 'errors' => 0];
        $rows = [];

        foreach ($brands as $brand) {
            if ((int) $brand->products_count === 0) {
                continue;
            }

            $summary['checked']++;
            $logoStatus = $this->logoStatus($brand->logo);
            if (! $force && $logoStatus === 'ok') {
                $summary['skipped_existing']++;
                continue;
            }

            $source = $index[$this->brandKey($brand->name)] ?? null;
            if ($source === null) {
                $summary['skipped_missing_source']++;
                continue;
            }

            $summary['matched']++;
            $rows[] = [$brand->id, $brand->name, $logoStatus, $source];

            if (! $apply) {
                continue;
            }

            $saved = $this->downloadLogo($source, $brand->slug ?: Str::slug($brand->name));
            if ($saved === null) {
                $summary['errors']++;
                $this->warn('Download failed: ' . $brand->name . ' -> ' . $source);
                continue;
            }

            $summary['downloaded']++;
            $brand->forceFill([
                'logo' => $saved,
                'updated_at' => now(),
            ])->save();
            $summary['updated']++;
        }

        $this->table(['metric', 'count'], collect($summary)->map(fn ($count, $metric) => [$metric, $count])->values()->all());
        if ($rows !== []) {
            $this->table(['brand_id', 'brand', 'old_logo', 'source'], array_slice($rows, 0, 40));
        }

        return self::SUCCESS;
    }

    private function loadTeplodvorIndex(): array
    {
        $response = Http::timeout(45)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 KOTLOV brand logo audit'])
            ->get(self::SOURCE_URL);

        if (! $response->successful()) {
            $this->warn('Teplodvor returned HTTP ' . $response->status());

            return [];
        }

        $html = (string) $response->body();
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($dom);

        $index = [];
        foreach ($xpath->query('//img[@alt and (@src or @data-src or @data-original)]') as $img) {
            if (! $img instanceof \DOMElement) {
                continue;
            }

            $name = trim(html_entity_decode((string) $img->getAttribute('alt'), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $name = preg_replace('/^(Image:\s*|Производитель\s+)/iu', '', $name) ?? $name;
            $src = trim((string) ($img->getAttribute('data-src') ?: $img->getAttribute('data-original') ?: $img->getAttribute('src')));

            if ($name === '' || $src === '' || str_contains($src, 'placeholder')) {
                continue;
            }

            $url = $this->absoluteUrl($src);
            if (! $this->looksLikeImageUrl($url)) {
                continue;
            }

            $index[$this->brandKey($name)] = $url;
        }

        return $index;
    }

    private function downloadLogo(string $url, string $slug): ?string
    {
        $response = Http::timeout(45)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 KOTLOV brand logo downloader'])
            ->get($url);

        if (! $response->successful()) {
            return null;
        }

        $body = (string) $response->body();
        if (strlen($body) < 256 || @getimagesizefromstring($body) === false) {
            return null;
        }

        $dir = public_path(self::TARGET_DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $ext = $this->extensionFromResponse($url, (string) $response->header('Content-Type'));
        $relative = self::TARGET_DIR . '/' . Str::slug($slug) . '.' . $ext;
        file_put_contents(public_path($relative), $body);

        return $relative;
    }

    private function logoStatus(?string $path): string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return 'missing';
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return 'ok';
        }

        if (Storage::disk('public')->exists($path)) {
            return 'ok';
        }

        if (file_exists(public_path(ltrim($path, '/')))) {
            return 'ok';
        }

        if (file_exists(public_path('images/' . ltrim($path, '/')))) {
            return 'ok';
        }

        return 'broken';
    }

    private function absoluteUrl(string $url): string
    {
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return 'https://www.teplodvor.by/' . ltrim($url, '/');
    }

    private function looksLikeImageUrl(string $url): bool
    {
        return (bool) preg_match('/\.(?:jpe?g|png|webp|gif|svg)(?:\?|$)/iu', $url);
    }

    private function extensionFromResponse(string $url, string $contentType): string
    {
        $contentType = strtolower($contentType);
        if (str_contains($contentType, 'png')) {
            return 'png';
        }
        if (str_contains($contentType, 'webp')) {
            return 'webp';
        }
        if (str_contains($contentType, 'gif')) {
            return 'gif';
        }
        if (str_contains($contentType, 'svg')) {
            return 'svg';
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], true) ? ($ext === 'jpeg' ? 'jpg' : $ext) : 'jpg';
    }

    private function brandKey(string $value): string
    {
        $value = Str::lower($value);
        $value = str_replace(['ё', 'Ё'], ['е', 'е'], $value);
        $value = preg_replace('/[^a-zа-я0-9]+/u', '', $value) ?? $value;

        return trim($value);
    }
}
