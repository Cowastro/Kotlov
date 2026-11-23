<?php

namespace App\Support\Catalog;

use App\Models\Product;
use App\Services\PriceList\BelomoPriceList;

final class BelomoOfficialMeterSpecs
{
    public const SGD1_PAGE = 'https://belomo.by/product/bytovye-schetchiki-gaza/schetchiki-gaza-diafragmennye-sgd-1/';

    public const SGD1_CERT = 'https://belomo.by/upload/iblock/152/i00vc4esnwjxwo3ae6iw5wp4fcmlgasw.pdf';

    public const SGD3T_PAGE = 'https://belomo.by/product/bytovye-schetchiki-gaza/schetchiki-gaza-s-termokompensatorom-sgd-3t/';

    public const SGD3T_CERT = 'https://belomo.by/upload/iblock/bdc/g0nqdrp4zxz8vajf97fj6i7foovwhr6e.PDF';

    public const SGD3T_DECLARATION = 'https://belomo.by/upload/iblock/52e/y1bzxiohjp137k6vqpraqi1plnfiugup.PDF';

    /** Factory identities: SGD-3T certificate, appendix A (PDF page 8), checked 2026-11-23. */
    private const THERMAL = [
        '00' => [6, 200, 'left', false], '01' => [6, 200, 'right', false], '02' => [6, 200, 'left', false],
        '04' => [6, 200, 'left', true], '05' => [6, 200, 'right', true],
        '08' => [6, 250, 'left', false], '09' => [6, 250, 'right', false],
        '10' => [6, 250, 'left', false], '11' => [6, 250, 'right', false],
        '12' => [6, 250, 'left', true], '13' => [6, 250, 'right', true],
        '20' => [4, 200, 'left', false], '21' => [4, 200, 'right', false],
        '22' => [4, 200, 'left', true], '23' => [4, 200, 'right', true],
        '24' => [4, 250, 'left', false],
    ];

    public static function all(): array
    {
        $profiles = [];
        foreach (self::THERMAL as $suffix => [$size, $distance, $direction, $pulse]) {
            $suffix = str_pad((string) $suffix, 2, '0', STR_PAD_LEFT);
            $article = '8181.00.00.000-'.$suffix;
            $model = 'СГД-3Т-'.($distance === 250 ? '2' : '1').($pulse ? 'И' : '').'-'.($direction === 'left' ? '1' : '2').'-G'.$size;
            $profiles[$article] = self::profile($article, $model, 'G'.$size, $distance, $direction, true, $pulse, in_array($suffix, ['02', '08', '09'], true));
        }
        foreach (['20' => ['G1,6', 'right'], '21' => ['G1,6', 'left'], '22' => ['G2,5', 'right'], '23' => ['G2,5', 'left']] as $suffix => [$size, $direction]) {
            $article = '8072.00.00.000-'.$suffix;
            $profiles[$article] = self::profile($article, 'СГД-1-2-'.($direction === 'left' ? '2' : '1').'-'.$size, $size, 110, $direction, false, false, false);
        }
        foreach ((new BelomoPriceList)->rows() as $row) {
            if (! isset($profiles[$row['article']])) {
                $profiles[$row['article']] = self::otherProfile($row);
            }
        }
        foreach (EliconGasMeterCatalog::models() as $row) {
            if ($row['full_article'] === '8336.00.00.000-58') {
                $profiles[$row['full_article']] = self::otherProfile(['article' => $row['full_article'], 'name' => $row['name'],
                    'center_distance_mm' => 110, 'flow_direction' => 'левый']);
            }
        }

        return $profiles;
    }

    private static function profile(string $article, string $model, string $size, int $distance, string $direction, bool $thermal, bool $pulse, bool $withoutConnectors): array
    {
        $flow = GasMeterSelectionGuide::SIZES[$size];
        $left = $direction === 'left' ? 'левый' : 'правый';
        $name = 'Счётчик газа БелОМО '.$model.' ('.$left.'), межосевое расстояние '.$distance.' мм'.($withoutConnectors ? ', без присоединительных элементов' : '');
        $specs = [
            'Артикул производителя' => $article, 'Модель' => $model, 'Тип счётчика' => 'Диафрагменный',
            'Типоразмер' => $size, 'Направление потока газа' => $left,
            'Расстояние между осями патрубков' => $distance.' ± 0,5 мм',
            'Габариты корпуса (длина × ширина × высота)' => $thermal ? ($distance === 250 ? '310 × 185 × 245 мм' : '265 × 185 × 250 мм') : '160 × 135 × 230 мм',
            'Номинальный расход газа' => self::number($flow['nominal']).' м³/ч',
            'Минимальный расход газа' => self::number($flow['nominal'] / 100).' м³/ч',
            'Максимальный расход газа' => self::number($flow['max']).' м³/ч',
            'Максимальное рабочее давление' => $thermal ? '60 кПа' : '30 кПа',
            'Резьба присоединительных патрубков' => $thermal ? 'G 1¼' : 'G 1-B',
            'Термокомпенсация' => $thermal ? 'Да' : 'Нет', 'Импульсный выход' => $pulse ? 'Да' : 'Нет',
            'Масса, не более' => $thermal ? ($distance === 250 ? '4,3 кг' : '3,8 кг') : '1,8 кг',
            'Потеря давления при максимальном расходе, не более' => ($thermal && $size === 'G6' ? '250' : '200').' Па',
            'Температура эксплуатации' => $thermal ? 'От −40 до +50 °C' : 'От −10 до +50 °C или от −15 до +60 °C — по паспорту исполнения',
            'Межповерочный интервал' => $thermal ? '10 лет — по данным производителя; сверяйте паспорт прибора' : '10 лет для выпуска с 12.09.2024; 8 лет для выпуска с 26.03.2019 по 11.09.2024',
        ];
        if ($withoutConnectors) {
            $specs['Комплектация'] = 'Без присоединительных элементов';
        }
        $assets = BelomoOfficialAssets::series($thermal ? 'sgd3t' : 'sgd1');
        $documents = $assets['documents'];
        $content = self::copy($model, $article, $flow['max'], $distance, $left, $thermal ? 'Термокомпенсатор корректирует показания при изменении температуры газа.' : 'Диафрагменная конструкция предназначена для учёта газа в бытовых и производственных условиях.');

        return compact('article', 'model', 'name', 'size', 'distance', 'direction', 'thermal', 'specs', 'documents', 'content')
            + ['source' => $thermal ? self::SGD3T_PAGE : self::SGD1_PAGE,
                'images' => array_column($assets['images'], 'url'),
                'card' => ['Типоразмер' => $size, 'Межосевое расстояние' => $distance.' мм', 'Вход газа' => $left, 'Термокомпенсация' => $thermal ? 'Да' : 'Нет']];
    }

    private static function otherProfile(array $row): array
    {
        $article = $row['article'];
        $family = match (substr($article, 0, 4)) {
            '1009' => 'sgmn', '8336' => 'sgd4', '8345' => 'vega', '8348' => 'kata', '8349' => 'skat',
            default => throw new \RuntimeException('Неизвестная серия '.$article),
        };
        preg_match('/G\s*-?\s*(1[,.]6|2[,.]5|10|4|6)(?=$|[^\d,.])/ui', $row['name'], $m);
        $size = 'G'.str_replace('.', ',', $m[1]);
        $flow = BelomoSelectorLineup::sizes()[$size];
        $distance = $row['center_distance_mm'];
        $direction = match ($row['flow_direction']) {
            'левый' => 'left', 'правый' => 'right', default => null
        };
        $left = $row['flow_direction'];
        $thermal = $family === 'sgd4' && str_contains($row['name'], 'ТИ');
        $model = preg_replace('/^Сч[её]тчик(?:и)?\s+газа\s+(?:диафрагменный|ультразвуковой)?\s*/ui', '', $row['name']);
        $model = trim(preg_replace('/\s*\(?левый\)?|\s*\(?правый\)?|\s*L\s*=\s*\d+|\s*\(без манжет\)/ui', '', $model));
        $variant = collect(BelomoGasMeterDesignations::variants())->firstWhere('code', $article);
        if ($variant) {
            $model = trim(preg_replace('/^Счетчик газа ультразвуковой\s*/u', '', $variant['name']));
        }
        $name = 'Счётчик газа БелОМО '.$model.($left ? ' ('.$left.')' : '').($distance ? ', межосевое расстояние '.$distance.' мм' : '');
        $specs = ['Артикул производителя' => $article, 'Модель' => $model,
            'Тип счётчика' => in_array($family, ['vega', 'kata', 'skat'], true) ? 'Ультразвуковой' : 'Диафрагменный',
            'Типоразмер' => $size, 'Номинальный расход газа' => self::number($flow['nominal']).' м³/ч',
            'Минимальный расход газа' => self::number($flow['nominal'] / 100).' м³/ч',
            'Максимальный расход газа' => self::number($flow['max']).' м³/ч'];
        if ($distance) {
            $specs['Расстояние между осями патрубков'] = $distance.' ± 0,5 мм';
            $specs['Направление потока газа'] = $left;
        }
        if ($family === 'sgmn') {
            $specs += ['Габариты корпуса (длина × ширина × высота)' => $distance === 250 ? '310 × 165 × 225 мм' : '265 × 165 × 245 мм',
                'Максимальное рабочее давление' => '60 кПа', 'Температура эксплуатации' => 'От −40 до +60 °C',
                'Масса, не более' => $distance === 250 ? '3,8 кг' : '3,3 кг', 'Резьба присоединительных патрубков' => 'G 1¼'];
        } elseif ($family === 'sgd4') {
            // Certificate 16046, table 2: the web summary's 45±10 field is not used.
            $specs += ['Габариты корпуса (длина × ширина × высота)' => '210 × 175 × 245 мм',
                'Масса, не более' => '2,3 кг', 'Резьба присоединительных патрубков' => 'G 1¼',
                'Максимальное рабочее давление' => '35 кПа',
                'Термокомпенсация' => $thermal ? 'Да' : 'Нет'];
        } elseif ($family === 'vega') {
            $specs += ['Максимальное рабочее давление' => '5 кПа', 'Резьба присоединительных элементов' => 'G ½-B',
                'Габариты корпуса (длина × ширина × высота)' => '97 × 40 × 210 мм', 'Масса, не более' => '0,55 кг',
                'Температура эксплуатации' => 'От −10 до +50 °C'];
        } elseif ($family === 'kata') {
            $specs += ['Максимальное рабочее давление' => '5 кПа', 'Температура эксплуатации' => 'От −40 до +55 °C',
                'Габариты корпуса (длина × ширина × высота)' => '130 × 100 × 215 мм', 'Масса, не более' => '1,5 кг', 'Степень защиты' => 'IP54'];
            if ($variant) {
                $specs['Резьба присоединительных патрубков'] = BelomoGasMeterDesignations::THREADS[$variant['thread']];
            }
        } elseif ($family === 'skat') {
            $specs += ['Температура эксплуатации' => 'От −40 до +55 °C', 'Габариты корпуса (длина × ширина × высота)' => '135 × 100 × 220 мм',
                'Масса, не более' => '1,5 кг', 'Резьба присоединительных элементов' => 'G 1¼-B',
                'Максимальное рабочее давление' => '5 кПа', 'Степень защиты' => 'IP54'];
        }
        $specs['Межповерочный интервал'] = '10 лет — сверяйте дату выпуска и паспорт прибора';
        if ($variant && $variant['option']) {
            $specs['Дополнительные функции'] = BelomoGasMeterDesignations::OPTIONS[$variant['option']];
        }
        $assets = BelomoOfficialAssets::series($family);
        $images = array_column($assets['images'], 'url');
        if ($family === 'sgd4') {
            $images = [$thermal ? '/img/products/belomo-official/sgd4-2.png'
                : ($size === 'G2,5' ? '/img/products/belomo-official/sgd4-g25.png' : '/img/products/belomo-official/sgd4-58.png')];
        } elseif ($family === 'vega') {
            $images = [str_contains($row['name'], 'R') ? '/img/products/belomo-official/vega-2.png' : '/img/products/belomo-official/vega-1.png'];
        }
        $feature = match ($family) {
            'sgmn' => 'Диафрагменная серия для учёта газа в жилых домах и на коммунально-бытовых объектах.',
            'sgd4' => $thermal ? 'Исполнение с термокомпенсацией. Подбирается по типоразмеру и направлению входа газа.' : 'Диафрагменное исполнение для бытового учёта газа.',
            'vega' => 'Ультразвуковое измерение газа в компактном корпусе. При замене важно проверить присоединительную резьбу и исполнение.',
            'kata' => 'Ультразвуковая серия с несколькими вариантами присоединительной резьбы и дополнительных функций.',
            'skat' => 'Ультразвуковая серия для учёта газа при повышенном расходе. Подключение и дополнительные функции зависят от артикула.',
        };
        if ($variant && $variant['option']) {
            $feature .= ' В этом исполнении — '.BelomoGasMeterDesignations::OPTIONS[$variant['option']].'.';
        }

        return compact('article', 'model', 'name', 'size', 'distance', 'direction', 'thermal', 'specs')
            + ['source' => $assets['source_url'], 'documents' => $assets['documents'], 'images' => $images,
                'content' => self::copy($model, $article, $flow['max'], $distance, $left, $feature),
                'card' => array_filter(['Типоразмер' => $size, 'Максимальный расход' => self::number($flow['max']).' м³/ч',
                    'Межосевое расстояние' => $distance ? $distance.' мм' : null, 'Вход газа' => $left,
                    'Резьба' => $specs['Резьба присоединительных патрубков'] ?? $specs['Резьба присоединительных элементов'] ?? null])];
    }

    private static function copy(string $model, string $article, float $max, ?int $distance, ?string $direction, string $feature): string
    {
        $model = htmlspecialchars($model, ENT_QUOTES, 'UTF-8');
        $mount = $distance ? 'Расстояние между осями патрубков — <strong>'.$distance.' мм</strong>. Это монтажный параметр; габариты корпуса приведены отдельно.' : 'Сверьте присоединительную резьбу и расположение патрубков с проектом.';
        if ($direction) {
            $mount .= ' Вход газа — <strong>'.$direction.'</strong>, если смотреть на лицевую сторону счётчика; направление обозначено стрелкой на корпусе.';
        }

        return '<h2>БелОМО '.$model.' — учёт газа для дома и квартиры</h2><p>Заводской артикул <strong>'.$article.'</strong>. Максимальный расход газа — <strong>'.self::number($max).' м³/ч</strong>. '.$feature.'</p>'
            .'<h3>Подключение и размеры</h3><p>'.$mount.'</p><h3>Как выбрать замену</h3><p>Сложите паспортный расход газовых приборов и сверьте допустимый диапазон счётчика. Для замены нужны заводской артикул, направление потока, резьба и комплектация. Окончательное исполнение сверяют с проектом; монтаж и пломбирование выполняет уполномоченный специалист.</p>'
            .'<p>Поможем подобрать счётчик БелОМО и уточнить наличие и доставку по Беларуси. Заводские документы можно открыть во вкладке «Документы» на этой странице. Фотографии показывают внешний вид серии; комплектацию выбранного исполнения сверяйте по артикулу.</p>'
            .'<p><a href="/baza-znaniy/kak-podobrat-schetchik-gaza">Подобрать счётчик по расходу газа</a> · <a href="/schetchiki-gaza">Все счётчики газа в каталоге KOTLOV</a></p>';
    }

    private static function number(float $value): string
    {
        return str_replace('.', ',', (string) $value);
    }

    public static function forProduct(Product $product): ?array
    {
        $matches = array_filter(self::all(), fn ($p) => BelomoSelectorLineup::matchesProduct($product, ['article_compact' => (new BelomoPriceList)->compactArticle($p['article'])]));

        return count($matches) === 1 ? reset($matches) : null;
    }

    public static function selectorRows(array $rows): array
    {
        $profiles = self::all();
        foreach ($rows as &$row) {
            if ($p = $profiles[$row['article']] ?? null) {
                $row = array_replace($row, ['name' => $p['name'], 'center_distance_mm' => $p['distance'],
                    'flow_direction' => match ($p['direction']) {
                        'left' => 'левый', 'right' => 'правый', default => null
                    }]);
            }
        }
        unset($row);
        if (! in_array('8181.00.00.000-24', array_column($rows, 'article'), true)) {
            $p = $profiles['8181.00.00.000-24'];
            $rows[] = ['article' => $p['article'], 'name' => $p['name'], 'center_distance_mm' => $p['distance'], 'flow_direction' => 'левый'];
        }

        return $rows;
    }
}
