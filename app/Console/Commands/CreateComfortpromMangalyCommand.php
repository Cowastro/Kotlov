<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * One-off: create the 22 «Мангалы ComfortProm» products (kotlov.by carried
 * none before — brand ComfortProm previously had only печи банные, see
 * debug:brand-stats --name=ComfortProm, 68/68 matches were печи).
 *
 * Source: «ПРАЙС КомфортПром ОПТ печи, мангалы 10,08,2026.xlsx», sheet
 * «✔✔✔ Прайс печи 10,08,2026», rows 122-143 (section «Мангалы ComfortProm»).
 * Price = РРЦ с НДС column, taken directly (same convention as the печи
 * price sync). Photos were embedded as images inside the xlsx cells (no
 * URL/filename in the "фото" column) — extracted by cell anchor and seeded
 * into resources/seed-images/comfortprom-mangaly/ (storage/app/* is
 * gitignored wholesale, resources/ isn't) for this command to copy into
 * the public disk on --apply (proper Storage::disk('public') write, not a
 * manual file drop — keeps kotlov's normal image-serving path intact).
 *
 * 2 models have no photo in the price list at all (confirmed with user,
 * add without photo): «Мангал передвижной ComfortProm Асгард» (both
 * thickness variants) and «Мангал складной ComfortProm Пегас».
 * Thickness-variant pairs (2мм/3мм жаровня) reuse the same one photo the
 * price list provided (it only had one image per model, not per thickness),
 * except «Локи 3мм» which had its own distinct embedded image.
 * «Асгард с крышей» (стационарный, оба толщины) photo is a composite the
 * supplier's file shows for both the stationary and the передвижной-с-
 * крышей variant together — best available, kept as-is.
 *
 *   php artisan supplier:create-comfortprom-mangaly            # dry run
 *   php artisan supplier:create-comfortprom-mangaly --apply
 */
class CreateComfortpromMangalyCommand extends Command
{
    protected $signature = 'supplier:create-comfortprom-mangaly {--apply : Actually create the products + copy images}';

    protected $description = 'Create the 22 ComfortProm мангалы products (kotlov.by had none) from the 10.08.2026 price list';

    private const SEED_DIR = 'comfortprom-mangaly';

    /**
     * @var array<int, array{
     *   slug: string, name: string, price: float, weight: ?float,
     *   image: ?string, specs: array<int, array{key:string, value:string, unit:?string}>,
     *   short: string, content: string
     * }>
     */
    private const ITEMS = [
        [
            'slug' => 'mangal-statsionarnyy-comfortprom-veles-2mm',
            'name' => 'Мангал стационарный ComfortProm Велес + решетка барбекю (сталь 2 мм)',
            'price' => 199, 'weight' => 14, 'image' => 'row122.jpg',
            'specs' => [
                ['key' => 'Толщина металла жаровни', 'value' => '2', 'unit' => 'мм'],
                ['key' => 'Габариты (ДхШхВ)', 'value' => '1200х340х850', 'unit' => 'мм'],
                ['key' => 'Размер чаши (ДхШхВ)', 'value' => '600х300х170', 'unit' => 'мм'],
                ['key' => 'Покрытие', 'value' => 'Термостойкая краска', 'unit' => null],
                ['key' => 'В комплекте', 'value' => 'Решётка барбекю', 'unit' => null],
            ],
            'short' => 'Стационарный мангал ComfortProm Велес с решёткой барбекю, жаровня из стали 2 мм.',
            'content' => 'Стационарный мангал ComfortProm Велес — сварная конструкция на ножках с жаровней из стали толщиной 2 мм, покрытой термостойкой краской. Габариты 1200×340×850 мм, размер чаши 600×300×170 мм. В комплекте идёт решётка барбекю. Подходит для дачи и постоянной установки на участке.',
        ],
        [
            'slug' => 'mangal-statsionarnyy-comfortprom-veles-3mm',
            'name' => 'Мангал стационарный ComfortProm Велес + решетка барбекю (сталь 3 мм)',
            'price' => 252, 'weight' => null, 'image' => 'row122.jpg',
            'specs' => [
                ['key' => 'Толщина металла жаровни', 'value' => '3', 'unit' => 'мм'],
                ['key' => 'Габариты (ДхШхВ)', 'value' => '1200х340х850', 'unit' => 'мм'],
                ['key' => 'Размер чаши (ДхШхВ)', 'value' => '600х300х170', 'unit' => 'мм'],
                ['key' => 'Покрытие', 'value' => 'Термостойкая краска', 'unit' => null],
                ['key' => 'В комплекте', 'value' => 'Решётка барбекю', 'unit' => null],
            ],
            'short' => 'Стационарный мангал ComfortProm Велес с решёткой барбекю, усиленная жаровня из стали 3 мм.',
            'content' => 'Усиленная версия мангала ComfortProm Велес с жаровней из стали толщиной 3 мм для увеличенного срока службы. Габариты 1200×340×850 мм, размер чаши 600×300×170 мм, покрытие термостойкой краской. В комплекте решётка барбекю.',
        ],
        [
            'slug' => 'mangal-statsionarnyy-comfortprom-veles-s-kryshkoy-2mm',
            'name' => 'Мангал стационарный ComfortProm Велес с крышкой + решетка барбекю (сталь 2 мм)',
            'price' => 235, 'weight' => 18, 'image' => 'row124.jpg',
            'specs' => [
                ['key' => 'Толщина металла жаровни', 'value' => '2', 'unit' => 'мм'],
                ['key' => 'Габариты (ДхШхВ)', 'value' => '1220х360х940', 'unit' => 'мм'],
                ['key' => 'Толщина металла крышки', 'value' => '0.8', 'unit' => 'мм'],
                ['key' => 'Размер чаши (ДхШхВ)', 'value' => '600х300х170', 'unit' => 'мм'],
                ['key' => 'Покрытие', 'value' => 'Термостойкая краска', 'unit' => null],
                ['key' => 'В комплекте', 'value' => 'Решётка барбекю, крышка', 'unit' => null],
            ],
            'short' => 'Стационарный мангал ComfortProm Велес с защитной крышкой и решёткой барбекю, жаровня 2 мм.',
            'content' => 'Мангал ComfortProm Велес с откидной крышкой (0,8 мм металл) защищает жаровню от осадков между использованиями. Жаровня — сталь 2 мм, габариты 1220×360×940 мм, размер чаши 600×300×170 мм. Покрытие термостойкой краской, в комплекте решётка барбекю.',
        ],
        [
            'slug' => 'mangal-statsionarnyy-comfortprom-veles-s-kryshkoy-3mm',
            'name' => 'Мангал стационарный ComfortProm Велес с крышкой + решетка барбекю (сталь 3 мм)',
            'price' => 260, 'weight' => null, 'image' => 'row124.jpg',
            'specs' => [
                ['key' => 'Толщина металла жаровни', 'value' => '3', 'unit' => 'мм'],
                ['key' => 'Габариты (ДхШхВ)', 'value' => '1220х360х940', 'unit' => 'мм'],
                ['key' => 'Толщина металла крышки', 'value' => '0.8', 'unit' => 'мм'],
                ['key' => 'Размер чаши (ДхШхВ)', 'value' => '600х300х170', 'unit' => 'мм'],
                ['key' => 'Покрытие', 'value' => 'Термостойкая краска', 'unit' => null],
                ['key' => 'В комплекте', 'value' => 'Решётка барбекю, крышка', 'unit' => null],
            ],
            'short' => 'Стационарный мангал ComfortProm Велес с крышкой и решёткой барбекю, усиленная жаровня 3 мм.',
            'content' => 'Усиленная версия мангала ComfortProm Велес с крышкой: жаровня из стали 3 мм, крышка 0,8 мм. Габариты 1220×360×940 мм, размер чаши 600×300×170 мм. Покрытие термостойкой краской, в комплекте решётка барбекю.',
        ],
        [
            'slug' => 'mangal-statsionarnyy-asgard-2mm',
            'name' => 'Мангал стационарный ComfortProm Асгард + решетка барбекю (сталь 2 мм)',
            'price' => 219, 'weight' => 16, 'image' => 'row126.jpg',
            'specs' => [
                ['key' => 'Толщина металла жаровни', 'value' => '2', 'unit' => 'мм'],
                ['key' => 'Габариты (ДхШхВ)', 'value' => '1200х340х850', 'unit' => 'мм'],
                ['key' => 'Размер чаши (ДхШхВ)', 'value' => '600х300х170', 'unit' => 'мм'],
                ['key' => 'Покрытие', 'value' => 'Термостойкая краска', 'unit' => null],
                ['key' => 'В комплекте', 'value' => 'Решётка барбекю', 'unit' => null],
            ],
            'short' => 'Стационарный мангал ComfortProm Асгард с решёткой барбекю, жаровня из стали 2 мм.',
            'content' => 'Стационарный мангал ComfortProm Асгард — жаровня из стали толщиной 2 мм на прочном каркасе, покрытие термостойкой краской. Габариты 1200×340×850 мм, размер чаши 600×300×170 мм. В комплекте решётка барбекю.',
        ],
        [
            'slug' => 'mangal-statsionarnyy-asgard-3mm',
            'name' => 'Мангал стационарный ComfortProm Асгард + решетка барбекю (сталь 3 мм)',
            'price' => 272, 'weight' => null, 'image' => 'row126.jpg',
            'specs' => [
                ['key' => 'Толщина металла жаровни', 'value' => '3', 'unit' => 'мм'],
                ['key' => 'Габариты (ДхШхВ)', 'value' => '1200х340х850', 'unit' => 'мм'],
                ['key' => 'Размер чаши (ДхШхВ)', 'value' => '600х300х170', 'unit' => 'мм'],
                ['key' => 'Покрытие', 'value' => 'Термостойкая краска', 'unit' => null],
                ['key' => 'В комплекте', 'value' => 'Решётка барбекю', 'unit' => null],
            ],
            'short' => 'Стационарный мангал ComfortProm Асгард с решёткой барбекю, усиленная жаровня из стали 3 мм.',
            'content' => 'Усиленная версия мангала ComfortProm Асгард с жаровней из стали толщиной 3 мм. Габариты 1200×340×850 мм, размер чаши 600×300×170 мм, покрытие термостойкой краской. В комплекте решётка барбекю.',
        ],
        [
            'slug' => 'mangal-statsionarnyy-asgard-s-kryshey-2mm',
            'name' => 'Мангал стационарный ComfortProm Асгард с крышей + решетка барбекю (сталь 2 мм)',
            'price' => 339, 'weight' => 27, 'image' => 'row128.png',
            'specs' => [
                ['key' => 'Толщина металла жаровни', 'value' => '2', 'unit' => 'мм'],
                ['key' => 'Габариты (ДхШхВ)', 'value' => '1200х450х850/1550', 'unit' => 'мм'],
                ['key' => 'Размер чаши (ДхШхВ)', 'value' => '600х300х170', 'unit' => 'мм'],
                ['key' => 'Покрытие', 'value' => 'Термостойкая краска', 'unit' => null],
                ['key' => 'В комплекте', 'value' => 'Решётка барбекю, кованая крыша', 'unit' => null],
            ],
            'short' => 'Стационарный мангал ComfortProm Асгард с кованой крышей и решёткой барбекю, жаровня 2 мм.',
            'content' => 'Мангал ComfortProm Асгард с декоративной кованой крышей защищает от осадков и добавляет мангальной зоне законченный вид. Жаровня — сталь 2 мм, габариты 1200×450×850/1550 мм (высота с крышей), размер чаши 600×300×170 мм. Покрытие термостойкой краской, в комплекте решётка барбекю.',
        ],
        [
            'slug' => 'mangal-statsionarnyy-asgard-s-kryshey-3mm',
            'name' => 'Мангал стационарный ComfortProm Асгард с крышей + решетка барбекю (сталь 3 мм)',
            'price' => 392, 'weight' => null, 'image' => 'row128.png',
            'specs' => [
                ['key' => 'Толщина металла жаровни', 'value' => '3', 'unit' => 'мм'],
                ['key' => 'Габариты (ДхШхВ)', 'value' => '1200х450х850/1550', 'unit' => 'мм'],
                ['key' => 'Размер чаши (ДхШхВ)', 'value' => '600х300х170', 'unit' => 'мм'],
                ['key' => 'Покрытие', 'value' => 'Термостойкая краска', 'unit' => null],
                ['key' => 'В комплекте', 'value' => 'Решётка барбекю, кованая крыша', 'unit' => null],
            ],
            'short' => 'Стационарный мангал ComfortProm Асгард с кованой крышей и решёткой барбекю, усиленная жаровня 3 мм.',
            'content' => 'Усиленная версия мангала ComfortProm Асгард с крышей: жаровня из стали толщиной 3 мм. Габариты 1200×450×850/1550 мм, размер чаши 600×300×170 мм. Покрытие термостойкой краской, в комплекте решётка барбекю.',
        ],
        [
            'slug' => 'mangal-peredvizhnoy-asgard-2mm',
            'name' => 'Мангал передвижной ComfortProm Асгард + решетка барбекю (сталь 2 мм)',
            'price' => 289, 'weight' => 27, 'image' => null,
            'specs' => [
                ['key' => 'Толщина металла жаровни', 'value' => '2', 'unit' => 'мм'],
                ['key' => 'Габариты (ДхШхВ)', 'value' => '1200х450х850/1550', 'unit' => 'мм'],
                ['key' => 'Размер чаши (ДхШхВ)', 'value' => '600х300х170', 'unit' => 'мм'],
                ['key' => 'Покрытие', 'value' => 'Термостойкая краска', 'unit' => null],
                ['key' => 'Исполнение', 'value' => 'Передвижное (колёса)', 'unit' => null],
                ['key' => 'В комплекте', 'value' => 'Решётка барбекю', 'unit' => null],
            ],
            'short' => 'Передвижной мангал ComfortProm Асгард на колёсах с решёткой барбекю, жаровня 2 мм.',
            'content' => 'Передвижная версия мангала ComfortProm Асгард на колёсной базе — удобно перемещать по участку. Жаровня из стали толщиной 2 мм, габариты 1200×450×850/1550 мм, размер чаши 600×300×170 мм. Покрытие термостойкой краской, в комплекте решётка барбекю.',
        ],
        [
            'slug' => 'mangal-peredvizhnoy-asgard-3mm',
            'name' => 'Мангал передвижной ComfortProm Асгард + решетка барбекю (сталь 3 мм)',
            'price' => 342, 'weight' => null, 'image' => null,
            'specs' => [
                ['key' => 'Толщина металла жаровни', 'value' => '3', 'unit' => 'мм'],
                ['key' => 'Габариты (ДхШхВ)', 'value' => '1200х450х850/1550', 'unit' => 'мм'],
                ['key' => 'Размер чаши (ДхШхВ)', 'value' => '600х300х170', 'unit' => 'мм'],
                ['key' => 'Покрытие', 'value' => 'Термостойкая краска', 'unit' => null],
                ['key' => 'Исполнение', 'value' => 'Передвижное (колёса)', 'unit' => null],
                ['key' => 'В комплекте', 'value' => 'Решётка барбекю', 'unit' => null],
            ],
            'short' => 'Передвижной мангал ComfortProm Асгард на колёсах с решёткой барбекю, усиленная жаровня 3 мм.',
            'content' => 'Усиленная передвижная версия мангала ComfortProm Асгард с жаровней из стали 3 мм на колёсной базе. Габариты 1200×450×850/1550 мм, размер чаши 600×300×170 мм. Покрытие термостойкой краской, в комплекте решётка барбекю.',
        ],
        [
            'slug' => 'mangal-peredvizhnoy-asgard-s-kryshey-2mm',
            'name' => 'Мангал передвижной ComfortProm Асгард с крышей + решетка барбекю (сталь 2 мм)',
            'price' => 422, 'weight' => 29, 'image' => 'row132.png',
            'specs' => [
                ['key' => 'Толщина металла жаровни', 'value' => '2', 'unit' => 'мм'],
                ['key' => 'Габариты (ДхШхВ)', 'value' => '1200х450х850/1550', 'unit' => 'мм'],
                ['key' => 'Размер чаши (ДхШхВ)', 'value' => '600х300х170', 'unit' => 'мм'],
                ['key' => 'Покрытие', 'value' => 'Термостойкая краска', 'unit' => null],
                ['key' => 'Исполнение', 'value' => 'Передвижное (колёса), кованая крыша', 'unit' => null],
                ['key' => 'В комплекте', 'value' => 'Решётка барбекю', 'unit' => null],
            ],
            'short' => 'Передвижной мангал ComfortProm Асгард с кованой крышей на колёсах, жаровня 2 мм.',
            'content' => 'Передвижная версия мангала ComfortProm Асгард с декоративной кованой крышей и колёсной базой. Жаровня — сталь 2 мм, габариты 1200×450×850/1550 мм, размер чаши 600×300×170 мм. Покрытие термостойкой краской, в комплекте решётка барбекю.',
        ],
        [
            'slug' => 'mangal-peredvizhnoy-asgard-s-kryshey-3mm',
            'name' => 'Мангал передвижной ComfortProm Асгард с крышей + решетка барбекю (сталь 3 мм)',
            'price' => 475, 'weight' => null, 'image' => 'row132.png',
            'specs' => [
                ['key' => 'Толщина металла жаровни', 'value' => '3', 'unit' => 'мм'],
                ['key' => 'Габариты (ДхШхВ)', 'value' => '1200х450х850/1550', 'unit' => 'мм'],
                ['key' => 'Размер чаши (ДхШхВ)', 'value' => '600х300х170', 'unit' => 'мм'],
                ['key' => 'Покрытие', 'value' => 'Термостойкая краска', 'unit' => null],
                ['key' => 'Исполнение', 'value' => 'Передвижное (колёса), кованая крыша', 'unit' => null],
                ['key' => 'В комплекте', 'value' => 'Решётка барбекю', 'unit' => null],
            ],
            'short' => 'Передвижной мангал ComfortProm Асгард с кованой крышей на колёсах, усиленная жаровня 3 мм.',
            'content' => 'Усиленная передвижная версия мангала ComfortProm Асгард с крышей: жаровня из стали 3 мм на колёсной базе. Габариты 1200×450×850/1550 мм, размер чаши 600×300×170 мм. Покрытие термостойкой краской, в комплекте решётка барбекю.',
        ],
        [
            'slug' => 'mangal-statsionarnyy-comfortprom-loki-2mm',
            'name' => 'Мангал стационарный ComfortProm Локи + решетка барбекю (сталь 2 мм)',
            'price' => 451, 'weight' => 35, 'image' => 'row134.png',
            'specs' => [
                ['key' => 'Толщина металла жаровни', 'value' => '2', 'unit' => 'мм'],
                ['key' => 'Габариты (ДхШхВ)', 'value' => '1200х900х850/2100', 'unit' => 'мм'],
                ['key' => 'Размер чаши (ДхШхВ)', 'value' => '600х300х170', 'unit' => 'мм'],
                ['key' => 'Покрытие', 'value' => 'Термостойкая краска', 'unit' => null],
                ['key' => 'В комплекте', 'value' => 'Решётка барбекю', 'unit' => null],
            ],
            'short' => 'Крупный стационарный мангал-беседка ComfortProm Локи с решёткой барбекю, жаровня 2 мм.',
            'content' => 'Мангал ComfortProm Локи — самая крупная стационарная модель линейки, по сути мангал-беседка с высокой крышей (850/2100 мм). Жаровня из стали толщиной 2 мм, габариты 1200×900×850/2100 мм, размер чаши 600×300×170 мм. Покрытие термостойкой краской, в комплекте решётка барбекю.',
        ],
        [
            'slug' => 'mangal-statsionarnyy-comfortprom-loki-3mm',
            'name' => 'Мангал стационарный ComfortProm Локи + решетка барбекю (сталь 3 мм)',
            'price' => 524, 'weight' => null, 'image' => 'row135.png',
            'specs' => [
                ['key' => 'Толщина металла жаровни', 'value' => '3', 'unit' => 'мм'],
                ['key' => 'Габариты (ДхШхВ)', 'value' => '1200х900х850/2100', 'unit' => 'мм'],
                ['key' => 'Размер чаши (ДхШхВ)', 'value' => '600х300х170', 'unit' => 'мм'],
                ['key' => 'Покрытие', 'value' => 'Термостойкая краска', 'unit' => null],
                ['key' => 'В комплекте', 'value' => 'Решётка барбекю', 'unit' => null],
            ],
            'short' => 'Крупный стационарный мангал-беседка ComfortProm Локи с решёткой барбекю, усиленная жаровня 3 мм.',
            'content' => 'Усиленная версия мангала-беседки ComfortProm Локи с жаровней из стали толщиной 3 мм. Габариты 1200×900×850/2100 мм, размер чаши 600×300×170 мм. Покрытие термостойкой краской, в комплекте решётка барбекю.',
        ],
        [
            'slug' => 'mangal-skladnoy-comfortprom-pegas',
            'name' => 'Мангал складной ComfortProm Пегас',
            'price' => 135, 'weight' => 8.3, 'image' => null,
            'specs' => [
                ['key' => 'Толщина металла жаровни', 'value' => '2', 'unit' => 'мм'],
                ['key' => 'Габариты (ДхШхВ)', 'value' => '450х250х470', 'unit' => 'мм'],
                ['key' => 'Материал', 'value' => 'Конструкционная сталь', 'unit' => null],
                ['key' => 'Покрытие', 'value' => 'Термостойкая краска', 'unit' => null],
                ['key' => 'Исполнение', 'value' => 'Складное, переносное', 'unit' => null],
            ],
            'short' => 'Компактный складной мангал ComfortProm Пегас для выездов на природу, вес 8,3 кг.',
            'content' => 'Складной переносной мангал ComfortProm Пегас — компактная модель для выездов на природу и дачи без постоянной установки. Жаровня из конструкционной стали толщиной 2 мм с термостойким покрытием, вес всего 8,3 кг, габариты в разложенном виде 450×250×470 мм.',
        ],
        [
            'slug' => 'mangal-comfortprom-piknik',
            'name' => 'Мангал ComfortProm Пикник',
            'price' => 120, 'weight' => 8.35, 'image' => 'row137.png',
            'specs' => [
                ['key' => 'Толщина металла жаровни', 'value' => '2', 'unit' => 'мм'],
                ['key' => 'Габариты (ДхШхВ)', 'value' => '470х250х450', 'unit' => 'мм'],
                ['key' => 'Глубина жаровни', 'value' => '150', 'unit' => 'мм'],
                ['key' => 'Покрытие', 'value' => 'Без покрытия', 'unit' => null],
            ],
            'short' => 'Лёгкий переносной мангал ComfortProm Пикник для выездов на природу, вес 8,35 кг.',
            'content' => 'Мангал ComfortProm Пикник — простая и лёгкая модель без покрытия для частых выездов на природу. Жаровня из стали толщиной 2 мм, глубина 150 мм, габариты 470×250×450 мм, вес 8,35 кг.',
        ],
        [
            'slug' => 'mangal-super-razbornoy-skladnoy',
            'name' => 'Мангал ComfortProm Super, разборный, складной, с жаропрочным покрытием',
            'price' => 128, 'weight' => 12, 'image' => 'row138.png',
            'specs' => [
                ['key' => 'Толщина металла', 'value' => '3', 'unit' => 'мм'],
                ['key' => 'Габариты (ДхШхВ)', 'value' => '400х350х450', 'unit' => 'мм'],
                ['key' => 'Глубина жаровни', 'value' => '150', 'unit' => 'мм'],
                ['key' => 'Покрытие', 'value' => 'Термостойкая краска', 'unit' => null],
                ['key' => 'Исполнение', 'value' => 'Разборное, складное', 'unit' => null],
            ],
            'short' => 'Разборный складной мангал ComfortProm Super с жаропрочным покрытием, сталь 3 мм.',
            'content' => 'Мангал ComfortProm Super — разборная складная конструкция из стали толщиной 3 мм с термостойким покрытием, удобна для транспортировки и хранения. Габариты 400×350×450 мм, глубина жаровни 150 мм, вес 12 кг.',
        ],
        [
            'slug' => 'mangal-super-s-polkami-razbornoy-skladnoy',
            'name' => 'Мангал ComfortProm Super с полками, разборный, складной, с жаропрочным покрытием',
            'price' => 140, 'weight' => 13.8, 'image' => 'row139.png',
            'specs' => [
                ['key' => 'Толщина металла', 'value' => '3', 'unit' => 'мм'],
                ['key' => 'Габариты (ДхШхВ)', 'value' => '400х350х450', 'unit' => 'мм'],
                ['key' => 'Длина с полками', 'value' => '900', 'unit' => 'мм'],
                ['key' => 'Размер полок (ШхД)', 'value' => '240х250', 'unit' => 'мм'],
                ['key' => 'Глубина жаровни', 'value' => '150', 'unit' => 'мм'],
                ['key' => 'Покрытие', 'value' => 'Термостойкая краска', 'unit' => null],
                ['key' => 'Исполнение', 'value' => 'Разборное, складное', 'unit' => null],
            ],
            'short' => 'Разборный складной мангал ComfortProm Super с боковыми полками, сталь 3 мм.',
            'content' => 'Версия мангала ComfortProm Super с двумя откидными боковыми полками для подготовки и подачи — общая длина в разложенном виде 900 мм, размер каждой полки 240×250 мм. Жаровня из стали толщиной 3 мм, термостойкое покрытие, глубина жаровни 150 мм, вес 13,8 кг.',
        ],
        [
            'slug' => 'kostrovaya-chasha-comfortprom',
            'name' => 'Костровая чаша ComfortProm',
            'price' => 77, 'weight' => 6.2, 'image' => 'row140.png',
            'specs' => [
                ['key' => 'Толщина металла', 'value' => '2', 'unit' => 'мм'],
                ['key' => 'Габариты (ДхШхВ)', 'value' => '410х410х290', 'unit' => 'мм'],
                ['key' => 'Ширина у основания', 'value' => '250', 'unit' => 'мм'],
                ['key' => 'Покрытие', 'value' => 'Термостойкая краска', 'unit' => null],
            ],
            'short' => 'Костровая чаша ComfortProm для открытого огня на участке, сталь 2 мм.',
            'content' => 'Костровая чаша ComfortProm — компактная жаровня для открытого костра на дачном участке. Сталь толщиной 2 мм, термостойкое покрытие, габариты 410×410×290 мм, ширина у основания 250 мм, вес 6,2 кг.',
        ],
        [
            'slug' => 'pech-pohodnaya-schepochnitsa-comfortprom',
            'name' => 'Печь походная Щепочница ComfortProm',
            'price' => 85, 'weight' => 4, 'image' => 'row141.png',
            'specs' => [
                ['key' => 'Толщина металла', 'value' => '2', 'unit' => 'мм'],
                ['key' => 'Габариты (ДхШхВ)', 'value' => '230х230х230', 'unit' => 'мм'],
                ['key' => 'Покрытие', 'value' => 'Термостойкая краска', 'unit' => null],
                ['key' => 'Вид топлива', 'value' => 'Щепа, ветки', 'unit' => null],
            ],
            'short' => 'Компактная походная печь-щепочница ComfortProm для приготовления на щепе и ветках.',
            'content' => 'Походная печь-щепочница ComfortProm — компактная малогабаритная модель для приготовления пищи на щепе и мелких ветках в походе или на даче. Сталь толщиной 2 мм с термостойким покрытием, габариты 230×230×230 мм, вес всего 4 кг.',
        ],
        [
            'slug' => 'zharovnya-dlya-mangala-comfortprom-2mm',
            'name' => 'Жаровня для мангала ComfortProm (сталь 2 мм)',
            'price' => 129, 'weight' => 9, 'image' => 'row142.png',
            'specs' => [
                ['key' => 'Толщина металла', 'value' => '2', 'unit' => 'мм'],
                ['key' => 'Габариты (ДхШхВ)', 'value' => '600х300х170', 'unit' => 'мм'],
                ['key' => 'Покрытие', 'value' => 'Термостойкая краска', 'unit' => null],
                ['key' => 'Назначение', 'value' => 'Сменная жаровня / запчасть', 'unit' => null],
            ],
            'short' => 'Сменная жаровня ComfortProm для мангала, сталь 2 мм, отдельно от каркаса.',
            'content' => 'Отдельная сменная жаровня ComfortProm — замена прогоревшей чаши мангала без покупки всей конструкции. Сталь толщиной 2 мм с термостойким покрытием, габариты 600×300×170 мм, вес 9 кг. Подходит к мангалам линейки ComfortProm со стандартным размером чаши.',
        ],
        [
            'slug' => 'zharovnya-dlya-mangala-comfortprom-3mm',
            'name' => 'Жаровня для мангала ComfortProm (сталь 3 мм)',
            'price' => 170, 'weight' => 13.5, 'image' => 'row142.png',
            'specs' => [
                ['key' => 'Толщина металла', 'value' => '3', 'unit' => 'мм'],
                ['key' => 'Габариты (ДхШхВ)', 'value' => '600х300х170', 'unit' => 'мм'],
                ['key' => 'Покрытие', 'value' => 'Термостойкая краска', 'unit' => null],
                ['key' => 'Назначение', 'value' => 'Сменная жаровня / запчасть', 'unit' => null],
            ],
            'short' => 'Сменная жаровня ComfortProm для мангала, усиленная сталь 3 мм, отдельно от каркаса.',
            'content' => 'Усиленная сменная жаровня ComfortProm из стали толщиной 3 мм — увеличенный срок службы по сравнению со стандартной. Термостойкое покрытие, габариты 600×300×170 мм, вес 13,5 кг. Подходит к мангалам линейки ComfortProm со стандартным размером чаши.',
        ],
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $brand = Brand::where('name', 'ComfortProm')->first();
        if (! $brand) {
            $this->error('Brand "ComfortProm" not found.');
            return self::FAILURE;
        }

        $category = Category::where('name', 'Мангалы')->first();
        if (! $category) {
            $this->error('Category "Мангалы" not found.');
            return self::FAILURE;
        }

        $this->info(sprintf('brand_id=%d category_id=%d', $brand->id, $category->id));

        $created = 0;
        $skipped = 0;

        foreach (self::ITEMS as $item) {
            $exists = Product::where('slug', $item['slug'])->exists();
            if ($exists) {
                $this->line("= exists, skip: {$item['slug']}");
                $skipped++;
                continue;
            }

            $imagePath = null;
            if ($item['image']) {
                $seedFile = resource_path('seed-images/' . self::SEED_DIR . '/' . $item['image']);
                if (! file_exists($seedFile)) {
                    $this->warn("  seed image missing: {$seedFile}");
                } else {
                    $ext = pathinfo($item['image'], PATHINFO_EXTENSION);
                    $destName = 'products/' . $item['slug'] . '.' . $ext;
                    if ($apply) {
                        Storage::disk('public')->put($destName, file_get_contents($seedFile));
                    }
                    $imagePath = $destName;
                }
            }

            $this->line(sprintf(
                '+ %s | price=%s | image=%s',
                $item['name'],
                number_format($item['price'], 2),
                $imagePath ?? '(none)'
            ));

            $created++;

            if ($apply) {
                Product::create([
                    'name' => $item['name'],
                    'slug' => $item['slug'],
                    'sku' => null,
                    'category_id' => $category->id,
                    'brand_id' => $brand->id,
                    'price' => $item['price'],
                    'currency' => 'BYN',
                    'unit' => 'шт',
                    'weight' => $item['weight'],
                    'is_active' => true,
                    'in_stock' => true,
                    'availability_status' => Product::AVAILABILITY_IN_STOCK,
                    'is_archived' => false,
                    'short_description' => $item['short'],
                    'content' => $item['content'],
                    'images' => $imagePath ? [$imagePath] : [],
                    'specs' => $item['specs'],
                ]);
            }
        }

        $this->info(sprintf(
            '%s total=%d created=%d skipped_existing=%d',
            $apply ? 'APPLIED' : 'DRY-RUN',
            count(self::ITEMS),
            $created,
            $skipped
        ));

        return self::SUCCESS;
    }
}
