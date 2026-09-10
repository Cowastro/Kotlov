<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Product;

/**
 * Обогащение карточек Royal Thermo AQUATEC Standart (добавлены из Русклимат 10.09.2026).
 * Источник данных: rusklimat.ru / rkcdn.ru
 * Обновляем: images, short_description, specs
 */
return new class extends Migration
{
    private const COMMON_SPECS = [
        'Бренд'              => 'Royal Thermo',
        'Серия'              => 'AQUATEC STANDART',
        'Материал бака'      => 'Нержавеющая сталь AISI 304',
        'Материал теплообменника' => 'Нержавеющая сталь',
        'Материал корпуса'   => 'Сталь',
        'Защита от коррозии' => 'Электронный (титановый) анод',
        'Защита от накипи'   => 'Электронный (титановый) анод',
        'ТЭН'                => 'Установлен, 2 кВт',
        'Регулировка ТЭН'    => '30–75 °С (механический блок)',
        'Рециркуляция'       => 'Есть (1/2" наружная)',
        'Давление в бойлере' => '6 бар',
        'Давление в теплообменнике' => '10 бар',
        'Теплоизоляция'      => '25 мм',
        'Гарантия'           => '8 лет',
        'Страна производства'=> 'Россия',
    ];

    private const PRODUCTS = [
        [
            'sku'    => 'KOTLOV-008099', // Royal Thermo AQUATEC Standart SW100 White настенный
            'short'  => 'Настенный бойлер косвенного нагрева Royal Thermo AQUATEC Standart, 100 л, нержавеющая сталь, электронный анод, ТЭН 2 кВт, производительность 590 л/ч, гарантия 8 лет.',
            'images' => [
                'https://rkcdn.ru/products/aa11a1e1-d10d-11f0-b8e1-00505601218a/src.webp',
                'https://rkcdn.ru/products/aa11a1e3-d10d-11f0-b8e1-00505601218a/src.webp',
                'https://rkcdn.ru/products/aa11a1e5-d10d-11f0-b8e1-00505601218a/src.webp',
                'https://rkcdn.ru/products/aa11a1e7-d10d-11f0-b8e1-00505601218a/src.webp',
                'https://rkcdn.ru/products/aa11a1e9-d10d-11f0-b8e1-00505601218a/src.webp',
                'https://rkcdn.ru/products/b0137a10-d10d-11f0-b8e1-00505601218a/src.webp',
                'https://rkcdn.ru/products/b0137a12-d10d-11f0-b8e1-00505601218a/src.webp',
                'https://rkcdn.ru/products/b0137a14-d10d-11f0-b8e1-00505601218a/src.webp',
                'https://rkcdn.ru/products/b0137a16-d10d-11f0-b8e1-00505601218a/src.webp',
            ],
            'extra_specs' => [
                'Цвет'                => 'Белый',
                'Объём'               => '100 л',
                'Тип монтажа'         => 'Настенный',
                'Производительность'  => '590 л/час',
                'Мощность теплообменника' => '24 кВт',
                'Площадь теплообменника'  => '0.64 м²',
                'Габариты (Ш×В×Г)'    => '55.5 × 107.5 × 33.5 см',
                'Вес'                 => '24 кг',
            ],
        ],
        [
            'sku'    => 'KOTLOV-008098', // Royal Thermo AQUATEC Standart SW100 Grafit настенный
            'short'  => 'Настенный бойлер косвенного нагрева Royal Thermo AQUATEC Standart, 100 л, цвет графит, нержавеющая сталь, электронный анод, ТЭН 2 кВт, производительность 590 л/ч, гарантия 8 лет.',
            'images' => [
                'https://rkcdn.ru/products/a4149d91-d10d-11f0-b8e1-00505601218a/src.webp',
                'https://rkcdn.ru/products/a4149d93-d10d-11f0-b8e1-00505601218a/src.webp',
                'https://rkcdn.ru/products/a4149d95-d10d-11f0-b8e1-00505601218a/src.webp',
                'https://rkcdn.ru/products/aa11a1d5-d10d-11f0-b8e1-00505601218a/src.webp',
            ],
            'extra_specs' => [
                'Цвет'                => 'Графит',
                'Объём'               => '100 л',
                'Тип монтажа'         => 'Настенный',
                'Производительность'  => '590 л/час',
                'Мощность теплообменника' => '24 кВт',
                'Площадь теплообменника'  => '0.64 м²',
                'Габариты (Ш×В×Г)'    => '55.5 × 107.5 × 33.5 см',
                'Вес'                 => '24 кг',
            ],
        ],
        [
            'sku'    => 'KOTLOV-008097', // Royal Thermo AQUATEC Standart SW080 White настенный
            'short'  => 'Настенный бойлер косвенного нагрева Royal Thermo AQUATEC Standart, 80 л, нержавеющая сталь, электронный анод, ТЭН 2 кВт, производительность 491 л/ч, гарантия 8 лет.',
            'images' => [
                'https://rkcdn.ru/products/a4149d7f-d10d-11f0-b8e1-00505601218a/src.webp',
                'https://rkcdn.ru/products/a4149d81-d10d-11f0-b8e1-00505601218a/src.webp',
                'https://rkcdn.ru/products/a4149d83-d10d-11f0-b8e1-00505601218a/src.webp',
                'https://rkcdn.ru/products/a4149d85-d10d-11f0-b8e1-00505601218a/src.webp',
            ],
            'extra_specs' => [
                'Цвет'                => 'Белый',
                'Объём'               => '80 л',
                'Тип монтажа'         => 'Настенный',
                'Производительность'  => '491 л/час',
                'Мощность теплообменника' => '20 кВт',
                'Площадь теплообменника'  => '0.53 м²',
                'Габариты (Ш×В×Г)'    => '55.5 × 89 × 33.5 см',
                'Вес'                 => '20.2 кг',
            ],
        ],
        [
            'sku'    => 'KOTLOV-008096', // Royal Thermo AQUATEC Standart SF200 White напольный
            'short'  => 'Напольный бойлер косвенного нагрева Royal Thermo AQUATEC Standart, 200 л, нержавеющая сталь, электронный анод, ТЭН 2 кВт, производительность 700 л/ч, гарантия 8 лет.',
            'images' => [
                'https://rkcdn.ru/products/ea6d1ea9-d11c-11f0-b8e1-00505601218a/src.webp',
                'https://rkcdn.ru/products/ea6d1eab-d11c-11f0-b8e1-00505601218a/src.webp',
                'https://rkcdn.ru/products/ea6d1ead-d11c-11f0-b8e1-00505601218a/src.webp',
                'https://rkcdn.ru/products/ea6d1eaf-d11c-11f0-b8e1-00505601218a/src.webp',
            ],
            'extra_specs' => [
                'Цвет'                => 'Белый',
                'Объём'               => '200 л',
                'Тип монтажа'         => 'Напольный',
                'Производительность'  => '700 л/час',
                'Мощность теплообменника' => '35 кВт',
                'Площадь теплообменника'  => '0.64 м²',
                'Габариты (Ш×В×Г)'    => '52.6 × 126.5 × 57 см',
                'Вес'                 => '35 кг',
            ],
        ],
    ];

    public function up(): void
    {
        foreach (self::PRODUCTS as $p) {
            $product = Product::where('sku', $p['sku'])->first();

            if (! $product) {
                logger()->warning("Royal Thermo AQUATEC Standart: product not found for sku [{$p['sku']}]");
                continue;
            }

            $specs = array_merge(self::COMMON_SPECS, $p['extra_specs']);

            $product->update([
                'images'            => $p['images'],
                'short_description' => $p['short'],
                'specs'             => $specs,
            ]);
        }
    }

    public function down(): void {}
};
