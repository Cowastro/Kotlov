<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\ProductSourceEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Enrich "Теплов и Сухов" MC Black chimney products with photos and material
 * spec, sourced from the matching product pages on teplov.ru.
 *
 * The mapping file (storage/app/imports/teplov-mc-black-media.json) was built
 * once by joining the approved SBG price list (sheet "MC Black", which
 * already carries the exact teplov.ru product name + diameter per supplier
 * article) against a scrape of https://teplov.ru/catalog/dymokhodnye_sistemy/
 * sistema_tis_ferrit_mc_black/ — see storage/app/imports/README for how to
 * regenerate it. This command only consumes the mapping; it makes no
 * outbound HTTP calls except to download the matched image.
 *
 * Usage:
 *   php artisan supplier:enrich-teplov-mc-black-media                (dry-run)
 *   php artisan supplier:enrich-teplov-mc-black-media --apply
 *   php artisan supplier:enrich-teplov-mc-black-media --apply --sku=TS.MCB.DFR.0115.78180
 */
class EnrichTeplovMcBlackMediaCommand extends Command
{
    protected $signature = 'supplier:enrich-teplov-mc-black-media
                            {--apply : Write photos and material spec to the DB (default: dry-run)}
                            {--overwrite : Replace existing photos/material even if already set}
                            {--limit= : Max products to process}
                            {--sku= : Process a single supplier article}
                            {--link= : Create a missing supplier_products link before processing, as "productId:supplierArticle"}';

    protected $description = 'Fill photos and material spec for Теплов и Сухов MC Black products from the teplov.ru media map';

    private const MAP_FILE = 'imports/teplov-mc-black-media.json';
    private const IMAGE_DIR = 'img/products/teplov-mcblack';

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

        $supplier = Supplier::query()->where('code', 'teplov')->first();
        if (! $supplier) {
            $this->error('Поставщик teplov не найден.');
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
            $product = Product::query()->find($linkProductId);
            if (! $product) {
                $this->error("Товар #{$linkProductId} не найден.");
                return self::FAILURE;
            }
            if ($apply) {
                SupplierProduct::query()->updateOrCreate(
                    ['supplier_id' => $supplier->id, 'supplier_article' => $linkArticle],
                    ['product_id' => $linkProductId, 'product_sku' => $product->sku, 'match_status' => 'matched', 'match_confidence' => 'manual_media_link']
                );
                $this->info("Связь создана: товар #{$linkProductId} ({$product->sku}) ↔ {$linkArticle}");
            } else {
                $this->info("[DRY RUN] Будет создана связь: товар #{$linkProductId} ({$product->sku}) ↔ {$linkArticle}");
            }
        }

        $this->line($apply
            ? '<fg=red;options=bold>APPLY — база будет изменена.</>'
            : '<fg=yellow;options=bold>DRY RUN — изменений не будет.</>');

        $stats = ['total' => 0, 'no_link' => 0, 'no_product' => 0, 'archived' => 0, 'image_done' => 0, 'image_skipped' => 0, 'material_done' => 0, 'material_skipped' => 0, 'no_image_source' => 0, 'errors' => 0];
        $rows = [];

        foreach ($map as $sku => $data) {
            if ($onlySku !== '' && $sku !== $onlySku) {
                continue;
            }
            if ($stats['total'] >= $limit) {
                break;
            }
            $stats['total']++;

            $link = SupplierProduct::query()
                ->where('supplier_id', $supplier->id)
                ->where('supplier_article', $sku)
                ->first();

            if (! $link || ! $link->product_id) {
                $stats['no_link']++;
                $rows[] = [$sku, '—', 'нет связи supplier_products'];
                continue;
            }

            $product = Product::query()->find($link->product_id);
            if (! $product) {
                $stats['no_product']++;
                $rows[] = [$sku, (string) $link->product_id, 'товар не найден'];
                continue;
            }
            if ($product->is_archived) {
                $stats['archived']++;
                $rows[] = [$sku, (string) $product->id, 'товар в архиве — пропущен'];
                continue;
            }

            $hasImages = ! empty($product->images);
            $needsImage = $overwrite || ! $hasImages;

            $existingSpecs = $this->decodeSpecs($product->specs);
            $hasMaterial = collect($existingSpecs)->contains(fn ($s) => mb_strtolower((string) ($s['key'] ?? '')) === 'материал');
            $needsMaterial = $overwrite || ! $hasMaterial;

            $imageUrl = $data['image'] ?? null;
            $steel = $data['steel'] ?? null;

            $actions = [];

            if ($needsImage) {
                if (! $imageUrl) {
                    $stats['no_image_source']++;
                    $actions[] = 'нет фото на teplov.ru';
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

            if ($needsMaterial) {
                if (! $steel) {
                    $actions[] = 'материал неизвестен';
                } else {
                    $materialValue = $this->materialLabel((string) $steel);
                    if ($apply) {
                        $this->applyMaterial($product, $existingSpecs, $materialValue, $overwrite);
                    }
                    $stats['material_done']++;
                    $actions[] = 'материал: ' . $materialValue;
                }
            } else {
                $stats['material_skipped']++;
            }

            if ($actions !== []) {
                $rows[] = [$sku, (string) $product->id, implode('; ', $actions)];
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
            ['Нет фото на teplov.ru', $stats['no_image_source']],
            [$apply ? 'Материал записан' : 'Материал будет записан', $stats['material_done']],
            ['Материал пропущен (уже есть)', $stats['material_skipped']],
            ['Ошибок', $stats['errors']],
        ]);
        $this->table(['Артикул', 'Товар ID', 'Результат'], array_slice($rows, 0, 40));

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

    private function materialLabel(string $steel): string
    {
        return match ($steel) {
            '430' => 'Ферритная сталь AISI 430, эмалевое покрытие MC Black',
            '304' => 'Нержавеющая сталь AISI 304',
            default => 'Сталь AISI ' . $steel,
        };
    }

    private function applyMaterial(Product $product, array $existingSpecs, string $materialValue, bool $overwrite): void
    {
        $byKey = [];
        foreach ($existingSpecs as $s) {
            $key = (string) ($s['key'] ?? $s['name'] ?? '');
            if ($key !== '') {
                $byKey[mb_strtolower($key)] = ['key' => $key, 'value' => (string) ($s['value'] ?? ''), 'unit' => (string) ($s['unit'] ?? '')];
            }
        }
        if ($overwrite || ! isset($byKey['материал'])) {
            $byKey['материал'] = ['key' => 'Материал', 'value' => $materialValue, 'unit' => ''];
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
                    'Referer: https://teplov.ru/',
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
