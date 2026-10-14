<?php

namespace App\Services\PriceList;

class SvahGroupCatalogContent
{
    /** @return array<string, mixed>|null */
    public function profile(array $row): ?array
    {
        $signature = (string) ($row['signature'] ?? '');

        if (preg_match('/^tatra:boiler:kt(15|30|40|50|70|95)(?:-(n|np1|nl1|np2|nl2))?$/', $signature, $match)) {
            return $this->boiler((int) $match[1], $match[2] ?? null);
        }

        if (preg_match('/^(tatra|svah|hot):stove:t(10|16|20|24|30|32|36)(t)?(?::tank(50|80|100))?$/', $signature, $match)) {
            return $this->stove(
                $match[1],
                (int) $match[2],
                ($match[3] ?? '') === 't',
                isset($match[4]) && $match[4] !== '' ? (int) $match[4] : null,
            );
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function boiler(int $power, ?string $variant): array
    {
        $data = [
            15 => ['area' => 'до 120 м²', 'steel' => '6 мм', 'burn' => 'от 12 часов'],
            30 => ['area' => '130–250 м²', 'steel' => '6 мм', 'burn' => 'от 12 часов'],
            40 => ['area' => 'до 400 м²', 'steel' => '6 мм', 'burn' => 'от 8 часов'],
            50 => ['area' => 'до 500 м²', 'steel' => $variant === null ? '4 мм' : '6 мм', 'burn' => 'от 8 часов'],
            70 => ['area' => 'до 700 м²', 'steel' => '6 мм', 'burn' => 'от 6 часов'],
            95 => ['area' => '600–1100 м²', 'steel' => $variant === null ? '5 мм' : '6 мм', 'burn' => 'от 6 часов'],
        ][$power];
        $side = str_contains((string) $variant, 'p') ? 'правое' : (str_contains((string) $variant, 'l') ? 'левое' : 'универсальное');
        $circuits = str_ends_with((string) $variant, '2') ? 2 : 1;
        $suffix = $variant ? '-'.mb_strtoupper($variant) : '';
        $name = "Tatra КТ{$power}{$suffix} Твердотопливный котёл {$data['steel']}";
        $purpose = $circuits === 2 ? 'отопления и подготовки горячей воды' : 'системы отопления';

        return [
            'name' => $name,
            'short_description' => "Шахтный твердотопливный котёл Tatra КТ{$power}{$suffix} мощностью {$power} кВт для {$purpose}; топка из стали {$data['steel']}, {$side} подключение.",
            'content' => "<p><strong>Tatra КТ{$power}{$suffix}</strong> — стальной котёл длительного горения мощностью {$power} кВт для {$purpose}. Рекомендуемая площадь объекта — {$data['area']} при корректном теплотехническом расчёте и нормативном утеплении.</p><p>Шахтная топка с нижней зоной горения и дожигом пиролизных газов рассчитана на дрова и древесные отходы. Водоохлаждаемые стенки и колосники отводят тепло в систему отопления, а продолжительность работы на одной загрузке составляет {$data['burn']} и зависит от топлива, тяги и нагрузки.</p><p>Исполнение: {$side} подключение, ".($circuits === 2 ? 'два контура — отопление и ГВС' : 'один отопительный контур').". Перед монтажом необходимо проверить расчётную мощность, сечение и высоту дымохода, схему защиты обратной линии и группу безопасности.</p>",
            'images' => null,
            'source_url' => 'https://kotlov.by/brands/tetra',
            'specs' => [
                ['key' => 'Тип', 'value' => 'Твердотопливный котёл длительного горения'],
                ['key' => 'Модель', 'value' => "Tatra КТ{$power}{$suffix}"],
                ['key' => 'Номинальная мощность', 'value' => "{$power} кВт"],
                ['key' => 'Рекомендуемая площадь отопления', 'value' => $data['area']],
                ['key' => 'Количество контуров', 'value' => (string) $circuits],
                ['key' => 'Назначение', 'value' => $circuits === 2 ? 'Отопление и горячее водоснабжение' : 'Отопление'],
                ['key' => 'Сторона подключения', 'value' => mb_convert_case($side, MB_CASE_TITLE)],
                ['key' => 'Принцип горения', 'value' => 'Шахтный с дожигом пиролизных газов'],
                ['key' => 'Топливо', 'value' => 'Дрова, древесные отходы'],
                ['key' => 'Материал топки', 'value' => 'Конструкционная котловая сталь'],
                ['key' => 'Толщина стали топки', 'value' => $data['steel']],
                ['key' => 'Колосники', 'value' => 'Водоохлаждаемые'],
                ['key' => 'Продолжительность горения', 'value' => $data['burn']],
                ['key' => 'Тяга', 'value' => 'Естественная'],
                ['key' => 'Установка', 'value' => 'Напольная'],
                ['key' => 'Теплоноситель', 'value' => 'Вода'],
                ['key' => 'Гарантия', 'value' => '36 месяцев'],
                ['key' => 'Бренд', 'value' => 'Tatra'],
                ['key' => 'Страна производства', 'value' => 'Беларусь'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function stove(string $brand, int $model, bool $turbo, ?int $tank): array
    {
        $models = [
            'tatra' => [
                10 => ['room' => 'до 13 м³', 'weight' => '115 кг', 'stones' => '70–90 кг', 'size' => '650×450×950 мм', 'chimney' => '115 мм', 'warranty' => '36 месяцев'],
                16 => ['room' => 'до 24 м³', 'weight' => '140 кг', 'stones' => 'до 120 кг', 'size' => '730×505×1100 мм', 'chimney' => '150 мм', 'warranty' => '36 месяцев'],
                24 => ['room' => 'до 24 м³', 'weight' => '160 кг', 'stones' => 'до 120 кг', 'size' => '730×505×1100 мм', 'chimney' => '115 мм', 'warranty' => '36 месяцев'],
                30 => ['room' => 'до 30 м³', 'weight' => '170 кг', 'stones' => 'до 150 кг', 'size' => '830×505×1100 мм', 'chimney' => '115 мм', 'warranty' => '36 месяцев'],
                32 => $turbo
                    ? ['room' => 'до 32 м³', 'weight' => '180 кг', 'stones' => '140–160 кг', 'size' => '700×510×1180 мм', 'chimney' => '130 мм', 'warranty' => '36 месяцев']
                    : ['room' => 'до 32 м³', 'weight' => '180 кг', 'stones' => 'до 120 кг', 'size' => '700×500×1500 мм', 'chimney' => '130 мм', 'warranty' => '36 месяцев'],
                36 => ['room' => 'до 45 м³', 'weight' => '220 кг', 'stones' => 'до 160 кг', 'size' => '730×510×1300 мм', 'chimney' => '150 мм', 'warranty' => '12 месяцев'],
            ],
            'svah' => [
                16 => ['room' => 'до 16 м³', 'weight' => '80 кг', 'stones' => '90–110 кг', 'size' => '510×650×650 мм', 'chimney' => '115 мм', 'warranty' => '24 месяца'],
                20 => ['room' => 'до 20 м³', 'weight' => '100 кг', 'stones' => '90–120 кг', 'size' => '770×500×700 мм', 'chimney' => '115 мм', 'warranty' => '24 месяца'],
                24 => ['room' => 'до 24 м³', 'weight' => '120 кг', 'stones' => '110–130 кг', 'size' => '510×650×850 мм', 'chimney' => '115 мм', 'warranty' => '24 месяца'],
            ],
            'hot' => [
                16 => ['room' => 'до 16 м³', 'weight' => null, 'stones' => 'до 100 кг', 'size' => null, 'chimney' => '115 мм', 'warranty' => '12 месяцев'],
                20 => ['room' => 'до 20 м³', 'weight' => null, 'stones' => 'до 120 кг', 'size' => null, 'chimney' => '115 мм', 'warranty' => '12 месяцев'],
                24 => ['room' => 'до 24 м³', 'weight' => null, 'stones' => 'до 130 кг', 'size' => null, 'chimney' => '115 мм', 'warranty' => '12 месяцев'],
            ],
        ];
        $data = $models[$brand][$model];
        $brandName = ['tatra' => 'Tatra', 'svah' => 'SVAH', 'hot' => 'HOT'][$brand];
        $modelName = 'T'.$model.($turbo ? 'T' : '');
        $tankText = $tank ? " с навесным баком {$tank} л" : '';
        $name = "{$brandName} {$modelName} Банная печь{$tankText}";
        $steel = $brand === 'tatra' ? '6–8 мм' : ($brand === 'svah' ? '8 мм' : 'сталь');
        $source = $brand === 'tatra' && $model === 16 && $tank === 80
            ? 'https://www.snabcity.by/product/pech-dlya-bani-tatra16-v-komplekte-s-bakom/'
            : match ($brand) {
            'tatra' => 'https://lepel.kotelok.by/catalog/bannye-pechi/bannye-pechi-na-drovakh/tatra/',
            'svah' => "https://lepel.kotelok.by/catalog/bannye-pechi/pech-bannaya-svah-t{$model}-stal-8mm".($model === 16 ? '3165' : ($model === 20 ? '14404' : '9159')).'/',
            default => 'https://kotlov.by/brands/tetra',
        };
        $images = $brand === 'tatra' && $model === 16 && $tank === 80
            ? ['img/products/tatra/tatra-t16-water-tank-80l-clean.png']
            : null;

        $specs = array_values(array_filter([
            ['key' => 'Тип', 'value' => 'Дровяная банная печь'],
            ['key' => 'Модель', 'value' => "{$brandName} {$modelName}"],
            ['key' => 'Объём парной', 'value' => $data['room']],
            ['key' => 'Тип каменки', 'value' => 'Открытая'],
            ['key' => 'Масса камней', 'value' => $data['stones']],
            ['key' => 'Масса печи', 'value' => $data['weight']],
            ['key' => 'Габариты (Д×Ш×В)', 'value' => $data['size']],
            ['key' => 'Диаметр дымохода', 'value' => $data['chimney']],
            ['key' => 'Подключение дымохода', 'value' => 'Верхнее'],
            ['key' => 'Материал топки', 'value' => 'Сталь'],
            ['key' => 'Толщина стали', 'value' => $steel],
            ['key' => 'Топливо', 'value' => 'Дрова'],
            ['key' => 'Тип установки', 'value' => 'Напольная'],
            ['key' => 'Топочный тоннель', 'value' => 'Выносной'],
            ['key' => 'Дверца', 'value' => 'Глухая'],
            ['key' => 'Гарантия', 'value' => $data['warranty']],
            ['key' => 'Бренд', 'value' => $brandName],
            ['key' => 'Страна производства', 'value' => 'Беларусь'],
        ], fn (array $spec): bool => $spec['value'] !== null));
        if ($tank) {
            $specs[] = ['key' => 'Объём бака', 'value' => "{$tank} л"];
            $specs[] = ['key' => 'Тип бака', 'value' => 'Боковой навесной'];
            $specs[] = ['key' => 'Материал бака', 'value' => 'Нержавеющая сталь'];
        }
        if ($brand === 'tatra' && $model === 16 && $tank === 80) {
            $specs[] = ['key' => 'Объём каменки', 'value' => '81 л'];
            $specs[] = ['key' => 'Теплообменник каменки', 'value' => 'Четырёхходовой'];
            $specs[] = ['key' => 'Глубина топки', 'value' => '500 мм'];
            $specs[] = ['key' => 'Максимальная длина полена', 'value' => '50 см'];
            $specs[] = ['key' => 'Толщина стали бака', 'value' => '1 мм'];
        }

        return [
            'name' => $name,
            'short_description' => "Дровяная банная печь {$brandName} {$modelName} для парной {$data['room']} с открытой каменкой {$data['stones']}".($tank ? " и навесным баком {$tank} л" : '').'.',
            'content' => "<p><strong>{$brandName} {$modelName}</strong> — дровяная банная печь для парных объёмом {$data['room']}. Открытая каменка рассчитана на загрузку {$data['stones']} камней и обеспечивает накопление тепла для парения.".($brand === 'tatra' && $model === 16 ? ' Камни прогревает четырёхходовой теплообменник с увеличенной площадью контакта с нагретыми газами.' : '')."</p><p>Стальная топка, верхнее подключение дымохода {$data['chimney']} и выносной топочный тоннель позволяют загружать дрова из смежного помещения.".($data['size'] !== null ? " Габариты печи — {$data['size']}, масса — {$data['weight']}." : '')."</p><p>Печь рассчитана на дровяное топливо и напольную установку на негорючее основание. Монтаж, противопожарные отступы и дымоход должны выполняться по паспорту изделия.".($tank ? " Комплектация включает боковой навесной бак объёмом {$tank} л." : '').'</p>',
            'images' => $images,
            'source_url' => $source,
            'specs' => $specs,
        ];
    }
}
