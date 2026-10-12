<?php

namespace App\Console\Commands;

use App\Models\IntegrationProduct;
use App\Models\Product;
use App\Services\Integrations\IntegrationProductMatchDecision;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReviveSbgExactProductsCommand extends Command
{
    protected $signature = 'integration:revive-sbg-exact-products {--apply : Persist the curated repair; default is a dry run}';

    protected $description = 'Repair and link dormant exact product cards from the SanBusinessGroup 1C queue.';

    public function handle(IntegrationProductMatchDecision $decision): int
    {
        $item = IntegrationProduct::query()->with('source')->where('external_code', 'УТ-00008980')->first();
        $product = Product::query()->where('sku', 'KOTLOV-022423')->first();
        $imageTemplate = Product::query()->where('sku', 'KOTLOV-022421')->first();
        $images = $imageTemplate ? $this->portableImages($imageTemplate) : [];

        $ready = $item
            && $product
            && $item->source?->name === '1С СанБизнесГруп'
            && $item->source?->partnerName() === 'ООО «СанБизнесГруп»'
            && ! $item->product_id
            && $item->match_status === 'ambiguous'
            && (float) $item->stock_quantity > 0
            && $this->containsAll($item->name, ['КПД', 'Отвод 45', '2мм', 'ф150'])
            && $this->containsAll($product->name, ['КПД', 'Отвод 45', '2мм', 'ф150'])
            && ! $product->is_active
            && ! $product->is_archived
            && (float) $product->price > 0
            && $product->category_id
            && $product->brand_id
            && $images !== [];

        if (! $ready) {
            $this->error('Blocked: the exact dormant-card guard failed.');

            return self::FAILURE;
        }

        if (! $this->option('apply')) {
            $this->info('DRY RUN: exact dormant card is ready for repair and linking.');
            $this->table(['1C code', 'site SKU', 'retail', 'stock'], [[
                $item->external_code,
                $product->sku,
                number_format((float) $product->price, 2, '.', '').' BYN',
                $item->formattedStockQuantity(),
            ]]);

            return self::SUCCESS;
        }

        $linked = DB::transaction(function () use ($decision, $images, $item, $product): ?Product {
            $name = 'КПД Отвод 45° 2 мм Ø150, чёрный';
            $product->update([
                'supplier_id' => $item->source?->supplier_id,
                'name' => $name,
                'slug' => 'kpd-otvod-45-2mm-f150-chernyy',
                'h1' => $name,
                'short_description' => 'Чёрный одностенный отвод КПД 45° диаметром 150 мм из стали толщиной 2 мм для изменения направления дымохода.',
                'content' => '<p><strong>КПД Отвод 45° 2 мм Ø150, чёрный</strong> — элемент одностенной дымоходной системы для поворота канала на 45 градусов.</p><p>Диаметр 150 мм и толщина стали 2 мм должны совпадать с параметрами остальных элементов дымохода. Цена сайта хранится отдельно от цен и остатков поставщиков.</p>',
                'images' => $images,
                'specs' => [
                    ['key' => 'Тип', 'value' => 'Отвод дымохода'],
                    ['key' => 'Угол', 'value' => '45°'],
                    ['key' => 'Диаметр', 'value' => '150 мм'],
                    ['key' => 'Толщина стали', 'value' => '2 мм'],
                    ['key' => 'Цвет', 'value' => 'Чёрный'],
                ],
                'is_active' => true,
                'meta_title' => $name.' — купить в Беларуси | KOTLOV',
                'meta_description' => 'Купить чёрный отвод КПД 45° Ø150 из стали 2 мм. Актуальная цена и наличие, доставка по Беларуси.',
            ]);

            return $decision->linkSelectedProduct($item, (int) $product->id);
        });

        if (! $linked) {
            $this->error('Blocked: the queue row changed during apply.');

            return self::FAILURE;
        }

        $this->info('APPLY: exact dormant card was repaired, activated and linked.');

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function portableImages(Product $template): array
    {
        return collect($template->imagePaths())
            ->map(function (string $path, int $index) use ($template): string {
                if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
                    || str_starts_with($path, 'product/') || str_starts_with($path, 'products/')
                    || str_starts_with($path, 'img/')) {
                    return $path;
                }

                $resolved = $template->imageUrl($index);

                return str_starts_with($resolved, '/proxy-image/')
                    ? substr($resolved, strlen('/proxy-image/'))
                    : '';
            })
            ->filter()
            ->values()
            ->all();
    }

    /** @param list<string> $fragments */
    private function containsAll(string $value, array $fragments): bool
    {
        $value = mb_strtolower(str_replace([' ', '*', 'ё'], ['', '', 'е'], $value));

        return collect($fragments)->every(fn (string $fragment): bool => str_contains(
            $value,
            mb_strtolower(str_replace([' ', '*', 'ё'], ['', '', 'е'], $fragment))
        ));
    }
}
