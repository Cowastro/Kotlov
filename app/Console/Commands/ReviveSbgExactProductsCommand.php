<?php

namespace App\Console\Commands;

use App\Models\IntegrationProduct;
use App\Models\Product;
use App\Services\Integrations\IntegrationProductMatchDecision;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReviveSbgExactProductsCommand extends Command
{
    private const KPD_SOURCE_URL = 'https://cheboksary.fornaks.ru/catalog/dymohod-schiedel-permeter-iz-stali/otvod-kpd-45-d-150-2-mm-chernyy/';

    private const KPD_IMAGES = [
        'https://cheboksary.fornaks.ru/upload/iblock/63b/%D0%9E%D1%82%D0%B2%D0%BE%D0%B4%2045.png',
        'https://cheboksary.fornaks.ru/upload/iblock/7ca/%D0%9E%D1%82%D0%B2%D0%BE%D0%B4%2045%202.png',
        'https://cheboksary.fornaks.ru/upload/iblock/4c7/%D0%BE%D1%82%D0%B2%D0%BE%D0%B4%2045%20%D1%81%D1%85.jpg',
    ];

    protected $signature = 'integration:revive-sbg-exact-products {--apply : Persist the curated repair; default is a dry run}';

    protected $description = 'Repair and link dormant exact product cards from the SanBusinessGroup 1C queue.';

    public function handle(IntegrationProductMatchDecision $decision): int
    {
        $item = IntegrationProduct::query()->with('source')->where('external_code', 'УТ-00008980')->first();
        $product = Product::query()->where('sku', 'KOTLOV-022423')->first();
        $images = self::KPD_IMAGES;

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
                'content_source_url' => self::KPD_SOURCE_URL,
                'specs' => [
                    ['key' => 'Тип', 'value' => 'Отвод дымохода'],
                    ['key' => 'Угол', 'value' => '45°'],
                    ['key' => 'Диаметр', 'value' => '150 мм'],
                    ['key' => 'Толщина стали', 'value' => '2 мм'],
                    ['key' => 'Материал', 'value' => 'Конструкционная сталь 08ПС'],
                    ['key' => 'Цвет', 'value' => 'Чёрный'],
                    ['key' => 'Рабочая температура', 'value' => 'до 800 °C'],
                    ['key' => 'Масса', 'value' => '2,3 кг'],
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
