<?php

namespace App\Console\Commands;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierSource;
use App\Models\SupplierSync;
use App\Services\SupplierProductAvailabilityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Теплоносители ZUBR / ZUBR Эко от производителя ООО «ЭкоСмартСервис»
 * (д. Станьково, Дзержинский р-н). Источник цен — коммерческие предложения
 * от 15.06.2026 № 15/06-1 (ZUBR Эко, пропиленгликоль) и № 15/06-2 (ZUBR,
 * этиленгликоль), цены за канистру с НДС 20 %. Характеристики — техкарты
 * производителя и этикетки (ТУ BY 691618104.006-2026, таблицы разбавления).
 *
 * Закупка фиксируется в supplier_products (match_confidence=manual_price_list,
 * поставка под заказ до 7 рабочих дней: 5 дней производителя + доставка).
 * Розница: --pricing=market (по умолчанию) — ~8 % ниже самого дешёвого
 * сопоставимого предложения на Onliner (21.10.2026), но не ниже закупки +15 %;
 * --pricing=markup15 — строго закупка +15 %.
 *
 *   php artisan catalog:publish-zubr-teplonositeli                 # dry run
 *   php artisan catalog:publish-zubr-teplonositeli --apply
 *   php artisan catalog:publish-zubr-teplonositeli --apply --pricing=markup15
 */
class PublishZubrHeatTransferFluidsCommand extends Command
{
    protected $signature = 'catalog:publish-zubr-teplonositeli
        {--apply : Записать поставщика, бренд, товары и связи}
        {--pricing=market : market — по рынку (не ниже +15 %), markup15 — закупка +15 %}';

    protected $description = 'Публикует теплоносители ZUBR и ZUBR Эко производителя ЭкоСмартСервис с ценами из КП 15.06.2026';

    public const SUPPLIER_CODE = 'ecosmartservice';

    public const BRAND_SLUG = 'zubr';

    public const CATEGORY_SLUG = 'teplonositeli';

    public const SYNC_KEY = 'ecosmartservice_zubr_price';

    private const DELIVERY_DAYS = 7;

    private const IMAGE_DIR = '/img/products/zubr/';

    private const DOCUMENTS = 'паспорт качества, паспорт безопасности, декларация о соответствии ЕАЭС, сертификат продукции собственного производства БелТПП';

    /**
     * Цены КП: [кг => [без НДС, с НДС, розница market, конкурент-ориентир]].
     */
    private const MODELS = [
        'zubr-30' => [
            'model' => 'ZUBR -30', 'line' => 'ZUBR', 'glycol' => 'eg', 'kind' => 'solution',
            'freeze' => -33, 'rated' => -30, 'boil' => '106', 'density' => '1,064', 'density_f' => 1.064,
            'ph' => '7,0–9,0', 'dyn' => '4,41', 'kin' => '4,11', 'cp' => '3,23', 'k' => '0,377',
            'color' => 'светло-розовый (флуоресцентный)',
            'packs' => [
                10 => [37.50, 45.00, 52, 'EcoTherm -30 C 10 кг от 48,76; MONOTHERM -30 10 кг 68,20'],
                20 => [74.17, 89.00, 103, 'EcoTherm -30 C 20 кг от 99,32; Технология Уюта -30 20 кг 100; Thermagent -30 C 20 кг 120'],
            ],
        ],
        'zubr-65' => [
            'model' => 'ZUBR -65', 'line' => 'ZUBR', 'glycol' => 'eg', 'kind' => 'diluted',
            'freeze' => -65, 'rated' => -65, 'boil' => '109', 'density' => '1,082', 'density_f' => 1.082,
            'ph' => '7,0–9,0', 'dyn' => '6,84', 'kin' => '6,31', 'cp' => '3,09', 'k' => '0,342',
            'color' => 'светло-розовый (флуоресцентный)',
            'dilution' => [[-20, 55, 45], [-25, 64, 36], [-30, 70, 30], [-40, 80, 20]],
            'packs' => [
                10 => [41.67, 50.00, 60, 'EcoTherm -65 C 10 кг от 65,96; Thermagent -65 C 10 кг от 96,50'],
                20 => [81.67, 98.00, 126, 'EcoTherm -65 C 20 кг от 137,42; Thermagent -65 C 20 кг от 150; Теплый дом -65 20 кг 160'],
            ],
        ],
        'zubr-100' => [
            'model' => 'ZUBR 100', 'line' => 'ZUBR', 'glycol' => 'eg', 'kind' => 'concentrate',
            'freeze' => null, 'rated' => null, 'boil' => '136', 'density' => '1,110', 'density_f' => 1.110,
            'ph' => '7,0–9,0', 'dyn' => '22,3', 'kin' => '19,9', 'cp' => null, 'k' => null,
            'color' => 'светло-розовый (флуоресцентный)',
            'dilution' => [[-20, 37, 63], [-25, 42, 58], [-30, 48, 52], [-40, 53, 47]],
            'packs' => [
                10 => [54.17, 65.00, 80, 'EcoTherm суперконцентрат 10 кг от 87,30'],
                20 => [106.67, 128.00, 160, '2 × EcoTherm суперконцентрат 10 кг = 174,60; MONOTHERM -95 20 кг 228,99'],
            ],
        ],
        'zubr-eko-30' => [
            'model' => 'ZUBR Эко -30', 'line' => 'ZUBR Эко', 'glycol' => 'pg', 'kind' => 'solution',
            'freeze' => -30, 'rated' => -30, 'boil' => '104', 'density' => '1,035', 'density_f' => 1.035,
            'ph' => '7,0–9,0', 'dyn' => '6,93', 'kin' => '6,55', 'cp' => '3,38', 'k' => '0,335',
            'color' => 'светло-зелёный (флуоресцентный)',
            'packs' => [
                10 => [49.17, 59.00, 97, 'PROTHERM -30 10 кг 105,43; Thermagent -30 C ЭКО 10 кг от 122,40'],
                20 => [96.67, 116.00, 156, 'Thermagent -30 C ЭКО 20 кг от 170; Warme Eco Pro 30 20 кг 180; PROTHERM -30 20 кг 195,34; Теплый дом ЭКО -30 20 кг 200'],
            ],
        ],
        'zubr-eko-65' => [
            'model' => 'ZUBR Эко -65', 'line' => 'ZUBR Эко', 'glycol' => 'pg', 'kind' => 'diluted',
            'freeze' => -65, 'rated' => -65, 'boil' => '107', 'density' => '1,040', 'density_f' => 1.040,
            'ph' => '7,0–9,0', 'dyn' => '10,8', 'kin' => '10,4', 'cp' => '3,12', 'k' => '0,301',
            'color' => 'светло-зелёный (флуоресцентный)',
            'dilution' => [[-20, 55, 45], [-25, 63, 37], [-30, 70, 30], [-40, 80, 20]],
            'packs' => [
                10 => [60.00, 72.00, 130, 'EcoTherm VitaPro -60 С 10 кг от 141,79'],
                20 => [118.33, 142.00, 224, 'EcoTherm VitaPro -60 С 20 кг от 243,70'],
            ],
        ],
        'zubr-eko-100' => [
            'model' => 'ZUBR Эко 100', 'line' => 'ZUBR Эко', 'glycol' => 'pg', 'kind' => 'concentrate',
            'freeze' => null, 'rated' => null, 'boil' => '140–150', 'density' => '1,041', 'density_f' => 1.041,
            'ph' => '7,0–9,0', 'dyn' => '65–70', 'kin' => '60–70', 'cp' => null, 'k' => null,
            'color' => 'светло-зелёный (флуоресцентный)',
            'dilution' => [[-20, 37, 63], [-25, 42, 58], [-30, 48, 52], [-40, 53, 47]],
            'packs' => [
                10 => [83.33, 100.00, 149, 'Прямого аналога-концентрата пропиленгликоля на Onliner нет; ZUBR Эко -65 10 кг = 130'],
                20 => [163.33, 196.00, 289, 'Прямого аналога-концентрата пропиленгликоля на Onliner нет; ZUBR Эко -65 20 кг = 224'],
            ],
        ],
    ];

    public function handle(SupplierProductAvailabilityService $availability): int
    {
        $apply = (bool) $this->option('apply');
        $pricing = (string) $this->option('pricing');
        if (! in_array($pricing, ['market', 'markup15'], true)) {
            $this->error('--pricing должен быть market или markup15.');

            return self::FAILURE;
        }

        $category = Category::query()->where('slug', self::CATEGORY_SLUG)->first();
        if (! $category) {
            $this->error('Категория teplonositeli не найдена.');

            return self::FAILURE;
        }

        foreach (array_keys(self::MODELS) as $key) {
            foreach ($this->imagesFor($key) as $image) {
                if (! is_file(public_path(ltrim($image, '/')))) {
                    $this->error('Нет изображения: '.$image);

                    return self::FAILURE;
                }
            }
        }

        $attributes = $this->filterAttributes($category);
        $rows = $this->plannedRows($pricing);

        $conflicts = 0;
        foreach ($rows as $row) {
            $matches = Product::query()->where('slug', $row['slug'])->orWhere('sku', $row['sku'])->get(['id', 'slug', 'sku']);
            if ($matches->count() > 1 || ($matches->isNotEmpty() && ($matches->first()->slug !== $row['slug'] || $matches->first()->sku !== $row['sku']))) {
                $this->error('Конфликт slug/sku, товар не будет перезаписан: '.$row['slug']);
                $conflicts++;
            }
        }
        if ($conflicts > 0) {
            $this->line('metric.zubr.identity_conflicts='.$conflicts);

            return self::FAILURE;
        }

        $this->info('Режим: '.($apply ? 'APPLY' : 'DRY RUN').', ценообразование: '.$pricing);
        $this->table(
            ['Товар', 'Закупка с НДС', 'Розница', 'Наценка %'],
            array_map(fn (array $row): array => [
                $row['name'],
                number_format($row['purchase_vat'], 2, ',', ' '),
                number_format($row['retail'], 2, ',', ' '),
                number_format(($row['retail'] / $row['purchase_vat'] - 1) * 100, 1, ',', ''),
            ], $rows)
        );
        $this->line('metric.zubr.planned_products='.count($rows));
        $this->line('metric.zubr.filter_attributes='.count(array_filter($attributes)));

        if (! $apply) {
            $this->warn('Предпросмотр: база не изменена. Для записи добавьте --apply.');

            return self::SUCCESS;
        }

        $stats = DB::transaction(function () use ($rows, $category, $attributes, $availability): array {
            $stats = ['created' => 0, 'updated' => 0, 'offers' => 0, 'attribute_values' => 0];
            $supplier = $this->ensureSupplier();
            $sync = $this->ensureSync($supplier);
            $brand = $this->ensureBrand();
            $supplier->brands()->syncWithoutDetaching([$brand->id]);
            $touched = [];

            foreach ($rows as $row) {
                $product = Product::query()->where('slug', $row['slug'])->first();
                $isNew = ! $product;
                $product ??= new Product;
                $product->fill([
                    'category_id' => $category->id,
                    'brand_id' => $brand->id,
                    'name' => $row['name'],
                    'h1' => $row['name'],
                    'slug' => $row['slug'],
                    'sku' => $row['sku'],
                    'price' => $row['retail'],
                    'price_old' => null,
                    'currency' => 'BYN',
                    'unit' => 'шт',
                    'weight' => $row['kg'],
                    'warranty' => 'Срок эксплуатации не менее 10 лет, хранения — 2 года',
                    'short_description' => $row['short'],
                    'content' => $row['content'],
                    'images' => $row['images'],
                    'specs' => $row['specs'],
                    'meta_title' => $row['meta_title'],
                    'meta_description' => $row['meta_description'],
                    'is_active' => true,
                    'is_archived' => false,
                    'is_new' => true,
                    'requires_price_confirmation' => false,
                    'manual_stock_management' => false,
                    'variant_selector_excluded' => false,
                ]);
                if ($isNew) {
                    $product->fill([
                        'in_stock' => false,
                        'stock_qty' => null,
                        'availability_status' => Product::AVAILABILITY_CHECK,
                    ]);
                }
                if ($product->isDirty()) {
                    $product->save();
                    $stats[$isNew ? 'created' : 'updated']++;
                }

                $article = $row['article'];
                SupplierProduct::query()->updateOrCreate([
                    'supplier_id' => $supplier->id,
                    'supplier_article_normalized' => mb_strtolower($article),
                ], [
                    'supplier_sync_id' => $sync->id,
                    'product_id' => $product->id,
                    'product_sku' => $product->sku,
                    'supplier_article' => $article,
                    'supplier_article_compact' => preg_replace('/[^\pL\pN]+/u', '', mb_strtolower($article)),
                    'supplier_name' => $row['supplier_name'],
                    'source_url' => null,
                    'price' => $row['purchase_vat'],
                    'currency' => 'BYN',
                    'currency_rate' => 1,
                    'price_byn' => $row['purchase_vat'],
                    'in_stock' => false,
                    'stock_quantity' => null,
                    'stock_status' => 'preorder',
                    'stock_text' => 'Под заказ: отгрузка производителем до 5 рабочих дней после 100 % предоплаты',
                    'warehouse_name' => 'Производство, д. Станьково',
                    'delivery_days' => self::DELIVERY_DAYS,
                    'last_stock_synced_at' => null,
                    'match_status' => 'matched',
                    'match_confidence' => 'manual_price_list',
                    'raw' => [
                        'document' => $row['document'],
                        'document_date' => '2026-06-15',
                        'price_without_vat' => $row['purchase_net'],
                        'vat_rate' => 20,
                        'price_with_vat' => $row['purchase_vat'],
                        'volume_discount' => $row['volume_discount'],
                        'payment_terms' => '100 % предоплата',
                        'pricing_mode' => $row['pricing_mode'],
                        'retail_markup_15' => $row['retail_markup15'],
                        'market_reference_onliner_2026_10_21' => $row['market_reference'],
                    ],
                    'last_synced_at' => now(),
                ]);
                $stats['offers']++;

                foreach ($attributes as $name => $attribute) {
                    $optionName = $name === 'Вид жидкости'
                        ? ($row['kind'] === 'concentrate' ? 'Концентрат' : 'Раствор')
                        : ($row['glycol'] === 'eg' ? 'Этиленгликоль' : 'Пропиленгликоль');
                    $option = $attribute ? AttributeOption::query()->where('attribute_id', $attribute->id)->where('name', $optionName)->first() : null;
                    if (! $option) {
                        continue;
                    }
                    ProductAttributeValue::query()->updateOrCreate(
                        ['product_id' => $product->id, 'attribute_id' => $attribute->id],
                        ['option_id' => $option->id, 'value' => null, 'is_checked' => null]
                    );
                    $stats['attribute_values']++;
                }

                $touched[] = (int) $product->id;
            }

            $availability->refreshMany($touched);
            $sync->forceFill(['last_run_at' => now(), 'last_status' => 'updated', 'last_exit_code' => 0])->save();

            return $stats;
        });

        foreach ($stats as $key => $value) {
            $this->line("metric.zubr.{$key}={$value}");
        }

        return self::SUCCESS;
    }

    /** @return array<string, ?Attribute> */
    private function filterAttributes(Category $category): array
    {
        $result = [];
        foreach (['Вид жидкости', 'Основа жидкости'] as $name) {
            $result[$name] = Attribute::query()->where('name', $name)
                ->orderByRaw('CASE WHEN category_id = ? THEN 0 ELSE 1 END', [$category->id])
                ->first();
            if (! $result[$name]) {
                $this->warn('Атрибут фильтра не найден: '.$name);
            }
        }

        return $result;
    }

    private function ensureSupplier(): Supplier
    {
        $supplier = Supplier::query()->firstOrNew(['code' => self::SUPPLIER_CODE]);
        $supplier->forceFill([
            'name' => $supplier->exists ? $supplier->name : 'ЭкоСмартСервис (теплоносители ZUBR)',
            'currency' => 'BYN',
            'currency_rate' => 1,
            'contact' => 'ООО «ЭкоСмартСервис», УНП 691618104. 222731, Минская обл., Дзержинский р-н, Станьковский с/с, д. Станьково, здание с инв. №620/С-22140, ком. 27. Тел.: 8 01716 40-500, +375 44 719-84-44, ecosystems1@mail.ru. Директор Д. В. Гаевский.',
            'communication_channels' => [
                ['type' => 'phone', 'name' => 'Офис', 'value' => '+375 1716 40-500', 'note' => 'Городской'],
                ['type' => 'phone', 'name' => 'Мобильный', 'value' => '+375 44 719-84-44', 'note' => null],
                ['type' => 'email', 'name' => 'Заказы', 'value' => 'ecosystems1@mail.ru', 'note' => null],
                ['type' => 'other', 'name' => 'Instagram', 'value' => '@zubr_teplonositeli', 'note' => null],
            ],
            'notes' => 'Собственное производство теплоносителей ZUBR (этиленгликоль) и ZUBR Эко (пропиленгликоль). Прайс: КП 15.06.2026 № 15/06-2 (ZUBR) и № 15/06-1 (ZUBR Эко), цены за канистру с НДС 20 %. Скидка от 500 кг: ZUBR 5 %, ZUBR Эко 7 %. Оплата 100 % предоплата, отгрузка 5 рабочих дней. Фасовка: канистры 10/20 кг, бочки 220 кг, IBC 1000 кг. Документы: '.self::DOCUMENTS.'. Срок действия КП — 10 дней: перед заказом подтвердить цену.',
            'is_active' => true,
        ])->save();

        SupplierSource::query()->updateOrCreate([
            'supplier_id' => $supplier->id,
            'name' => 'ЭкоСмартСервис — КП 15.06.2026 (ZUBR и ZUBR Эко)',
        ], [
            'type' => 'email',
            'config' => [
                'documents' => ['№ 15/06-2 — ZUBR (этиленгликоль)', '№ 15/06-1 — ZUBR Эко (пропиленгликоль)'],
                'source_date' => '2026-06-15',
                'pricing' => 'Цена за канистру 10/20 кг с НДС 20 %',
                'availability' => 'Под заказ, 5 рабочих дней после предоплаты',
            ],
            'schedule_cron' => 'После получения нового КП',
            'is_active' => true,
        ]);

        return $supplier;
    }

    private function ensureSync(Supplier $supplier): SupplierSync
    {
        return SupplierSync::query()->updateOrCreate(['key' => self::SYNC_KEY], [
            'supplier_id' => $supplier->id,
            'name' => 'ЭкоСмартСервис',
            'code' => self::SUPPLIER_CODE,
            'title' => 'ЭкоСмартСервис: теплоносители ZUBR из КП',
            'description' => 'Ручной прайс из КП производителя: закупка с НДС, розница по рынку (не ниже +15 %). Обновлять при получении нового КП.',
            'command' => 'catalog:publish-zubr-teplonositeli',
            'source_url' => null,
            'is_active' => true,
        ]);
    }

    private function ensureBrand(): Brand
    {
        $brand = Brand::query()->where('slug', self::BRAND_SLUG)->first()
            ?? Brand::query()->whereRaw('LOWER(name) = ?', ['zubr'])->first()
            ?? new Brand;
        $isOurs = ! $brand->exists || blank($brand->content) || str_contains((string) $brand->content, 'data-zubr-brand');
        $brand->fill([
            'name' => $brand->exists ? $brand->name : 'ZUBR',
            'slug' => $brand->exists ? $brand->slug : self::BRAND_SLUG,
            'is_active' => true,
        ]);
        if ($isOurs) {
            $brand->fill([
                'h1' => 'Теплоносители ZUBR — белорусское производство',
                'logo' => $brand->logo ?: 'img/brands/zubr-red.png',
                'country' => 'Беларусь',
                'producer' => 'ООО «ЭкоСмартСервис»',
                'content' => $this->brandContent(),
                'meta_title' => 'Теплоносители ZUBR и ZUBR Эко — купить антифриз для отопления в %city%',
                'meta_description' => 'Белорусские теплоносители ZUBR на этиленгликоле и ZUBR Эко на пропиленгликоле: -30, -65 и суперконцентраты, канистры 10 и 20 кг. Документы ЕАЭС, срок службы 10 лет.',
            ]);
        }
        $brand->save();

        return $brand;
    }

    /** @return array<int, string> */
    private function imagesFor(string $key): array
    {
        $images = [self::IMAGE_DIR.$key.'.webp', self::IMAGE_DIR.$key.'-harakteristiki.webp'];
        if (isset(self::MODELS[$key]['dilution'])) {
            $images[] = self::IMAGE_DIR.$key.'-razbavlenie.webp';
        }

        return $images;
    }

    /** @return array<int, array<string, mixed>> */
    private function plannedRows(string $pricing): array
    {
        $rows = [];
        foreach (self::MODELS as $key => $m) {
            foreach ($m['packs'] as $kg => [$net, $vat, $market, $reference]) {
                $markup15 = (float) ceil($vat * 1.15);
                $retail = $pricing === 'markup15' ? $markup15 : (float) max($market, $markup15);
                $name = $this->productName($m, $kg);
                $isEco = $m['glycol'] === 'pg';
                $rows[] = [
                    'key' => $key,
                    'kg' => $kg,
                    'name' => $name,
                    'slug' => 'teplonositel-'.$key.'-'.($isEco ? 'propilenglikol' : 'etilenglikol').'-'.$kg.'-kg',
                    'sku' => 'ZUBR-'.strtoupper(str_replace(['zubr-', 'eko-'], ['', 'ECO-'], $key)).'-'.$kg,
                    'article' => $m['model'].' '.$kg.' кг',
                    'supplier_name' => ($m['kind'] === 'concentrate' ? 'Теплоноситель '.$m['model'].' (суперконцентрат)' : 'Теплоноситель '.$m['model']).', '.$kg.' кг',
                    'document' => $isEco ? 'КП № 15/06-1 от 15.06.2026' : 'КП № 15/06-2 от 15.06.2026',
                    'volume_discount' => $isEco ? '7 % при заказе от 500 кг' : '5 % при заказе от 500 кг',
                    'purchase_net' => $net,
                    'purchase_vat' => $vat,
                    'retail' => $retail,
                    'retail_markup15' => $markup15,
                    'pricing_mode' => $pricing,
                    'market_reference' => $reference,
                    'glycol' => $m['glycol'],
                    'kind' => $m['kind'],
                    'images' => $this->imagesFor($key),
                    'specs' => $this->specs($m, $kg),
                    'short' => $this->shortDescription($m, $kg),
                    'content' => $this->content($key, $m, $kg),
                    'meta_title' => $this->metaTitle($m, $kg),
                    'meta_description' => $this->metaDescription($m, $kg),
                ];
            }
        }

        return $rows;
    }

    private function productName(array $m, int $kg): string
    {
        $base = $m['glycol'] === 'eg' ? 'на этиленгликоле' : 'на пропиленгликоле';
        $model = $m['kind'] === 'concentrate' ? $m['model'].' суперконцентрат' : $m['model'];

        return "Теплоноситель {$model} {$base}, {$kg} кг";
    }

    private function metaTitle(array $m, int $kg): string
    {
        $base = $m['glycol'] === 'eg' ? 'этиленгликоль' : 'пропиленгликоль';
        $model = $m['kind'] === 'concentrate' ? $m['model'].' концентрат' : $m['model'];

        return "Теплоноситель {$model} ({$base}) {$kg} кг — купить в %city%";
    }

    private function metaDescription(array $m, int $kg): string
    {
        $base = $m['glycol'] === 'eg' ? 'этиленгликоле' : 'пропиленгликоле';
        $protection = match ($m['kind']) {
            'concentrate' => 'суперконцентрат: разбавьте водой до нужной температуры от −20 до −40 °C',
            'diluted' => 'защита до −65 °C, можно разбавлять водой до −20…−40 °C',
            default => 'готов к заливке, защита до '.$this->minus($m['freeze']).' °C',
        };

        return "Теплоноситель {$m['model']} на {$base}, канистра {$kg} кг: {$protection}. Ингибиторы коррозии, срок службы 10 лет, белорусское производство, документы ЕАЭС. Доставка по Беларуси.";
    }

    private function shortDescription(array $m, int $kg): string
    {
        $litres = $this->litres($m, $kg);
        $text = match ($m['kind']) {
            'concentrate' => "Суперконцентрат {$m['model']} — 100 % основа с полным пакетом присадок. Разбавляется водой: из {$kg} кг получается до ".$this->fmt($kg * 100 / 37, 0).' кг теплоносителя на −20 °C или '.$this->fmt($kg * 100 / 48, 1).' кг на −30 °C.',
            'diluted' => "Теплоноситель {$m['model']} с защитой до −65 °C для неотапливаемых домов и северных регионов. Можно разбавить водой до −30 °C (70/30) и получить ".$this->fmt($kg / 0.7, 1).' кг готового раствора.',
            default => "Готовый к заливке теплоноситель {$m['model']}: начало кристаллизации ".$this->minus($m['freeze']).' °C, не требует разбавления.',
        };
        $base = $m['glycol'] === 'eg'
            ? ' Этиленгликоль с ингибиторами коррозии, биоцидом и пеногасителем.'
            : ' Пропиленгликоль — нетоксичная основа для двухконтурных котлов и систем рядом с ГВС.';

        return $text.$base." Канистра {$kg} кг (≈ ".$this->fmt($litres, 1).' л). Срок службы — не менее 10 лет.';
    }

    /** @return array<int, array{key:string, value:string}> */
    private function specs(array $m, int $kg): array
    {
        $isEco = $m['glycol'] === 'pg';
        $rows = [
            'Бренд' => 'ZUBR',
            'Линейка' => $isEco ? 'ZUBR Эко (пропиленгликоль)' : 'ZUBR (этиленгликоль)',
            'Модель' => $m['model'],
            'Фасовка' => $kg.' кг, канистра',
            'Объём, примерно' => '≈ '.$this->fmt($this->litres($m, $kg), 1).' л',
            'Основа жидкости' => $isEco ? 'Пропиленгликоль' : 'Этиленгликоль',
            'Вид жидкости' => $m['kind'] === 'concentrate' ? 'Концентрат' : 'Раствор',
            'Готовность к применению' => match ($m['kind']) {
                'concentrate' => 'Суперконцентрат, разбавляется водой',
                'diluted' => 'Готов к заливке, допускается разбавление водой',
                default => 'Готов к заливке, не разбавлять',
            },
        ];
        if ($m['freeze'] !== null) {
            $rows['Температура начала кристаллизации'] = $this->minus($m['freeze']).' °C';
        }
        if (isset($m['dilution'])) {
            $rows['Разбавление для −30 °C'] = collect($m['dilution'])->firstWhere(0, -30)[1].' % теплоносителя + '.collect($m['dilution'])->firstWhere(0, -30)[2].' % воды';
        }
        $rows += [
            'Температура кипения при 1 бар' => $m['boil'].' °C',
            'Плотность при 20 °C, не выше' => $m['density'].' г/см³',
            'Водородный показатель (pH)' => $m['ph'],
            'Динамическая вязкость при 20 °C' => $m['dyn'].' мПа·с',
            'Кинематическая вязкость при 20 °C' => $m['kin'].' мм²/с',
        ];
        if ($m['cp'] !== null) {
            $rows['Удельная теплоёмкость при 20 °C'] = $m['cp'].' кДж/(кг·К)';
        }
        if ($m['k'] !== null) {
            $rows['Теплопроводность при 20 °C'] = $m['k'].' Вт/(м·К)';
        }
        $rows += [
            'Цвет' => $m['color'],
            'Состав' => ($isEco ? 'Пропиленгликоль' : 'Этиленгликоль').($m['kind'] === 'concentrate' ? '' : ', деминерализованная вода').', ингибиторы коррозии с биоцидом, антинакипные и стабилизирующие присадки, пеногаситель, флуоресцентный краситель',
            'Тип систем' => $isEco ? 'Закрытые и открытые системы отопления, вентиляции и кондиционирования' : 'Закрытые системы отопления, вентиляции и кондиционирования',
            'Применение' => $isEco
                ? 'Отопление домов, двухконтурные котлы, тёплые полы, тепловые насосы, чиллеры, пищевые объекты, HVAC'
                : 'Отопление домов и дач, котельные, тёплые полы, тепловые насосы, чиллеры, промышленные объекты, HVAC',
            'Срок эксплуатации' => 'Не менее 10 лет при соблюдении руководства по применению',
            'Гарантийный срок хранения' => '2 года',
            'Нормативный документ' => 'ТУ BY 691618104.006-2026',
            'Соответствие' => 'ТР ТС 030/2012',
            'Документы при отгрузке' => mb_strtoupper(mb_substr(self::DOCUMENTS, 0, 1)).mb_substr(self::DOCUMENTS, 1),
            'Другие фасовки под заказ' => 'Бочка 220 кг, еврокуб IBC 1000 кг',
            'Производитель' => 'ООО «ЭкоСмартСервис», Беларусь',
            'Страна производства' => 'Беларусь',
        ];

        return collect($rows)->map(fn ($value, $key) => ['key' => $key, 'value' => (string) $value])->values()->all();
    }

    private function content(string $key, array $m, int $kg): string
    {
        $isEco = $m['glycol'] === 'pg';
        $e = fn (string $text): string => htmlspecialchars($text, ENT_QUOTES);
        $name = $this->productName($m, $kg);
        $litres = $this->fmt($this->litres($m, $kg), 1);
        $baseWord = $isEco ? 'пропиленгликоля' : 'этиленгликоля';
        $color = $isEco ? 'светло-зелёный' : 'светло-розовый';
        $otherPack = $kg === 10 ? 20 : 10;
        $otherUrl = '/teplonositeli/teplonositel-'.$key.'-'.($isEco ? 'propilenglikol' : 'etilenglikol').'-'.$otherPack.'-kg';
        $img = self::IMAGE_DIR.$key;

        $intro = match ($m['kind']) {
            'concentrate' => "<p><strong>{$e($m['model'])}</strong> — суперконцентрат на основе 100 % {$baseWord} с полным пакетом присадок. Это самый экономичный вариант: вы сами разбавляете его водой до нужной температуры защиты и не переплачиваете за воду в канистре. Из {$kg} кг концентрата получается до <strong>".$this->fmt($kg * 100 / 37, 0).' кг</strong> теплоносителя с защитой до −20 °C или <strong>'.$this->fmt($kg * 100 / 48, 1).' кг</strong> — до −30 °C.</p>',
            'diluted' => "<p><strong>{$e($m['model'])}</strong> — теплоноситель на основе {$baseWord} с температурой начала кристаллизации <strong>−65 °C</strong>. Подходит для домов, которые зимой остаются без отопления, и для северных регионов. Если такой запас не нужен, его можно разбавить водой: при пропорции 70/30 получается раствор с защитой до −30 °C — в канистре {$kg} кг выходит около ".$this->fmt($kg / 0.7, 1).' кг готового теплоносителя.</p>',
            default => "<p><strong>{$e($m['model'])}</strong> — готовый к заливке теплоноситель на основе {$baseWord}. Начало кристаллизации — <strong>".$this->minus($m['freeze']).' °C</strong>, разбавлять водой не нужно: залили — и система защищена. Это оптимальный выбор для большинства частных домов Беларуси с постоянным или периодическим отоплением.</p>',
        };

        $whyBase = $isEco
            ? '<li><strong>Пропиленгликоль вместо этиленгликоля.</strong> Нетоксичная основа: подходит для двухконтурных котлов, систем рядом с горячим водоснабжением и объектов пищевой промышленности.</li><li><strong>Для открытых и закрытых систем.</strong> Производитель допускает применение в открытых системах отопления.</li>'
            : '<li><strong>Этиленгликоль с низкой вязкостью и высокой теплоёмкостью.</strong> Хорошо прокачивается насосом и эффективно переносит тепло — оптимальное соотношение цены и характеристик для закрытых систем.</li><li><strong>Совместим с котлами всех типов</strong> в закрытых системах отопления.</li>';

        $html = "<h2>{$e($name)}</h2>\n{$intro}\n";
        $html .= '<p>Теплоноситель производится в Беларуси (д. Станьково, Минская обл.) по ТУ BY 691618104.006-2026 и соответствует ТР ТС 030/2012. Кроме гликоля в составе — карбоксилатные ингибиторы коррозии с биоцидом, который препятствует размножению бактерий и образованию грибков, антинакипные и стабилизирующие присадки, пеногаситель и флуоресцентный '.$color.' краситель: по свечению легко найти место протечки ультрафиолетовым фонариком.</p>'."\n";
        $html .= "<h3>Почему выбирают ZUBR</h3>\n<ul>{$whyBase}<li><strong>Защита металлов от коррозии.</strong> Пакет ингибиторов защищает сталь, чугун, медь, латунь и алюминиевые сплавы теплообменников и радиаторов.</li><li><strong>Не пенится и не образует отложений.</strong> Пеногаситель и антинакипные присадки берегут насос и теплообменник котла.</li><li><strong>10 отопительных сезонов.</strong> При соблюдении правил эксплуатации и отсутствии подсоса воздуха свойства сохраняются не менее 10 лет.</li><li><strong>Полный пакет документов:</strong> ".self::DOCUMENTS.".</li></ul>\n";

        $html .= '<figure><img src="'.$img.'-harakteristiki.webp" alt="'.$e($m['model']).': основные характеристики" width="1200" height="1200" loading="lazy" style="max-width:100%;height:auto"></figure>'."\n";

        $html .= "<h3>Где применяется</h3>\n<ul>";
        $html .= $isEco
            ? '<li>системы отопления частных домов, дач и бань — открытые и закрытые;</li><li>двухконтурные газовые котлы и системы с бойлером косвенного нагрева;</li><li>тёплые водяные полы и тепловые насосы;</li><li>чиллеры, системы вентиляции и кондиционирования (HVAC);</li><li>объекты пищевой промышленности, где важна безопасность теплоносителя.</li>'
            : '<li>закрытые системы отопления частных домов и дач, в том числе с периодическим проживанием;</li><li>котельные и инженерные системы зданий;</li><li>тёплые водяные полы и тепловые насосы;</li><li>чиллеры, холодильные агрегаты, системы вентиляции и кондиционирования (HVAC);</li><li>охлаждение промышленного оборудования и станков.</li>';
        $html .= "</ul>\n";

        if (isset($m['dilution'])) {
            $html .= "<h3>Как разбавить {$e($m['model'])}</h3>\n<p>Пропорции по массе — по таблице на этикетке производителя. Разбавляйте дистиллированной или деминерализованной водой: жёсткая водопроводная вода даёт накипь и снижает ресурс присадок.</p>\n";
            $html .= '<table class="table"><thead><tr><th>Защита до</th><th>'.$e($m['model']).'</th><th>Вода</th><th>Готового раствора из '.$kg.' кг</th></tr></thead><tbody>';
            foreach ($m['dilution'] as [$t, $share, $water]) {
                $html .= '<tr><td>'.$this->minus($t).' °C</td><td>'.$share.' %</td><td>'.$water.' %</td><td>≈ '.$this->fmt($kg * 100 / $share, 1).' кг</td></tr>';
            }
            $html .= "</tbody></table>\n";
            $html .= '<figure><img src="'.$img.'-razbavlenie.webp" alt="Таблица разбавления '.$e($m['model']).' водой" width="1200" height="1200" loading="lazy" style="max-width:100%;height:auto"></figure>'."\n";
        }

        $html .= "<h3>Сколько теплоносителя нужно</h3>\n<p>Объём системы складывается из объёма котла, радиаторов, труб и расширительного бака (заполняется примерно на треть). Ориентиры: секция алюминиевого или биметаллического радиатора — 0,2–0,45 л, стальной панельный радиатор 500×1000 мм тип 22 — около 5–6 л, 10 м трубы Ø20 мм (внутр. ~16 мм) — около 2 л, Ø26 мм — около 3,5 л. Типичная система дома 100–150 м² содержит 80–150 л.</p>\n";
        $html .= "<p>В канистре {$kg} кг — около <strong>{$litres} л</strong> (плотность {$m['density']} г/см³). Берите с запасом 10–15 % на доливку. Для объёма от 500 кг доступны бочки 220 кг и еврокубы 1000 кг — <a href=\"/contacts\">запросите расчёт</a>.</p>\n";

        $html .= "<h3>Как правильно залить</h3>\n<ol><li>Промойте систему и слейте воду полностью — остатки воды разбавят теплоноситель и поднимут температуру замерзания.</li><li>Не смешивайте ZUBR с теплоносителями других марок и на другой основе: пакеты присадок могут быть несовместимы.</li><li>Заливайте насосом через нижнюю точку системы, выпуская воздух через краны Маевского и автоматические воздухоотводчики. Воздух — главный враг ингибиторов.</li><li>Используйте прокладки и уплотнения, стойкие к гликолям (EPDM, паронит, FUM-лента, специальные пасты). Лён с обычной пастой и оцинкованные трубы для гликолевых систем не рекомендуются.</li><li>Раз в отопительный сезон проверяйте цвет, прозрачность и pH (норма 7,0–9,0). Помутнение, осадок или запах — повод проверить систему.</li></ol>\n";

        $html .= "<h3>Этиленгликоль или пропиленгликоль</h3>\n<table class=\"table\"><thead><tr><th></th><th>ZUBR (этиленгликоль)</th><th>ZUBR Эко (пропиленгликоль)</th></tr></thead><tbody><tr><td>Вязкость и теплоёмкость</td><td>Ниже вязкость, выше теплоотдача</td><td>Вязкость выше, нужен насос с запасом напора</td></tr><tr><td>Безопасность</td><td>Токсичен, только закрытые системы</td><td>Нетоксичен, допускаются открытые системы</td></tr><tr><td>Двухконтурные котлы, пищевые объекты</td><td>Не рекомендуется</td><td>Подходит</td></tr><tr><td>Цена</td><td>Доступнее</td><td>Дороже</td></tr></tbody></table>\n";
        $html .= '<p>Вся линейка: <a href="/teplonositeli/teplonositel-zubr-30-etilenglikol-20-kg">ZUBR -30</a>, <a href="/teplonositeli/teplonositel-zubr-65-etilenglikol-20-kg">ZUBR -65</a>, <a href="/teplonositeli/teplonositel-zubr-100-etilenglikol-20-kg">суперконцентрат ZUBR 100</a>, <a href="/teplonositeli/teplonositel-zubr-eko-30-propilenglikol-20-kg">ZUBR Эко -30</a>, <a href="/teplonositeli/teplonositel-zubr-eko-65-propilenglikol-20-kg">ZUBR Эко -65</a> и <a href="/teplonositeli/teplonositel-zubr-eko-100-propilenglikol-20-kg">суперконцентрат ZUBR Эко 100</a>. Эта же модель в канистре '.$otherPack.' кг — <a href="'.$otherUrl.'">'.$e($this->productName($m, $otherPack)).'</a>.</p>'."\n";

        $faq = [
            ['Можно ли разбавлять '.$m['model'].' водой?', match ($m['kind']) {
                'concentrate' => 'Не только можно, а нужно: суперконцентрат в чистом виде не заливают. Пропорции для −20, −25, −30 и −40 °C приведены в таблице выше.',
                'diluted' => 'Да. При разбавлении 70/30 (теплоноситель/вода) получается раствор с защитой до −30 °C, при 55/45 — до −20 °C. Пропорции — в таблице выше.',
                default => 'Нет. '.$m['model'].' уже разбавлен до рабочей концентрации. Добавление воды поднимет температуру замерзания и ослабит защиту от коррозии.',
            }],
            ['Подходит ли для двухконтурного котла?', $isEco
                ? 'Да. Пропиленгликоль нетоксичен, поэтому ZUBR Эко рекомендуется для двухконтурных котлов и систем с бойлером косвенного нагрева. Перед заливкой проверьте, что производитель котла допускает гликолевые теплоносители, и при необходимости учтите снижение мощности на 10–15 %.'
                : 'Для двухконтурных котлов и систем, где отопительный контур граничит с горячей водой, выбирайте нетоксичный ZUBR Эко на пропиленгликоле. ZUBR на этиленгликоле предназначен для закрытых систем без контакта с питьевой водой.'],
            ['Как часто менять теплоноситель?', 'Производитель гарантирует сохранение свойств не менее 10 лет при соблюдении руководства по применению и отсутствии подсоса воздуха и посторонних жидкостей. Ежегодно проверяйте pH, цвет и прозрачность.'],
            ['Подходит ли для электрокотла?', 'Для ТЭНовых и индукционных котлов — да, если гликолевые теплоносители допускает производитель котла. Электродные (ионные) котлы требуют теплоносителя с особой электропроводностью — уточняйте совместимость у производителя котла перед покупкой.'],
            ['Какие документы я получу?', 'С каждой отгрузкой — '.self::DOCUMENTS.'. Для организаций предоставляем счёт и ТТН.'],
            ['Как быстро доставите?', 'Товар поставляется под заказ: отгрузка с производства до 5 рабочих дней после оплаты, затем доставка по Минску и всей Беларуси. При заказе от 500 кг согласуем специальную цену.'],
        ];
        $html .= '<section class="product-description-faq" aria-labelledby="zubr-faq-title"><header><p class="product-description-faq__eyebrow">Подбор и эксплуатация</p><h3 id="zubr-faq-title">Частые вопросы</h3><p class="product-description-faq__intro">Коротко о том, что важно знать перед покупкой теплоносителя.</p></header>';
        foreach ($faq as $i => [$q, $a]) {
            $html .= '<details name="zubr-faq"'.($i === 0 ? ' open' : '').'><summary>'.$e($q).'</summary><div class="product-description-faq__answer"><p>'.$e($a).'</p></div></details>';
        }
        $html .= "</section>\n";

        if ($kg === 10 && $key !== 'zubr-100') {
            $html .= '<p><small>На фото — канистра 20 кг; канистра 10 кг выпускается с аналогичной этикеткой.</small></p>'."\n";
        }
        if ($kg === 20 && $key === 'zubr-100') {
            $html .= '<p><small>На фото — канистра 10 кг; канистра 20 кг выпускается с аналогичной этикеткой.</small></p>'."\n";
        }

        return $html;
    }

    private function brandContent(): string
    {
        return <<<'HTML'
<div data-zubr-brand="2026-10-21">
<p><strong>ZUBR</strong> — белорусские теплоносители (антифризы) для систем отопления, вентиляции и охлаждения. Производитель — ООО «ЭкоСмартСервис», д. Станьково, Минская область. Продукция выпускается по ТУ BY 691618104.006-2026, соответствует ТР ТС 030/2012 и поставляется с паспортом качества, паспортом безопасности, декларацией о соответствии ЕАЭС и сертификатом продукции собственного производства БелТПП.</p>
<h2>Две линейки</h2>
<ul>
<li><strong>ZUBR</strong> (красная этикетка) — на основе этиленгликоля: <a href="/teplonositeli/teplonositel-zubr-30-etilenglikol-20-kg">ZUBR -30</a>, <a href="/teplonositeli/teplonositel-zubr-65-etilenglikol-20-kg">ZUBR -65</a> и <a href="/teplonositeli/teplonositel-zubr-100-etilenglikol-20-kg">суперконцентрат ZUBR 100</a>. Для закрытых систем отопления, котельных, тёплых полов и промышленного оборудования.</li>
<li><strong>ZUBR Эко</strong> (зелёная этикетка) — на основе пропиленгликоля: <a href="/teplonositeli/teplonositel-zubr-eko-30-propilenglikol-20-kg">ZUBR Эко -30</a>, <a href="/teplonositeli/teplonositel-zubr-eko-65-propilenglikol-20-kg">ZUBR Эко -65</a> и <a href="/teplonositeli/teplonositel-zubr-eko-100-propilenglikol-20-kg">суперконцентрат ZUBR Эко 100</a>. Нетоксичная основа для двухконтурных котлов, открытых систем и пищевых объектов.</li>
</ul>
<h2>Что в составе</h2>
<p>Кроме гликоля — карбоксилатные ингибиторы коррозии с биоцидом против бактерий и грибков, антинакипные и стабилизирующие присадки, пеногаситель и флуоресцентный краситель для поиска протечек. При соблюдении руководства по применению свойства сохраняются не менее 10 лет.</p>
<p>Фасовка: канистры 10 и 20 кг, под заказ — бочки 220 кг и еврокубы IBC 1000 кг.</p>
</div>
HTML;
    }

    private function litres(array $m, int $kg): float
    {
        return $kg / $m['density_f'];
    }

    private function minus(int $value): string
    {
        return $value < 0 ? '−'.abs($value) : (string) $value;
    }

    private function fmt(float $value, int $decimals): string
    {
        $formatted = number_format($value, $decimals, ',', ' ');

        return $decimals > 0 ? rtrim(rtrim($formatted, '0'), ',') : $formatted;
    }
}
