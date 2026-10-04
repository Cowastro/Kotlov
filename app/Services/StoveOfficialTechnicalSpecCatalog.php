<?php

namespace App\Services;

class StoveOfficialTechnicalSpecCatalog
{
    /**
     * Technical characteristics copied from manufacturer pages and branded
     * manufacturer catalogues. Heated area is deliberately absent unless it
     * is explicitly published by the manufacturer.
     *
     * @return array<string, array<string, mixed>>
     */
    public function entries(): array
    {
        $models = [
            'atene-g' => $this->model('ATENE G', 'atene-g', '495×440×965', '340×340×340', '7–9', '79'),
            'berna-lux-s' => $this->model('BERNA LUX S', 'berna-lux-s', '330×340×940', '250×255×300', '5–7', '31'),
            'berna-lux' => $this->model('BERNA LUX', 'berna-lux', '390×450×870', '290×250×360', '5–7', '48'),
            'blist-b1' => $this->model('BLIST B1', 'blist-b1', '460×380×770', '390×270×350', '7–9', '39'),
            'modena' => $this->model('MODENA', 'modena', '480×460×930', '320×320×280', '7–9', '64'),
            'padova' => $this->model('PADOVA', 'padova', '510×490×910', '440×390×360', '9–11', '61'),
            'roma-e' => $this->model('ROMA E', 'roma-e', '485×470×1070', '300×330×450', '20–22', '90', [
                $this->spec('Мощность водяного контура', '15–17', 'кВт'),
                $this->spec('Объём водяного контура', '22', 'л'),
            ]),
            'roma-s' => $this->model('ROMA S', 'roma-s', '485×440×1200', '340×340×340', '11–13', '97', [
                $this->spec('Размеры духовки (Ш×Г×В)', '320×280×280', 'мм'),
            ]),
            'roma-g' => $this->model('ROMA G', 'roma-g', '495×440×1190', '340×340×340', '11–13', '108', [
                $this->spec('Размеры духовки (Ш×Г×В)', '320×280×280', 'мм'),
            ]),
        ];

        return [
            'blist-pec-atene-g-seraia' => $models['atene-g'],
            'blist-pec-berna-lux-s' => $models['berna-lux-s'],
            'blist-pec-berna-lux-bezevaia' => $models['berna-lux'],
            'blist-pec-berna-lux-krasnaia' => $models['berna-lux'],
            'blist-pec-berna-lux-seraia' => $models['berna-lux'],
            'blist-pec-blist-b1' => $models['blist-b1'],
            'blist-pec-modena-bezevaia' => $models['modena'],
            'blist-pec-modena-krasnaia' => $models['modena'],
            'blist-pec-modena-seraia' => $models['modena'],
            'blist-pec-padova-e' => $models['padova'],
            'blist-pec-roma-e-bezevaia' => $models['roma-e'],
            'blist-pec-roma-s-bezevaia' => $models['roma-s'],
            'blist-pec-roma-g-bezevaia' => $models['roma-g'],
            'ferguss-pec-ferguss-l-8606107095288-lawa-cook-ucenka' => [
                'specs' => [
                    $this->spec('Габариты (Ш×Г×В)', '535×445×926', 'мм'),
                    $this->spec('Размеры топки (Ш×Г×В)', '410×310×270', 'мм'),
                    $this->spec('Мощность', '12,8', 'кВт'),
                    $this->spec('Масса', '168', 'кг'),
                    $this->spec('КПД', '74', '%'),
                    $this->spec('Диаметр дымохода', '120', 'мм'),
                    $this->spec('Максимальная длина полена', '250', 'мм'),
                ],
                'source_url' => 'https://www.ikoma.hr/Content/product/document/ferguss-katalog-2018.pdf',
                'source_label' => 'Ferguss — каталог 2018, EAN 8606107095288',
                'product_name' => 'Печь Ferguss L Ornament Cook (8606107095288) (УЦЕНКА)',
                'h1' => 'Печь Ferguss L Ornament Cook с варочной плитой',
                'meta_title' => 'Печь Ferguss L Ornament Cook — купить в Минске | KOTLOV',
                'meta_description' => 'Чугунная печь Ferguss L Ornament Cook с варочной плитой: 12,8 кВт, КПД 74%, дымоход 120 мм. Характеристики, цена и доставка по Беларуси.',
                'short_description' => 'Чугунная отопительно-варочная печь Ferguss L Ornament Cook с декоративным литьём и дымовой заслонкой. Мощность 12,8 кВт, КПД 74%, масса 168 кг, дымоход 120 мм.',
                'content' => '<p><strong>Ferguss L Ornament Cook</strong> — чугунная отопительно-варочная печь с декоративным литьём, варочной поверхностью и дымовой заслонкой. Модель точно идентифицирована по коду EAN 8606107095288 в фирменном каталоге Ferguss.</p><p>Производитель указывает мощность 12,8 кВт, КПД 74%, массу 168 кг и габариты 535 × 445 × 926 мм. Размер топочной камеры составляет 410 × 310 × 270 мм, диаметр дымохода — 120 мм, максимальная длина полена — 250 мм.</p><p>Площадь отопления производителем для этой модели не заявлена. Печь следует подбирать с учётом теплопотерь здания, высоты помещений, утепления и режима эксплуатации.</p>',
            ],
            'pech-kamin-fireway-konnecta' => [
                'specs' => [
                    $this->spec('Габариты (Ш×Г×В)', '590×473×882', 'мм'),
                    $this->spec('Мощность', '10', 'кВт'),
                    $this->spec('Материал корпуса', 'Чугун', ''),
                    $this->spec('Диаметр дымохода', '150', 'мм'),
                    $this->spec('Подключение дымохода', 'Верхнее и заднее', ''),
                    $this->spec('Варочная поверхность', 'Есть', ''),
                    $this->spec('Гарантия', '60', 'мес.'),
                ],
                'source_url' => 'https://fireway.pro/pech-chugunnaya-konnekta.html',
                'source_label' => 'Fireway — печь-камин Konnekta',
            ],
            'otopitelno-varochnaya-pech-fireway-skif' => [
                'specs' => [
                    $this->spec('Габариты (Ш×Г×В)', '950×600×863', 'мм'),
                    $this->spec('Мощность', '8', 'кВт'),
                    $this->spec('Материал корпуса', 'Чугун', ''),
                    $this->spec('Диаметр дымохода', '150', 'мм'),
                    $this->spec('Подключение дымохода', 'Верхнее и заднее', ''),
                    $this->spec('Варочная поверхность', 'Есть', ''),
                    $this->spec('Духовой шкаф', 'Есть', ''),
                    $this->spec('Гарантия', '60', 'мес.'),
                ],
                'source_url' => 'https://fireway.pro/pech-kamin-skif.html',
                'source_label' => 'Fireway — отопительно-варочная печь Skif',
            ],
            'otopitelnaya-pech-ecokamin-ogonek' => [
                'specs' => [
                    $this->spec('Габариты (Ш×Г×В)', '300×477×421', 'мм'),
                    $this->spec('Материал корпуса', 'Сталь', ''),
                    $this->spec('Диаметр дымохода', '120', 'мм'),
                    $this->spec('Подключение дымохода', 'Верхнее', ''),
                    $this->spec('Масса', '23', 'кг'),
                    $this->spec('Вид топлива', 'Дрова', ''),
                ],
                'source_url' => 'https://www.ecokamin.ru/catalog/otopitelnye_pechi/13581/',
                'source_label' => 'ЭкоКамин — отопительная печь ОГОНЕК, PO 001',
            ],
            'kamin-panorama-tri-stekla-grafit' => $this->ecokaminFireplace(
                'ПАНОРАМА ТРИ СТЕКЛА графит',
                'https://ecokamin.ru/catalog/kaminy/kaminy_tri_stekla/16115/',
                '729×539×2643',
                '12',
                '150',
                '157',
                '80',
                'Сталь',
            ),
            'kamin-panorama-tri-stekla-chernyiy' => $this->ecokaminFireplace(
                'ПАНОРАМА ТРИ СТЕКЛА чёрный',
                'https://ecokamin.ru/catalog/kaminy/kaminy_tri_stekla/16025/',
                '729×539×2643',
                '12',
                '150',
                '157',
                '80',
                'Сталь',
            ),
            'kamin-praga-tri-stekla-new-chernyiy' => $this->ecokaminFireplace(
                'ПРАГА три стекла NEW, чёрный',
                'https://ecokamin.ru/catalog/kaminy/praga/16933/',
                '836×554×2820',
                '14',
                '200',
                '223',
                '78',
                'Сталь',
            ),
            'kamin-praga-tri-stekla-new-cernyi-samot-cernyi' => $this->ecokaminFireplace(
                'ПРАГА три стекла NEW, чёрный шамот',
                'https://ecokamin.ru/catalog/kaminy/praga/18615/',
                '836×554×2820',
                '14',
                '200',
                '223',
                '78',
                'Сталь',
            ),
            'kamin-madrid-na-drovnike-podovyi' => $this->ecokaminFireplace(
                'МАДРИД на дровнике, подовый',
                'https://ecokamin.ru/catalog/kaminy/kaminy_madrid/17564/',
                '768×526×2529',
                '8',
                '200',
                '187',
                '80',
            ),
            'kamin-madrid-na-drovnike-gigant-centralnyi-cernyi-samot-podovyi' => $this->ecokaminFireplace(
                'МАДРИД на дровнике Гигант центральный, чёрный шамот',
                'https://ecokamin.ru/catalog/kaminy/kaminy_madrid/17570/',
                '1300×580×2529',
                '8',
                '200',
                '205',
                '80',
            ),
            'kamin-madrid-na-drovnike-gigant-sleva-cernyi-samot-podovyi' => $this->ecokaminFireplace(
                'МАДРИД на дровнике Гигант слева, чёрный шамот',
                'https://ecokamin.ru/catalog/kaminy/kaminy_madrid/17565/',
                '1300×580×2529',
                '8',
                '200',
                '205',
                '80',
            ),
        ];
    }

    /**
     * @param  array<int, array{key: string, value: string, unit: string}>  $extraSpecs
     * @return array{specs: array<int, array{key: string, value: string, unit: string}>, source_url: string, source_label: string}
     */
    private function model(
        string $label,
        string $path,
        string $dimensions,
        string $fireboxDimensions,
        string $power,
        string $weight,
        array $extraSpecs = [],
    ): array {
        return [
            'specs' => [
                $this->spec('Габариты (Ш×Г×В)', $dimensions, 'мм'),
                $this->spec('Размеры топки (Ш×Г×В)', $fireboxDimensions, 'мм'),
                $this->spec('Мощность', $power, 'кВт'),
                $this->spec('Масса', $weight, 'кг'),
                ...$extraSpecs,
            ],
            'source_url' => "https://blist.co.rs/proizvodi/peci-i-kamini-na-cvrsto-gorivo/peci-i-kamini/{$path}/",
            'source_label' => "Blist — {$label}",
        ];
    }

    /**
     * @return array{specs: array<int, array{key: string, value: string, unit: string}>, source_url: string, source_label: string}
     */
    private function ecokaminFireplace(
        string $label,
        string $sourceUrl,
        string $dimensions,
        string $power,
        string $chimneyDiameter,
        string $weight,
        string $efficiency,
        ?string $material = null,
    ): array {
        return [
            'specs' => [
                $this->spec('Габариты (Ш×Г×В)', $dimensions, 'мм'),
                $this->spec('Мощность', $power, 'кВт'),
                ...($material !== null ? [$this->spec('Материал корпуса', $material, '')] : []),
                $this->spec('Диаметр дымохода', $chimneyDiameter, 'мм'),
                $this->spec('Подключение дымохода', 'Верхнее и заднее', ''),
                $this->spec('Масса', $weight, 'кг'),
                $this->spec('КПД', $efficiency, '%'),
                $this->spec('Гарантия', '2', 'года'),
                $this->spec('Вид топлива', 'Дрова', ''),
            ],
            'source_url' => $sourceUrl,
            'source_label' => "ЭкоКамин — {$label}",
        ];
    }

    /** @return array{key: string, value: string, unit: string} */
    private function spec(string $key, string $value, string $unit): array
    {
        return compact('key', 'value', 'unit');
    }
}
