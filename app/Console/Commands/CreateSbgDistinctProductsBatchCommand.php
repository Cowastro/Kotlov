<?php

namespace App\Console\Commands;

use App\Models\IntegrationProduct;
use App\Models\Product;
use App\Services\Integrations\IntegrationProductCreator;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateSbgDistinctProductsBatchCommand extends Command
{
    protected $signature = 'integration:create-sbg-distinct-products
        {--batch=chimney-priced-20261012-a : Curated product-creation batch}
        {--code=* : Limit the run to one or more 1C codes}
        {--apply : Create and link the verified cards; default is a dry run}';

    protected $description = 'Create complete product cards for curated SanBusinessGroup variants that must not be merged.';

    private const SOURCE_NAME = '1С СанБизнесГруп';

    private const PARTNER_NAME = 'ООО «СанБизнесГруп»';

    /** @var array<string, array{template:string,candidate?:string,fragments:list<string>}> */
    private const BATCH_CHIMNEY_PRICED_20261012_A = [
        'УТ-00000947' => ['template' => 'KOTLOV-007993', 'fragments' => ['Труба L250', '430-0.8', 'D130']],
        'УТ-00000761' => ['template' => 'PS-009.107', 'fragments' => ['Проход перекрытия', '480х480', 'D220-260']],
        'УТ-00000319' => ['template' => 'PS-009.324', 'fragments' => ['KNSR 150/45', 'CZ2', '2сек']],
        'УТ-00000020' => ['template' => 'PS-009.330', 'fragments' => ['RP120/250', 'CZ2']],
        'УТ-00000019' => ['template' => 'PS-009.343', 'fragments' => ['RP120/1000', 'CZ2']],
        'УТ-00000007' => ['template' => 'PS-009.328', 'fragments' => ['KNSR 150/90', 'CZ2', '3 сек']],
        'УТ-00009583' => ['template' => 'PS-008.994', 'fragments' => ['Адаптер переход', '430-0.8', 'D130/140']],
        'УТ-00009578' => ['template' => 'PS-008.996', 'fragments' => ['Адаптер переход', '430-0.8', 'D160/200']],
        'УТ-00008176' => ['template' => 'PS-008.995', 'fragments' => ['Адаптер переход', '430-0.8', 'D150/180']],
        'УТ-00007477' => ['template' => 'PS-008.990', 'fragments' => ['Адаптер переход', '430-0.8', 'D100/120']],
        'УТ-00004093' => ['template' => 'PS-008.993', 'fragments' => ['Адаптер переход', '430-0.8', 'D120/150']],
        'УТ-00008868' => ['template' => 'PS-008.991', 'fragments' => ['Адаптер переход', '430-0.8', 'D110/115']],
    ];

    /** @var array<string, array{template:string,candidate?:string,fragments:list<string>}> */
    private const BATCH_CHIMNEY_PRICED_20261012_B = [
        'УТ-00001134' => ['template' => 'PS-009.741', 'fragments' => ['Шибер ШМ-Р', '430-0.8', 'D150']],
        'УТ-00000241' => ['template' => 'KOTLOV-007998', 'candidate' => 'PS-007.788', 'fragments' => ['Зонт ЗМ-Р', '430-0.5', 'D150']],
        'УТ-00000239' => ['template' => 'KOTLOV-007998', 'candidate' => 'PS-007.837', 'fragments' => ['Зонт ЗМ-Р', '430-0.5', 'D120']],
        'УТ-00008738' => ['template' => 'KOTLOV-007984', 'candidate' => 'PS-007.806', 'fragments' => ['Зонт ЗМ-Р', '430-0.5', 'D115']],
        'УТ-00006024' => ['template' => 'PS-009.741', 'candidate' => 'PS-007.954', 'fragments' => ['Шибер ШМ-Р', '430-0.8', 'D110']],
        'УТ-00003598' => ['template' => 'PS-009.741', 'candidate' => 'PS-007.863', 'fragments' => ['Шибер ШМ-Р', '430-0.8', 'D130']],
        'УТ-00000911' => ['template' => 'PS-009.085', 'candidate' => 'PS-007.858', 'fragments' => ['Тройник 87', '430-0.8', 'D130']],
        'УТ-00008924' => ['template' => 'PS-009.085', 'candidate' => 'PS-007.807', 'fragments' => ['Тройник 87', '430-0.8', 'D115']],
        'УТ-00008923' => ['template' => 'PS-009.691', 'candidate' => 'PS-007.807', 'fragments' => ['Тройник 45', '430-0.8', 'D115']],
    ];

    /** @var array<string, array{template:string,candidate?:string,fragments:list<string>}> */
    private const BATCH_CHIMNEY_PRICED_20261012_C = [
        'УТ-00009647' => ['template' => 'PS-008.996', 'candidate' => 'PS-009.519', 'fragments' => ['Адаптер переход', '430-0.8', 'D200/220']],
        'УТ-00008323' => ['template' => 'KOTLOV-007983', 'candidate' => 'PS-007.811', 'fragments' => ['Конденсатоотвод', '430-0.5', 'D115']],
        'УТ-00008732' => ['template' => 'PS-008.996', 'candidate' => 'PS-009.518', 'fragments' => ['Адаптер переход', '430-0.8', 'D180/200']],
        'УТ-00008832' => ['template' => 'PS-009.051', 'candidate' => 'PS-009.743', 'fragments' => ['Труба Термо L 250', '430-0.8/430', 'D115/200']],
        'УТ-00009413' => ['template' => 'PS-008.996', 'candidate' => 'PS-009.519', 'fragments' => ['Адаптер переход', '430-0.8', 'D200/250']],
    ];

    /** @var array<string, array{template:string,candidate?:string,fragments:list<string>}> */
    private const BATCH_CHIMNEY_PRICED_20261012_D = [
        'УТ-00002027' => ['template' => 'KOTLOV-022564', 'candidate' => 'PS-009.743', 'fragments' => ['Труба L250', '430-0.8', 'D115']],
        'УТ-00008915' => ['template' => 'KOTLOV-007994', 'candidate' => 'PS-003.424', 'fragments' => ['Отвод 87', '430-0.5', 'D115', '3S']],
    ];

    private const BATCH_RESEARCHED_PRICED_20261012_E = [
        'УТ-00000962' => [
            'template' => 'PS-009.331', 'fragments' => ['RP 120/500-CZ2', 'ML'],
            'name' => 'DARCO Труба дымоходная RP120/500-CZ2 (ML) Ø120, 2 мм',
            'images' => ['https://sklep.darco.pl/picture/fit-in/608x809/smart/filters:quality(75):fill(white)/ca2d77af23ce0eed739d5bb6a99e2b48'],
            'source_url' => 'https://sklep.darco.pl/ogrzewanie_kominkowe/rura_prosta_rp120500_cz2_mlsz_szara',
            'short' => 'Прямая дымоходная труба DARCO RP120/500-CZ2 (ML) из чёрной стали: Ø120 мм, длина 500 мм, стенка 2 мм.',
            'content' => '<p><strong>DARCO RP120/500-CZ2 (ML)</strong> — прямая труба системы подключения печей и каминов на твёрдом топливе. Изготовлена из чёрной стали и покрыта термостойкой краской Senotherm.</p><p>Диаметр 120 мм, длина 500 мм и толщина стенки 2 мм. Рабочая температура — до 600 °C. Цена сайта хранится отдельно от оптовой цены и остатка 1С.</p>',
            'specs' => [
                ['key' => 'Тип', 'value' => 'Прямая дымоходная труба'], ['key' => 'Диаметр', 'value' => '120 мм'],
                ['key' => 'Длина', 'value' => '500 мм'], ['key' => 'Толщина стали', 'value' => '2 мм'],
                ['key' => 'Материал', 'value' => 'Чёрная сталь'], ['key' => 'Покрытие', 'value' => 'Термостойкая краска Senotherm'],
                ['key' => 'Рабочая температура', 'value' => 'до 600 °C'], ['key' => 'Гарантия', 'value' => '24 месяца'],
            ],
        ],
        'УТ-00000616' => [
            'template' => 'PS-009.932', 'fragments' => ['Милана', '8мм'],
            'name' => 'Царь-Печи Милана, отопительная печь 8 мм',
            'images' => [
                'https://www.r-sauna.ru/assets/images/products/9802/milana.jpeg',
                'https://www.r-sauna.ru/assets/images/products/9802/milana-21.jpeg',
                'https://www.r-sauna.ru/assets/images/products/9802/milana-shema.jpeg',
            ],
            'source_url' => 'https://www.r-sauna.ru/catalog/otopit/otopitelnye-pechi-car-pechi/otopitelnaya-pech-milana-8mm/',
            'short' => 'Стальная отопительная печь Царь-Печи Милана с топкой 8 мм для помещений объёмом 35–45 м³.',
            'content' => '<p><strong>Царь-Печи Милана 8 мм</strong> — компактная дровяная отопительная печь с конвекционными экранами и варочной поверхностью. Рассчитана на быстрый прогрев помещений объёмом 35–45 м³.</p><p>Топка изготовлена из стали толщиной 8 мм; диаметр дымохода — 115 мм. Цена сайта рассчитана с сохранением минимальной валовой маржи относительно оптовой цены 1С.</p>',
            'specs' => [
                ['key' => 'Объём помещения', 'value' => '35–45 м³'], ['key' => 'Размеры (Ш×Г×В)', 'value' => '480×420×420 мм'],
                ['key' => 'Масса', 'value' => '35 кг'], ['key' => 'Диаметр дымохода', 'value' => '115 мм'],
                ['key' => 'Толщина топки', 'value' => '8 мм'], ['key' => 'Максимальная длина полена', 'value' => '40 см'],
                ['key' => 'Топливо', 'value' => 'Дрова'], ['key' => 'Страна производства', 'value' => 'Россия'],
            ],
        ],
        'УТ-00000613' => [
            'template' => 'PS-009.932', 'fragments' => ['Золовка', '8мм'],
            'name' => 'Царь-Печи Золовка, отопительная печь 8 мм',
            'images' => ['https://www.r-sauna.ru/assets/images/products/9796/zolovka.jpeg'],
            'source_url' => 'https://www.r-sauna.ru/catalog/otopit/otopitelnye-pechi-car-pechi/otopitelnaya-pech-zolovka-8mm/',
            'short' => 'Стальная отопительная печь Царь-Печи Золовка с топкой 8 мм для помещений объёмом 50–70 м³.',
            'content' => '<p><strong>Царь-Печи Золовка 8 мм</strong> — дровяная отопительная печь с конвекционными экранами и варочной поверхностью для помещений объёмом 50–70 м³.</p><p>Топка изготовлена из стали толщиной 8 мм; диаметр дымохода — 115 мм. Цена сайта рассчитана с сохранением минимальной валовой маржи относительно оптовой цены 1С.</p>',
            'specs' => [
                ['key' => 'Объём помещения', 'value' => '50–70 м³'], ['key' => 'Размеры (Ш×Г×В)', 'value' => '480×500×550 (675) мм'],
                ['key' => 'Масса', 'value' => '60 кг'], ['key' => 'Диаметр дымохода', 'value' => '115 мм'],
                ['key' => 'Толщина топки', 'value' => '8 мм'], ['key' => 'Максимальная длина полена', 'value' => '45 см'],
                ['key' => 'Топливо', 'value' => 'Дрова'], ['key' => 'Страна производства', 'value' => 'Россия'],
            ],
        ],
        'УТ-00000611' => [
            'template' => 'PS-009.932', 'fragments' => ['Злата', '8мм'],
            'name' => 'Царь-Печи Злата, отопительная печь 8 мм',
            'images' => [
                'https://www.r-sauna.ru/assets/images/products/9795/zlata.jpeg',
                'https://www.r-sauna.ru/assets/images/products/9795/zlata-31.jpeg',
                'https://www.r-sauna.ru/assets/images/products/9795/zlata-shema-2.jpeg',
            ],
            'source_url' => 'https://www.r-sauna.ru/catalog/otopit/otopitelnye-pechi-car-pechi/otopitelnaya-pech-zlata-8mm/',
            'short' => 'Стальная отопительная печь Царь-Печи Злата с топкой 8 мм для помещений объёмом 35–50 м³.',
            'content' => '<p><strong>Царь-Печи Злата 8 мм</strong> — дровяная отопительная печь с варочной поверхностью для дачи, дома, гаража или мастерской объёмом до 50 м³.</p><p>Топка изготовлена из стали толщиной 8 мм; диаметр дымохода — 115 мм. Цена сайта рассчитана с сохранением минимальной валовой маржи относительно оптовой цены 1С.</p>',
            'specs' => [
                ['key' => 'Объём помещения', 'value' => '35–50 м³'], ['key' => 'Размеры (Ш×Г×В)', 'value' => '560×410×480 мм'],
                ['key' => 'Масса', 'value' => '60 кг'], ['key' => 'Диаметр дымохода', 'value' => '115 мм'],
                ['key' => 'Толщина топки', 'value' => '8 мм'], ['key' => 'Максимальная длина полена', 'value' => '40 см'],
                ['key' => 'Топливо', 'value' => 'Дрова'], ['key' => 'Страна производства', 'value' => 'Россия'],
            ],
        ],
    ];

    public function handle(IntegrationProductCreator $creator): int
    {
        $rows = match ($this->option('batch')) {
            'chimney-priced-20261012-a' => self::BATCH_CHIMNEY_PRICED_20261012_A,
            'chimney-priced-20261012-b' => self::BATCH_CHIMNEY_PRICED_20261012_B,
            'chimney-priced-20261012-c' => self::BATCH_CHIMNEY_PRICED_20261012_C,
            'chimney-priced-20261012-d' => self::BATCH_CHIMNEY_PRICED_20261012_D,
            'researched-priced-20261012-e' => self::BATCH_RESEARCHED_PRICED_20261012_E,
            default => null,
        };

        if ($rows === null) {
            $this->error('Unknown batch.');

            return self::FAILURE;
        }

        $codes = array_values(array_filter(array_map('strval', (array) $this->option('code'))));
        if ($codes !== []) {
            $rows = array_intersect_key($rows, array_flip($codes));
        }

        $apply = (bool) $this->option('apply');
        $stats = array_fill_keys(['configured', 'ready', 'created', 'already', 'blocked'], 0);
        $stats['configured'] = count($rows);
        $details = [];

        foreach ($rows as $code => $rule) {
            $items = IntegrationProduct::query()->with(['source', 'product'])->where('external_code', $code)->get();
            $template = Product::query()->where('sku', $rule['template'])->first();
            $expectedCandidate = Product::query()->where('sku', $rule['candidate'] ?? $rule['template'])->first();

            if ($items->count() !== 1 || ! $template || ! $expectedCandidate) {
                $stats['blocked']++;
                $details[] = [$code, $rule['template'], 'blocked', 'missing unique 1C row or template'];

                continue;
            }

            $item = $items->first();
            $portableImages = $rule['images'] ?? $this->portableImages($template);
            if ($item->product_id) {
                if ($item->match_status === 'matched' && $item->match_method === 'manual_created') {
                    $stats['already']++;
                    $details[] = [$code, $item->product?->sku ?: '-', 'already', Str::limit($item->product?->name ?: '', 58)];
                } else {
                    $stats['blocked']++;
                    $details[] = [$code, $rule['template'], 'blocked', 'already linked by another method'];
                }

                continue;
            }

            $candidateIds = collect($item->candidates)->pluck('product_id')->map(fn ($id): int => (int) $id);
            $brand = trim((string) $template->brand?->name);
            $name = $rule['name'] ?? $this->productName($brand, $item->name);
            $valid = $item->source?->name === self::SOURCE_NAME
                && $item->source?->partnerName() === self::PARTNER_NAME
                && $item->match_status === 'ambiguous'
                && (float) $item->stock_quantity > 0
                && $item->normalizedPriceByn()
                && $template->category_id
                && $template->brand_id
                && $portableImages !== []
                && $candidateIds->contains((int) $expectedCandidate->id)
                && $this->containsAll($item->name, $rule['fragments'])
                && Product::query()->where('name', $name)->doesntExist();

            if (! $valid) {
                $stats['blocked']++;
                $details[] = [$code, $rule['template'], 'blocked', 'source, identity, price, category, brand, image or candidate guard failed'];

                continue;
            }

            $stats['ready']++;
            $retail = $this->retailPrice((float) $item->normalizedPriceByn(), (float) $template->price);

            if ($apply) {
                $product = $creator->createAndLink($item, [
                    'name' => $name,
                    'category_id' => $template->category_id,
                    'retail_price' => $retail,
                    'brand_id' => $template->brand_id,
                    'h1' => $name,
                    'short_description' => $rule['short'] ?? $this->shortDescription($name),
                    'content' => $rule['content'] ?? $this->content($name, (string) $item->external_sku),
                    'images' => $portableImages,
                    'specs' => $rule['specs'] ?? $this->specs($item),
                    'content_source_url' => $rule['source_url'] ?? null,
                    'meta_title' => Str::limit($name.' — купить в Беларуси | KOTLOV', 255, ''),
                    'meta_description' => Str::limit('Купить '.$name.' в KOTLOV. Характеристики, актуальная цена и наличие, доставка по Беларуси.', 255, ''),
                ]);

                if (! $product) {
                    $stats['blocked']++;
                    $details[] = [$code, $rule['template'], 'blocked', 'row changed during create'];

                    continue;
                }

                $stats['created']++;
                $details[] = [$code, $product->sku, 'created', number_format($retail, 2, '.', '').' BYN · '.$name];
            } else {
                $details[] = [$code, $rule['template'], 'ready', number_format($retail, 2, '.', '').' BYN · '.$name];
            }
        }

        $this->line($apply ? 'APPLY: verified product cards were created.' : 'DRY RUN: no database changes.');
        $this->table(['1C code', 'template/new SKU', 'status', 'detail'], $details);
        $this->table(['metric', 'count'], collect($stats)->map(fn ($count, $metric) => [$metric, $count])->values()->all());

        return $stats['blocked'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function retailPrice(float $wholesale, float $templateRetail): float
    {
        $withMargin = $wholesale / 0.70;

        return ceil(max($withMargin, $templateRetail) * 20) / 20;
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

    private function productName(string $brand, string $sourceName): string
    {
        $sourceName = trim($sourceName);

        return $brand !== '' && ! str_contains(mb_strtolower($sourceName), mb_strtolower($brand))
            ? $brand.' '.$sourceName
            : $sourceName;
    }

    private function shortDescription(string $name): string
    {
        return Str::limit($name.' — отдельная конфигурация элемента дымоходной системы. Размеры, материал и исполнение указаны в характеристиках; актуальное наличие подтверждается данными 1С.', 230, '');
    }

    private function content(string $name, string $article): string
    {
        $articleText = $article !== '' ? ' Артикул производителя: '.e($article).'.' : '';

        return '<p><strong>'.e($name).'</strong> — самостоятельная товарная конфигурация, которая отличается от близких моделей размером или исполнением.'.$articleText.'</p>'
            .'<p>Перед заказом сверяйте диаметр, длину, марку стали и толщину стенки в характеристиках. Цена и складской остаток поставщика учитываются отдельно от розничной цены сайта.</p>';
    }

    /** @return list<array{key:string,value:string}> */
    private function specs(IntegrationProduct $item): array
    {
        $name = (string) $item->name;
        $specs = [];

        if (preg_match('/\b(304|430)\s*[-\/]\s*(\d[\.,]\d)/u', $name, $match)) {
            $specs[] = ['key' => 'Марка стали', 'value' => 'AISI '.$match[1]];
            $specs[] = ['key' => 'Толщина стали', 'value' => str_replace(',', '.', $match[2]).' мм'];
        }
        if (preg_match('/\bD\s*(\d+(?:[\/-]\d+)?)/ui', $name, $match)) {
            $specs[] = ['key' => 'Диаметр', 'value' => str_replace('-', '/', $match[1]).' мм'];
        } elseif (preg_match('/\b(?:RP|KNSR?)\s*(\d+)\/(\d+)/ui', $name, $match)) {
            $specs[] = ['key' => 'Диаметр', 'value' => $match[1].' мм'];
        }
        if (preg_match('/\bL\s*(\d+)/ui', $name, $match)) {
            $specs[] = ['key' => 'Длина', 'value' => $match[1].' мм'];
        } elseif (preg_match('/\bRP\s*\d+\/(\d+)/ui', $name, $match)) {
            $specs[] = ['key' => 'Длина', 'value' => $match[1].' мм'];
        }
        if (preg_match('/\b(45|87|90)\s*\*/u', $name, $match)) {
            $specs[] = ['key' => 'Угол', 'value' => $match[1].'°'];
        } elseif (preg_match('/\bKNSR?\s*\d+\/(45|90)/ui', $name, $match)) {
            $specs[] = ['key' => 'Угол', 'value' => $match[1].'°'];
        }
        if (preg_match('/\b(\d)\s*(?:сек|S)\b/ui', $name, $match)) {
            $specs[] = ['key' => 'Количество секций', 'value' => $match[1]];
        }
        if ($item->external_sku) {
            $specs[] = ['key' => 'Артикул производителя', 'value' => (string) $item->external_sku];
        }

        return $specs;
    }

    /** @param list<string> $fragments */
    private function containsAll(string $value, array $fragments): bool
    {
        $value = mb_strtolower(str_replace([',', '–', '—', ' '], ['.', '-', '-', ''], $value));

        return collect($fragments)->every(function (string $fragment) use ($value): bool {
            $fragment = mb_strtolower(str_replace([',', '–', '—', ' '], ['.', '-', '-', ''], $fragment));

            return str_contains($value, $fragment);
        });
    }
}
