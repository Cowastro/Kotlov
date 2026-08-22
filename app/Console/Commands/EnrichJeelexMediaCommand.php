<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\ProductSourceEnricher;
use Illuminate\Console\Command;

/**
 * Enrich "Джилекс" products with photos and full technical specs, sourced
 * from the matching product pages on jeelex.ru (official manufacturer site).
 *
 * Unlike the Теплов и Сухов join, matching here needs no fuzzy name logic:
 * every jeelex.ru product page shows its own "Артикул: NNNN", which is
 * exactly the supplier article already stored via the tm-management price
 * sync (SyncTmManagementCommand) in supplier_products. So this command only
 * has to look up SupplierProduct by that numeric article.
 *
 * The mapping file (storage/app/imports/jeelex-media.json) was built once by
 * crawling every URL in jeelex.ru's sitemap-iblock-32.xml (the catalog
 * iblock) and keeping only pages that show an "Артикул" (i.e. actual product
 * pages, not category/listing pages).
 *
 * Usage:
 *   php artisan supplier:enrich-jeelex-media                (dry-run)
 *   php artisan supplier:enrich-jeelex-media --apply
 *   php artisan supplier:enrich-jeelex-media --apply --sku=9449
 */
class EnrichJeelexMediaCommand extends Command
{
    protected $signature = 'supplier:enrich-jeelex-media
                            {--apply : Write photos and specs to the DB (default: dry-run)}
                            {--overwrite : Replace existing photos/specs even if already set}
                            {--limit= : Max products to process}
                            {--sku= : Process a single supplier article}
                            {--link= : Create a missing supplier_products link before processing, as "productId:supplierArticle"}';

    protected $description = 'Fill photos and specs for Джилекс products from the jeelex.ru media map';

    private const SUPPLIER_CODE = 'tm-management';
    private const MAP_FILE = 'imports/jeelex-media.json';
    private const IMAGE_DIR = 'img/products/jeelex';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $overwrite = (bool) $this->option('overwrite');
        $onlySku = trim((string) $this->option('sku'));
        $limit = $this->option('limit') ? (int) $this->option('limit') : PHP_INT_MAX;

        $path = storage_path('app/' . self::MAP_FILE);
        $map = json_decode((string) file_get_contents($path), true);
        if (! is_array($map) || $map === []) {
            $this->error('Media map not found or empty: ' . $path);
            return self::FAILURE;
        }

        $supplier = Supplier::query()->where('code', self::SUPPLIER_CODE)->first();
        if (! $supplier) {
            $this->error('Поставщик ' . self::SUPPLIER_CODE . ' не найден.');
            return self::FAILURE;
        }

        $linkSpec = trim((string) $this->option('link'));
        if ($linkSpec !== '') {
            if (! preg_match('/^(\d+):(.+)$/', $linkSpec, $m)) {
                $this->error('Неверный формат --link, ожидается "productId:supplierArticle".');
                return self::FAILURE;
            }
            $linkProductId = (int) $m[1];
            $linkArticle = trim($m[2]);
            $linkProduct = Product::query()->find($linkProductId);
            if (! $linkProduct) {
                $this->error("Товар #{$linkProductId} не найден.");
                return self::FAILURE;
            }
            if ($apply) {
                SupplierProduct::query()->updateOrCreate(
                    ['supplier_id' => $supplier->id, 'supplier_article' => $linkArticle],
                    ['product_id' => $linkProductId, 'product_sku' => $linkProduct->sku, 'match_status' => 'matched', 'match_confidence' => 'manual_media_link']
                );
                $this->info("Связь создана: товар #{$linkProductId} ({$linkProduct->sku}) ↔ {$linkArticle}");
            } else {
                $this->info("[DRY RUN] Будет создана связь: товар #{$linkProductId} ({$linkProduct->sku}) ↔ {$linkArticle}");
            }
        }

        $this->line($apply
            ? '<fg=red;options=bold>APPLY — база будет изменена.</>'
            : '<fg=yellow;options=bold>DRY RUN — изменений не будет.</>');

        $stats = ['total' => 0, 'no_link' => 0, 'no_product' => 0, 'archived' => 0, 'image_done' => 0, 'image_skipped' => 0, 'no_image_source' => 0, 'specs_done' => 0, 'specs_skipped' => 0, 'errors' => 0];
        $rows = [];

        foreach ($map as $sku => $data) {
            if ($onlySku !== '' && (string) $sku !== $onlySku) {
                continue;
            }
            if ($stats['total'] >= $limit) {
                break;
            }
            $stats['total']++;

            $link = SupplierProduct::query()
                ->where('supplier_id', $supplier->id)
                ->where('supplier_article', (string) $sku)
                ->first();

            if (! $link || ! $link->product_id) {
                // A raw article collision during the TM Management price sync gets
                // disambiguated with a "-<hash>" suffix (see SyncTmManagementCommand::
                // uniqueSupplierArticle) — the plain numeric jeelex.ru article then no
                // longer matches exactly. Fall back to a prefix match, but only act on
                // it when it is unambiguous: a shared collision prefix can point at
                // several different products, and guessing which one is wrong is worse
                // than leaving it unmatched.
                $candidates = SupplierProduct::query()
                    ->where('supplier_id', $supplier->id)
                    ->where('supplier_article', 'like', $sku . '-%')
                    ->get();

                if ($candidates->count() === 1) {
                    $link = $candidates->first();
                } elseif ($candidates->count() > 1) {
                    $stats['no_link']++;
                    $rows[] = [(string) $sku, '—', 'неоднозначно: ' . $candidates->pluck('product_id')->implode(',')];
                    continue;
                }
            }

            if (! $link || ! $link->product_id) {
                $stats['no_link']++;
                $rows[] = [(string) $sku, '—', 'нет связи supplier_products'];
                continue;
            }

            $product = Product::query()->find($link->product_id);
            if (! $product) {
                $stats['no_product']++;
                $rows[] = [(string) $sku, (string) $link->product_id, 'товар не найден'];
                continue;
            }
            if ($product->is_archived) {
                $stats['archived']++;
                $rows[] = [(string) $sku, (string) $product->id, 'товар в архиве — пропущен'];
                continue;
            }

            $hasImages = ! empty($product->images);
            $needsImage = $overwrite || ! $hasImages;

            $existingSpecs = $this->decodeSpecs($product->specs);
            $hasSpecs = $this->hasRealSpecs($existingSpecs);
            $needsSpecs = $overwrite || ! $hasSpecs;

            $imageUrl = $data['image'] ?? null;
            $sourceSpecs = $data['specs'] ?? [];

            $actions = [];

            if ($needsImage) {
                if (! $imageUrl) {
                    $stats['no_image_source']++;
                    $actions[] = 'нет фото на jeelex.ru';
                } elseif (! $apply) {
                    $actions[] = 'фото будет добавлено';
                } else {
                    try {
                        if ($this->downloadImage($product, $imageUrl)) {
                            $stats['image_done']++;
                            $actions[] = 'фото сохранено';
                        } else {
                            $stats['errors']++;
                            $actions[] = 'ОШИБКА загрузки фото';
                        }
                    } catch (\Throwable $e) {
                        $stats['errors']++;
                        $actions[] = 'ОШИБКА фото: ' . $e->getMessage();
                    }
                }
            } else {
                $stats['image_skipped']++;
            }

            if ($needsSpecs) {
                if (empty($sourceSpecs)) {
                    $actions[] = 'нет характеристик на jeelex.ru';
                } else {
                    if ($apply) {
                        $this->applySpecs($product, $existingSpecs, $sourceSpecs, $overwrite);
                    }
                    $stats['specs_done']++;
                    $actions[] = 'характеристик: ' . count($sourceSpecs);
                }
            } else {
                $stats['specs_skipped']++;
            }

            if ($actions !== []) {
                $rows[] = [(string) $sku, (string) $product->id, implode('; ', $actions)];
            }
        }

        $this->newLine();
        $this->table(['Показатель', 'Количество'], [
            ['Строк в карте', $stats['total']],
            ['Нет связи с товаром (supplier_products)', $stats['no_link']],
            ['Товар не найден', $stats['no_product']],
            ['В архиве — пропущено', $stats['archived']],
            [$apply ? 'Фото сохранено' : 'Фото будет сохранено', $stats['image_done']],
            ['Фото пропущено (уже есть)', $stats['image_skipped']],
            ['Нет фото на jeelex.ru', $stats['no_image_source']],
            [$apply ? 'Характеристики записаны' : 'Характеристики будут записаны', $stats['specs_done']],
            ['Характеристики пропущены (уже есть)', $stats['specs_skipped']],
            ['Ошибок', $stats['errors']],
        ]);
        $this->table(['Артикул', 'Товар ID', 'Результат'], array_slice($rows, 0, 60));

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function decodeSpecs(mixed $specs): array
    {
        if (is_array($specs)) {
            return $specs;
        }
        $decoded = json_decode((string) $specs, true);
        return is_array($decoded) ? $decoded : [];
    }

    // The TM Management import writes bookkeeping fields (brand, supplier,
    // wholesale/retail price, price-list section, supplier article) into the
    // same specs array used for real technical characteristics. A product
    // carrying only those counts as having NO specs for enrichment purposes —
    // otherwise every imported product looks "already enriched" and the real
    // jeelex.ru specs (material, power, dimensions, ...) never get written.
    private const META_ONLY_SPEC_KEYS = [
        'бренд', 'поставщик', 'цена опт, byn', 'цена ррц, byn', 'раздел прайса', 'артикул поставщика',
    ];

    private function hasRealSpecs(array $specs): bool
    {
        foreach ($specs as $s) {
            $key = mb_strtolower((string) ($s['key'] ?? $s['name'] ?? ''));
            if ($key !== '' && ! in_array($key, self::META_ONLY_SPEC_KEYS, true)) {
                return true;
            }
        }
        return false;
    }

    private function applySpecs(Product $product, array $existingSpecs, array $sourceSpecs, bool $overwrite): void
    {
        $byKey = [];
        foreach ($existingSpecs as $s) {
            $key = (string) ($s['key'] ?? $s['name'] ?? '');
            if ($key !== '') {
                $byKey[mb_strtolower($key)] = ['key' => $key, 'value' => (string) ($s['value'] ?? ''), 'unit' => (string) ($s['unit'] ?? '')];
            }
        }
        foreach ($sourceSpecs as $k => $v) {
            $lk = mb_strtolower((string) $k);
            if ($overwrite || ! isset($byKey[$lk])) {
                $byKey[$lk] = ['key' => (string) $k, 'value' => (string) $v, 'unit' => ''];
            }
        }
        $merged = array_values($byKey);

        $product->specs = $merged;
        $product->save();

        app(ProductSourceEnricher::class)->syncSpecsToAttributeValues($product, $merged);
    }

    private function downloadImage(Product $product, string $url): bool
    {
        $body = $this->fetch($url, true);
        if ($body === null || strlen($body) < 2000) {
            return false;
        }
        $size = @getimagesizefromstring($body);
        if (! $size) {
            return false;
        }

        $dir = public_path(self::IMAGE_DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $ext = match ($size['mime'] ?? '') {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $file = $product->id . '.' . $ext;
        file_put_contents("{$dir}/{$file}", $body);

        $product->images = [self::IMAGE_DIR . '/' . $file];
        $product->save();

        return true;
    }

    private function fetch(string $url, bool $binary = false): ?string
    {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 30,
                'follow_location' => 1,
                'max_redirects' => 5,
                'header' => implode("\r\n", [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept: */*',
                    'Referer: https://jeelex.ru/',
                ]),
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);

        $body = @file_get_contents($url, false, $ctx);
        if ($body === false || (! $binary && strlen($body) < 500)) {
            return null;
        }
        return $body;
    }
}
