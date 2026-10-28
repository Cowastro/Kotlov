<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/** Complete the 14 Master Flash cards created from the unmatched 1C queue. */
class FinishMasterFlashCardsCommand extends Command
{
    protected $signature = 'catalog:finish-master-flash-cards {--apply : Write the catalogue content}';

    protected $description = 'Complete Master Flash cards with descriptions, specs, SEO and verified brand data';

    /** @var array<string,array{series:string,diameter:string,colour:string,base:?string,brand:?string,source:?string}> */
    private const CARDS = [
        'KOTLOV-030290' => ['series' => 'RES №1', 'diameter' => '75–200 мм', 'colour' => 'красный', 'base' => null, 'brand' => null, 'source' => null],
        'KOTLOV-030291' => ['series' => 'RES №1', 'diameter' => '75–200 мм', 'colour' => 'чёрный', 'base' => null, 'brand' => null, 'source' => null],
        'KOTLOV-030292' => ['series' => 'RES №1', 'diameter' => '75–200 мм', 'colour' => 'синий', 'base' => null, 'brand' => null, 'source' => null],
        'KOTLOV-030293' => ['series' => 'RES №1', 'diameter' => '75–200 мм', 'colour' => 'серый', 'base' => null, 'brand' => null, 'source' => null],
        'KOTLOV-030294' => ['series' => 'RES №1', 'diameter' => '75–200 мм', 'colour' => 'коричневый', 'base' => null, 'brand' => null, 'source' => null],
        'KOTLOV-030295' => ['series' => 'RES №1', 'diameter' => '75–200 мм', 'colour' => 'зелёный', 'base' => null, 'brand' => null, 'source' => null],
        'KOTLOV-030296' => ['series' => 'RES №2', 'diameter' => '203–280 мм', 'colour' => 'чёрный', 'base' => '600×600 мм', 'brand' => null, 'source' => null],
        'KOTLOV-030297' => ['series' => 'RES №2', 'diameter' => '203–280 мм', 'colour' => 'серый', 'base' => '600×600 мм', 'brand' => null, 'source' => null],
        'KOTLOV-030298' => ['series' => 'RES №2', 'diameter' => '203–280 мм', 'colour' => 'красный', 'base' => '600×600 мм', 'brand' => null, 'source' => null],
        'KOTLOV-030299' => ['series' => 'RES №2', 'diameter' => '203–280 мм', 'colour' => 'коричневый', 'base' => '600×600 мм', 'brand' => null, 'source' => null],
        'KOTLOV-030300' => ['series' => 'RES №2', 'diameter' => '203–280 мм', 'colour' => 'зелёный', 'base' => '600×600 мм', 'brand' => null, 'source' => null],
        'KOTLOV-030301' => ['series' => '№6', 'diameter' => '200–280 мм', 'colour' => 'красный', 'base' => '600×600 мм', 'brand' => 'ASTON', 'source' => 'https://www.kaminyug.ru/catalog/master_flesh_uglovoy/master_flesh_aston_6_200_280mm_600kh600mm_ugl_silikon_krasnyy/'],
        'KOTLOV-030302' => ['series' => 'RES №2', 'diameter' => '203–280 мм', 'colour' => 'графитовый', 'base' => '600×600 мм', 'brand' => null, 'source' => 'https://www.kaminyug.ru/catalog/master_flesh_uglovoy/master_flesh_2_203_280mm_600mm_600mm_uglovoy_silikon_grafit_rr7024/'],
        'KOTLOV-030303' => ['series' => 'RES №2', 'diameter' => '203–280 мм', 'colour' => 'синий', 'base' => '600×600 мм', 'brand' => null, 'source' => 'https://pechki66.ru/product/res_2_203-280_sinij_silikon_no_name/'],
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $stats = ['expected' => count(self::CARDS), 'found' => 0, 'missing' => 0, 'updated' => 0, 'with_images' => 0, 'without_images' => 0];
        $astonBrand = null;

        foreach (self::CARDS as $sku => $card) {
            $product = Product::query()->where('sku', $sku)->first();
            if (! $product) {
                $stats['missing']++;
                $this->line("audit.master_flash.missing={$sku}");
                continue;
            }

            $stats['found']++;
            $images = array_values(array_filter((array) $product->images, fn ($image) => is_string($image) && trim($image) !== ''));
            $images === [] ? $stats['without_images']++ : $stats['with_images']++;

            $isAston = $card['brand'] === 'ASTON';
            $title = $isAston
                ? "Мастер-флеш ASTON №6 угловой {$card['diameter']}, {$card['base']}, силикон, {$card['colour']}"
                : "Мастер-флеш {$card['series']} угловой {$card['diameter']}, силикон, {$card['colour']}";
            $short = $isAston
                ? "Угловой кровельный уплотнитель ASTON №6 из силикона для дымоходных труб диаметром {$card['diameter']}. Основание {$card['base']}, цвет — {$card['colour']}."
                : "Угловой кровельный уплотнитель {$card['series']} из силикона для дымоходных труб диаметром {$card['diameter']}. Цвет — {$card['colour']}.";

            $specs = [
                ['key' => 'Тип изделия', 'value' => 'Кровельный уплотнитель (мастер-флеш)'],
                ['key' => 'Модель', 'value' => ($isAston ? 'ASTON ' : '').$card['series']],
                ['key' => 'Диаметр трубы', 'value' => $card['diameter']],
                ['key' => 'Материал', 'value' => 'Силикон'],
                ['key' => 'Цвет', 'value' => Str::ucfirst($card['colour'])],
                ['key' => 'Исполнение', 'value' => 'Угловое'],
                ['key' => 'Температура эксплуатации', 'value' => 'от −74 до +260 °C'],
                ['key' => 'Назначение', 'value' => 'Герметизация прохода дымохода через кровлю'],
                ['key' => 'Совместимые кровельные материалы', 'value' => 'Профнастил, металлочерепица, черепица, резина, пластик'],
            ];
            if ($card['base']) {
                array_splice($specs, 3, 0, [['key' => 'Размер основания', 'value' => $card['base']]]);
            }
            if ($card['series'] === 'RES №2') {
                $specs[] = ['key' => 'Рекомендуемый уклон кровли', 'value' => '30–55°'];
            }

            $content = '<p>'.$short.'</p>'
                .'<p>Эластичная манжета плотно охватывает трубу, а широкое основание повторяет профиль кровли. Уплотнитель применяют для герметизации кровельного прохода дымохода и защиты места примыкания от атмосферных осадков.</p>'
                .'<p>Перед монтажом верхушку манжеты обрезают по метке, соответствующей диаметру трубы. Основание формуют по кровельному профилю, герметизируют и закрепляют кровельными саморезами.</p>';

            $changes = [
                'name' => $title,
                'h1' => $title,
                'short_description' => $short,
                'content' => $content,
                'specs' => $specs,
                'unit' => 'шт.',
                'meta_title' => Str::limit($title, 66, '').' — купить в %city%',
                'meta_description' => Str::limit($short.' Цена, наличие и доставка по Беларуси.', 175, ''),
                'meta_keywords' => implode(', ', ['мастер-флеш', 'кровельный уплотнитель', $card['series'], $card['diameter'], $card['colour']]),
            ];
            if ($card['source']) {
                $changes['content_source_url'] = $card['source'];
            }
            if ($isAston) {
                if ($apply && ! $astonBrand) {
                    $astonBrand = Brand::query()->firstOrCreate(['slug' => 'aston'], ['name' => 'ASTON', 'h1' => 'ASTON', 'is_active' => true]);
                }
                if ($astonBrand) {
                    $changes['brand_id'] = $astonBrand->id;
                }
            }

            $this->line(sprintf('audit.master_flash.card=%d:%s:images_%d:%s', $product->id, $sku, count($images), $apply ? 'apply' : 'preview'));
            if ($apply) {
                $product->forceFill($changes)->save();
            }
            $stats['updated']++;
        }

        foreach ($stats as $key => $value) {
            $this->line("metric.master_flash.{$key}={$value}");
        }
        if (! $apply) {
            $this->warn('Предпросмотр: база не изменена. Для записи добавьте --apply.');
        }

        return $stats['missing'] === 0 && $stats['without_images'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
