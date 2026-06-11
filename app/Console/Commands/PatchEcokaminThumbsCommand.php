<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Одноразовая чистка превью каталога EcoKamin.
 *
 * До фикса sync-ecokamin-fireboxes сохранял listing-превью 133x200
 * первым кадром в products.images, и карточки на сайте получались
 * размытыми. Команда снимает пути -1.<ext> из products.images у всех
 * товаров поставщика ecokamin и удаляет соответствующие маленькие
 * файлы. После одного --apply на проде команду можно удалить.
 */
class PatchEcokaminThumbsCommand extends Command
{
    protected $signature = 'patch:ecokamin-thumbs
        {--apply : Write changes (without it — dry-run)}
        {--max-size=30000 : Удалять файл, только если он меньше этого размера в байтах}';

    protected $description = 'Удалить размытые превью EcoKamin из products.images и с диска.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $maxSize = (int) $this->option('max-size');

        $supplierId = DB::table('suppliers')->where('code', 'ecokamin')->value('id');
        if (! $supplierId) {
            $this->error('Поставщик ecokamin не найден.');
            return self::FAILURE;
        }

        $productIds = DB::table('supplier_products')
            ->where('supplier_id', $supplierId)
            ->whereNotNull('product_id')
            ->pluck('product_id');

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: products.images и файлы будут изменены.</>'
            : '<fg=yellow;options=bold>DRY RUN: ничего не изменяется.</>');

        $stats = ['products_updated' => 0, 'files_deleted' => 0, 'orphan_files_deleted' => 0, 'skipped_large' => 0];

        foreach ($productIds as $productId) {
            $row = DB::table('products')->where('id', $productId)->first(['id', 'sku', 'images']);
            if (! $row) {
                continue;
            }

            $images = json_decode((string) $row->images, true);
            if (! is_array($images) || $images === []) {
                continue;
            }

            $kept = [];
            $removed = [];
            foreach ($images as $path) {
                if ($this->isListingThumb((string) $path)) {
                    $removed[] = (string) $path;
                } else {
                    $kept[] = (string) $path;
                }
            }

            if ($removed === []) {
                continue;
            }

            foreach ($removed as $path) {
                $full = public_path($path);
                if (! is_file($full)) {
                    continue;
                }

                if (filesize($full) > $maxSize) {
                    $stats['skipped_large']++;
                    $this->warn(sprintf('  %s: %s слишком большой (%d байт) — не удаляю', $row->sku, $path, filesize($full)));
                    continue;
                }

                if ($apply) {
                    unlink($full);
                }
                $stats['files_deleted']++;
            }

            if ($apply) {
                DB::table('products')->where('id', $productId)->update([
                    'images' => json_encode(array_values($kept), JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            }

            $stats['products_updated']++;
            $this->line(sprintf('  %s: убрано %d превью, осталось %d фото', $row->sku, count($removed), count($kept)));
        }

        // "Сиротские" -1 файлы, не привязанные ни к одному товару.
        $dir = public_path('img/products/ecokamin');
        $usedFiles = [];
        foreach ($productIds as $productId) {
            $images = json_decode((string) DB::table('products')->where('id', $productId)->value('images'), true) ?: [];
            foreach ($images as $p) {
                $usedFiles[basename((string) $p)] = true;
            }
        }

        foreach (glob($dir . '/*-1.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [] as $file) {
            $name = basename($file);
            if (isset($usedFiles[$name])) {
                continue;
            }
            if (filesize($file) > $maxSize) {
                continue;
            }

            if ($apply) {
                unlink($file);
            }
            $stats['orphan_files_deleted']++;
        }

        $this->newLine();
        $this->table(['metric', 'count'], array_map(
            fn($k, $v) => [$k, $v],
            array_keys($stats),
            array_values($stats),
        ));

        if (! $apply) {
            $this->line('Запустите с --apply, чтобы применить изменения.');
        }

        return self::SUCCESS;
    }

    private function isListingThumb(string $path): bool
    {
        return (bool) preg_match('#img/products/ecokamin/.+-1\.(jpe?g|png|webp)$#i', $path);
    }
}
