<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;

/**
 * Data migration: 63 новые банные печи Fireway из прайса Каминпрофессионал 07.2026.
 * Идемпотентна — пропускает уже существующие slug-и.
 * Категория: Дровяные печи (69), Бренд: Fireway (299), Поставщик: kaminproff.
 */
return new class extends Migration
{
    private const BRAND_ID      = 299;
    private const CAT_ID        = 69;   // Дровяные печи
    private const SUPPLIER_CODE = 'kaminproff';

    // Изображения с fireway.pro
    private const I18_WITH     = 'https://fireway.pro/assets/media/products/211/0-0.jpg';
    private const I18_K505     = 'https://fireway.pro/assets/media/360/212/medium/1ded85bdbe88469dde310f3d78dab2e72e9d8ffd.jpg';
    private const I18_BEZ      = 'https://fireway.pro/assets/media/products/219/1-0.jpg';
    private const I24_WITH     = 'https://fireway.pro/assets/media/products/215/ontop1.jpg';
    private const I24_K505     = 'https://fireway.pro/assets/media/products/216/ontop1-1.jpg';
    private const I24_K404     = 'https://fireway.pro/assets/media/products/244/ontop1-1.jpg';
    private const IALMA        = 'https://fireway.pro/assets/media/products/218/ontop4.jpg';
    private const IKOLCHUGA    = 'https://pechking.ru/wp-content/uploads/2022/08/propar-kolchuga-18-201-2-600x600-1.jpg';

    public function up(): void
    {
        $supplier = Supplier::where('code', self::SUPPLIER_CODE)->first();

        foreach ($this->products() as $p) {
            $product = Product::where('slug', $p['slug'])->first();

            if (!$product) {
                $product = Product::create([
                    'name'                => $p['name'],
                    'slug'                => $p['slug'],
                    'category_id'         => self::CAT_ID,
                    'brand_id'            => self::BRAND_ID,
                    'price'               => $p['rrc'],
                    'currency'            => 'BYN',
                    'images'              => [$p['img']],
                    'short_description'   => $p['short'],
                    'content'             => $this->content($p),
                    'specs'               => $this->specs($p),
                    'availability_status' => 'check',
                    'is_active'           => true,
                    'is_archived'         => false,
                    'in_stock'            => false,
                    'is_new'              => true,
                ]);
            }

            if ($supplier && !SupplierProduct::where('supplier_id', $supplier->id)
                    ->where('product_id', $product->id)->exists()) {
                SupplierProduct::create([
                    'supplier_id'                 => $supplier->id,
                    'product_id'                  => $product->id,
                    'supplier_article'            => 'fireway-' . $p['slug'],
                    'supplier_article_normalized' => 'fireway-' . $p['slug'],
                    'supplier_article_compact'    => 'fireway-' . $p['slug'],
                    'supplier_name'               => $p['name'],
                    'price'                       => $p['opt'],
                    'price_byn'                   => $p['opt'],
                    'currency'                    => 'BYN',
                    'currency_rate'               => 1.0,
                    'in_stock'                    => false,
                    'stock_status'                => 'preorder',
                    'match_status'                => 'matched',
                    'match_confidence'            => 100,
                    'last_synced_at'              => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $slugs = array_column($this->products(), 'slug');
        $ids   = Product::whereIn('slug', $slugs)->where('brand_id', self::BRAND_ID)->pluck('id');

        $supplier = Supplier::where('code', self::SUPPLIER_CODE)->first();
        if ($supplier) {
            SupplierProduct::where('supplier_id', $supplier->id)->whereIn('product_id', $ids)->delete();
        }
        Product::whereIn('id', $ids)->delete();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function content(array $p): string
    {
        return match($p['series']) {
            'pr18_kovka' => $this->desc18Kovka($p),
            'pr24_kovka' => $this->desc24Kovka($p),
            'pr18_prut'  => $this->desc18Prut($p),
            'pr24_prut'  => $this->desc24Prut($p),
            'alma'       => $this->descAlma($p),
            'kolchuga'   => $this->descKolchuga(),
            'iskra'      => $this->descIskra(),
            'faraon'     => $this->descFaraon(),
            'setka'      => $this->descSetka($p),
            default      => '',
        };
    }

    private function specs(array $p): array
    {
        $base = [
            'Бренд'       => 'Fireway',
            'Производство'=> 'Россия',
            'Гарантия'    => '36 месяцев',
            'Материал'    => 'Чугун СЧ20',
        ];

        return match($p['series']) {
            'pr18_kovka', 'pr18_prut' => array_merge($base, [
                'Объём парной'       => 'до 18 м³',
                'Масса камней'       => '120 кг',
                'Вес печи'           => $p['code'] === 'k505' ? '104 кг' : '85 кг',
                'Габариты (Ш×В×Г)'  => '600 × 660 × 550 мм',
                'Дымоход'            => 'Ø 115 мм',
                'Вынос топки'        => empty($p['bez_vynosa']) ? 'Есть' : 'Нет',
                'Стекло'             => 'Панорамное жаропрочное',
                'Тип каменки'        => $p['series'] === 'pr18_kovka' ? 'Открытая, ковка' : 'Открытая, прут',
            ]),
            'pr24_kovka', 'pr24_prut' => array_merge($base, [
                'Объём парной'       => 'до 24 м³',
                'Масса камней'       => '180 кг',
                'Вес печи'           => '104 кг',
                'Габариты (Ш×В×Г)'  => '680 × 660 × 650 мм',
                'Дымоход'            => 'Ø 115 мм',
                'Вынос топки'        => empty($p['bez_vynosa']) ? 'Есть' : 'Нет',
                'Стекло'             => 'Панорамное жаропрочное',
                'Тип каменки'        => $p['series'] === 'pr24_kovka' ? 'Открытая, ковка' : 'Открытая, прут',
            ]),
            'alma' => array_merge($base, [
                'Объём парной'       => 'до 20 м³',
                'Масса камней'       => '60 кг (открытая)',
                'Вес печи'           => '142 кг',
                'Габариты (Ш×В×Г)'  => '620 × 900 × 620 мм',
                'Дымоход'            => 'Ø 140 мм',
                'Вынос топки'        => 'Есть',
                'Покрытие'           => 'Эмаль антрацит',
            ]),
            'kolchuga' => array_merge($base, [
                'Серия'              => 'Pro-Пар',
                'Объём парной'       => 'до 18 м³',
                'Масса камней'       => '100 кг',
                'Вес печи'           => '86 кг',
                'Габариты (Ш×В×Г)'  => '540 × 710 × 540 мм',
                'Дымоход'            => 'Ø 115 мм',
                'Вынос топки'        => 'Есть',
                'Стекло'             => 'Панорамное жаропрочное',
            ]),
            'iskra' => array_merge($base, [
                'Серия'              => 'Pro-Пар',
                'Объём парной'       => 'до 16 м³',
                'Вынос топки'        => 'Есть',
                'Стекло'             => 'Жаропрочное',
            ]),
            'faraon' => array_merge($base, [
                'Серия'              => 'Новинки 2026',
                'Объём парной'       => 'до 24 м³',
                'Вынос топки'        => 'Есть',
                'Стекло'             => 'Панорамное жаропрочное',
            ]),
            'setka' => [
                'Бренд'             => 'Fireway',
                'Производство'      => 'Россия',
                'Тип'               => str_contains($p['slug'], 'kovka') ? 'Ковка' : 'Кольчуга',
            ],
            default => $base,
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Descriptions
    // ─────────────────────────────────────────────────────────────────────────

    private function desc18Kovka(array $p): string
    {
        $bez = !empty($p['bez_vynosa']);
        $vynos = $bez
            ? 'без выноса — топка располагается непосредственно в парной.'
            : 'с выносной топкой — дрова загружаются из соседнего помещения, сохраняя чистоту парной.';
        return '<p>Банная печь <strong>Fireway ПароВар 18 Ковка (' . $p['code_display'] . ')</strong> — чугунная печь серии ПароВар ' . $vynos . ' Открытая каменка кованого исполнения вмещает до 120 кг камней и обеспечивает мягкий пар даже при большой парной.</p>'
            . '<ul>'
            . '<li>Объём парной до 18 м³</li>'
            . '<li>Открытая кованая каменка, 120 кг камней</li>'
            . '<li>Панорамное жаропрочное стекло</li>'
            . '<li>Чугунный корпус СЧ20 — долговечность и высокая теплоёмкость</li>'
            . '<li>Работа до 8 часов на одной закладке</li>'
            . '<li>Гарантия 36 месяцев</li>'
            . '</ul>';
    }

    private function desc24Kovka(array $p): string
    {
        $bez = !empty($p['bez_vynosa']);
        $vynos = $bez
            ? 'без выноса — топка в парной.'
            : 'с выносной топкой — загрузка дров из соседнего помещения.';
        $extra = '';
        if (str_contains($p['slug'], 'zakrytaya-kamenka')) {
            $extra = ' <strong>Вариант с закрытой каменкой</strong>: камни накрыты крышкой, что даёт влажный пар (лейте воду на камни через специальный клапан).';
        }
        return '<p>Банная печь <strong>Fireway ПароВар 24 Ковка (' . $p['code_display'] . ')</strong> — мощная чугунная печь ' . $vynos . $extra . ' Открытая кованая каменка на 180 кг камней обеспечивает длительный сеанс пара в парных объёмом до 24 м³.</p>'
            . '<ul>'
            . '<li>Объём парной до 24 м³</li>'
            . '<li>Кованая каменка, 180 кг камней</li>'
            . '<li>Панорамное жаропрочное стекло</li>'
            . '<li>Чугунный корпус СЧ20</li>'
            . '<li>Работа до 8 часов на одной закладке</li>'
            . '<li>Гарантия 36 месяцев</li>'
            . '</ul>';
    }

    private function desc18Prut(array $p): string
    {
        $bez = !empty($p['bez_vynosa']);
        $vynos = $bez ? 'без выноса' : 'с выносной топкой';
        return '<p>Банная печь <strong>Fireway ПароВар 18 (' . $p['code_display'] . ')</strong> — чугунная печь серии ПароВар ' . $vynos . ' с прутковой каменкой. Прутковое исполнение каменки легче кованого и отлично подходит для тех, кто ценит функциональность. Вмещает до 120 кг камней, объём парной до 18 м³.</p>'
            . '<ul>'
            . '<li>Объём парной до 18 м³</li>'
            . '<li>Прутковая каменка, 120 кг камней</li>'
            . '<li>Панорамное жаропрочное стекло</li>'
            . '<li>Чугунный корпус СЧ20</li>'
            . '<li>Гарантия 36 месяцев</li>'
            . '</ul>';
    }

    private function desc24Prut(array $p): string
    {
        $bez = !empty($p['bez_vynosa']);
        $vynos = $bez ? 'без выноса' : 'с выносной топкой';
        return '<p>Банная печь <strong>Fireway ПароВар 24 (' . $p['code_display'] . ')</strong> — чугунная печь серии ПароВар ' . $vynos . ' с прутковой каменкой на 180 кг. Прутковое исполнение — оптимальный выбор для больших парных объёмом до 24 м³.</p>'
            . '<ul>'
            . '<li>Объём парной до 24 м³</li>'
            . '<li>Прутковая каменка, 180 кг камней</li>'
            . '<li>Панорамное жаропрочное стекло</li>'
            . '<li>Чугунный корпус СЧ20</li>'
            . '<li>Гарантия 36 месяцев</li>'
            . '</ul>';
    }

    private function descAlma(array $p): string
    {
        $code = $p['code_display'];
        return '<p>Банная печь <strong>Fireway ALMA c Сеткой (' . $code . ')</strong> — премиальная чугунная банная печь с открытой сетчатой каменкой и выносной топкой. Массивный корпус весом 142 кг обеспечивает превосходную теплоёмкость. Высота 900 мм делает печь настоящей доминантой парной.</p>'
            . '<ul>'
            . '<li>Объём парной до 20 м³</li>'
            . '<li>Открытая сетчатая каменка, 60 кг камней</li>'
            . '<li>Вес печи 142 кг — высокая теплоёмкость</li>'
            . '<li>Дымоход Ø 140 мм</li>'
            . '<li>Покрытие: термостойкая эмаль антрацит</li>'
            . '<li>Гарантия 36 месяцев</li>'
            . '</ul>';
    }

    private function descKolchuga(): string
    {
        return '<p>Банная печь <strong>Fireway Pro-Пар Кольчуга 18 (201) с сеткой</strong> — чугунная печь серии Pro-Пар с выносной топкой и открытой сеткой на 100 кг камней. Серия Pro-Пар отличается увеличенным ресурсом и усиленной конструкцией каменки для профессиональных парных.</p>'
            . '<ul>'
            . '<li>Объём парной до 18 м³</li>'
            . '<li>Открытая сетка, 100 кг камней</li>'
            . '<li>Выносная топка (200 мм)</li>'
            . '<li>Панорамное жаропрочное стекло</li>'
            . '<li>Чугунный корпус СЧ20, вес 86 кг</li>'
            . '<li>Гарантия 36 месяцев</li>'
            . '</ul>';
    }

    private function descIskra(): string
    {
        return '<p>Банная печь <strong>Fireway ИСКРА 16</strong> — компактная чугунная банная печь серии Pro-Пар для небольших парных объёмом до 16 м³. Новинка 2026 года: эффективный нагрев при минимальных габаритах.</p>'
            . '<ul>'
            . '<li>Объём парной до 16 м³</li>'
            . '<li>Выносная топка</li>'
            . '<li>Чугунный корпус СЧ20</li>'
            . '<li>Гарантия 36 месяцев</li>'
            . '</ul>';
    }

    private function descFaraon(): string
    {
        return '<p>Банная печь <strong>Fireway Фараон 24 (K505)</strong> — новинка 2026 года в линейке Fireway. Чугунная банная печь для просторных парных объёмом до 24 м³ с выносной топкой и декоративным порталом K505.</p>'
            . '<ul>'
            . '<li>Объём парной до 24 м³</li>'
            . '<li>Выносная топка</li>'
            . '<li>Панорамное жаропрочное стекло</li>'
            . '<li>Чугунный корпус СЧ20</li>'
            . '<li>Гарантия 36 месяцев</li>'
            . '</ul>';
    }

    private function descSetka(array $p): string
    {
        $type = str_contains($p['slug'], 'kovka') ? 'ковка (кованая)' : 'кольчуга';
        return '<p>Сетка на трубу <strong>Fireway ' . $type . '</strong> — дополнительная каменка, устанавливаемая на дымовую трубу банной печи Fireway. Позволяет увеличить массу камней и улучшить качество пара без замены основной печи.</p>'
            . '<ul><li>Совместима с банными печами Fireway</li><li>Исполнение: ' . $type . '</li><li>Производство Россия</li></ul>';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Product list (63 товара из прайса Kaminproff 07.2026, строки 4–80)
    // ─────────────────────────────────────────────────────────────────────────
    private function products(): array
    {
        return [
            // ── НОВИНКИ ──────────────────────────────────────────────────────
            [
                'slug'         => 'bannaya-pech-fireway-faraon-24-k505',
                'name'         => 'Банная печь Fireway Фараон 24 (K505)',
                'rrc'          => 2135.00, 'opt' => 1643.00,
                'img'          => self::I24_K505,
                'series'       => 'faraon',
                'code'         => 'k505', 'code_display' => 'K505',
                'short'        => 'Новинка 2026. Чугунная банная печь Fireway Фараон 24 для парных до 24 м³, портал K505, выносная топка.',
            ],

            // ── PRO-ПАР ──────────────────────────────────────────────────────
            [
                'slug'         => 'bannaya-pech-fireway-kolchuga-18-201',
                'name'         => 'Банная печь Fireway Pro-Пар Кольчуга 18 (201) с сеткой',
                'rrc'          => 1481.00, 'opt' => 1185.00,
                'img'          => self::IKOLCHUGA,
                'series'       => 'kolchuga',
                'code'         => '201', 'code_display' => '201',
                'short'        => 'Чугунная банная печь Fireway Pro-Пар Кольчуга 18 для парных до 18 м³. Открытая сетка, 100 кг камней, выносная топка.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-iskra-16',
                'name'         => 'Банная печь Fireway ИСКРА 16',
                'rrc'          => 898.00, 'opt' => 711.00,
                'img'          => self::I18_WITH,
                'series'       => 'iskra',
                'code'         => '16', 'code_display' => '16',
                'short'        => 'Компактная чугунная банная печь Fireway ИСКРА 16 для парных до 16 м³. Выносная топка, новинка 2026 года.',
            ],

            // ── ПАРОВАР 18 КОВКА (с выносом) ─────────────────────────────────
            [
                'slug'         => 'bannaya-pech-fireway-parovar-18-kovka-k201',
                'name'         => 'Банная печь Fireway ПароВар 18 Ковка (K201)',
                'rrc'          => 1371.00, 'opt' => 1055.00,
                'img'          => self::I18_WITH,
                'series'       => 'pr18_kovka',
                'code'         => 'k201', 'code_display' => 'K201',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 18 с кованой каменкой и выносом (K201). Парная до 18 м³, 120 кг камней.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-18-kovka-k505',
                'name'         => 'Банная печь Fireway ПароВар 18 Ковка (К505)',
                'rrc'          => 1920.00, 'opt' => 1477.00,
                'img'          => self::I18_K505,
                'series'       => 'pr18_kovka',
                'code'         => 'k505', 'code_display' => 'К505',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 18 с кованой каменкой и выносом (К505). Парная до 18 м³, 120 кг камней, декоративный портал К505.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-18-kovka-k404',
                'name'         => 'Банная печь Fireway ПароВар 18 Ковка (К404)',
                'rrc'          => 1724.00, 'opt' => 1326.00,
                'img'          => self::I18_WITH,
                'series'       => 'pr18_kovka',
                'code'         => 'k404', 'code_display' => 'К404',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 18 с кованой каменкой и выносом (К404). Парная до 18 м³, 120 кг камней.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-18-kovka-k414',
                'name'         => 'Банная печь Fireway ПароВар 18 Ковка (К414)',
                'rrc'          => 1724.00, 'opt' => 1326.00,
                'img'          => self::I18_WITH,
                'series'       => 'pr18_kovka',
                'code'         => 'k414', 'code_display' => 'К414',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 18 с кованой каменкой и выносом (К414). Парная до 18 м³, 120 кг камней.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-18-kovka-k211',
                'name'         => 'Банная печь Fireway ПароВар 18 Ковка (К211)',
                'rrc'          => 1332.00, 'opt' => 1025.00,
                'img'          => self::I18_WITH,
                'series'       => 'pr18_kovka',
                'code'         => 'k211', 'code_display' => 'К211',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 18 с кованой каменкой и выносом (К211). Парная до 18 м³, 120 кг камней.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-18-kovka-302',
                'name'         => 'Банная печь Fireway ПароВар 18 Ковка (302)',
                'rrc'          => 1430.00, 'opt' => 1100.00,
                'img'          => self::I18_WITH,
                'series'       => 'pr18_kovka',
                'code'         => '302', 'code_display' => '302',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 18 с кованой каменкой и выносом (302). Парная до 18 м³, 120 кг камней.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-18-kovka-402',
                'name'         => 'Банная печь Fireway ПароВар 18 Ковка (402)',
                'rrc'          => 1528.00, 'opt' => 1175.00,
                'img'          => self::I18_WITH,
                'series'       => 'pr18_kovka',
                'code'         => '402', 'code_display' => '402',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 18 с кованой каменкой и выносом (402). Парная до 18 м³, 120 кг камней.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-18-kovka-412',
                'name'         => 'Банная печь Fireway ПароВар 18 Ковка (412)',
                'rrc'          => 1489.00, 'opt' => 1145.00,
                'img'          => self::I18_WITH,
                'series'       => 'pr18_kovka',
                'code'         => '412', 'code_display' => '412',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 18 с кованой каменкой и выносом (412). Парная до 18 м³, 120 кг камней.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-18-kovka-413',
                'name'         => 'Банная печь Fireway ПароВар 18 Ковка (413)',
                'rrc'          => 1724.00, 'opt' => 1326.00,
                'img'          => self::I18_WITH,
                'series'       => 'pr18_kovka',
                'code'         => '413', 'code_display' => '413',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 18 с кованой каменкой и выносом (413). Парная до 18 м³, 120 кг камней.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-18-kovka-403',
                'name'         => 'Банная печь Fireway ПароВар 18 Ковка (403)',
                'rrc'          => 1567.00, 'opt' => 1206.00,
                'img'          => self::I18_WITH,
                'series'       => 'pr18_kovka',
                'code'         => '403', 'code_display' => '403',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 18 с кованой каменкой и выносом (403). Парная до 18 м³, 120 кг камней.',
            ],

            // ── ПАРОВАР 18 КОВКА (без выноса) ────────────────────────────────
            [
                'slug'         => 'bannaya-pech-fireway-parovar-18-kovka-k201-bez-vynosa',
                'name'         => 'Банная печь Fireway ПароВар 18 Ковка (К201) без выноса',
                'rrc'          => 1371.00, 'opt' => 1055.00,
                'img'          => self::I18_BEZ,
                'series'       => 'pr18_kovka',
                'code'         => 'k201', 'code_display' => 'К201',
                'bez_vynosa'   => true,
                'short'        => 'Чугунная банная печь Fireway ПароВар 18 Ковка (К201) без выноса. Топка в парной, каменка ковка 120 кг, до 18 м³.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-18-kovka-k211-bez-vynosa',
                'name'         => 'Банная печь Fireway ПароВар 18 Ковка (К211) без выноса',
                'rrc'          => 1332.00, 'opt' => 1025.00,
                'img'          => self::I18_BEZ,
                'series'       => 'pr18_kovka',
                'code'         => 'k211', 'code_display' => 'К211',
                'bez_vynosa'   => true,
                'short'        => 'Чугунная банная печь Fireway ПароВар 18 Ковка (К211) без выноса. Топка в парной, каменка ковка 120 кг, до 18 м³.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-18-kovka-402-bez-vynosa',
                'name'         => 'Банная печь Fireway ПароВар 18 Ковка (402) без выноса',
                'rrc'          => 1528.00, 'opt' => 1175.00,
                'img'          => self::I18_BEZ,
                'series'       => 'pr18_kovka',
                'code'         => '402', 'code_display' => '402',
                'bez_vynosa'   => true,
                'short'        => 'Чугунная банная печь Fireway ПароВар 18 Ковка (402) без выноса. Каменка ковка 120 кг, объём парной до 18 м³.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-18-kovka-412-bez-vynosa',
                'name'         => 'Банная печь Fireway ПароВар 18 Ковка (412) без выноса',
                'rrc'          => 1489.00, 'opt' => 1145.00,
                'img'          => self::I18_BEZ,
                'series'       => 'pr18_kovka',
                'code'         => '412', 'code_display' => '412',
                'bez_vynosa'   => true,
                'short'        => 'Чугунная банная печь Fireway ПароВар 18 Ковка (412) без выноса. Каменка ковка 120 кг, объём парной до 18 м³.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-18-kovka-302-bez-vynosa',
                'name'         => 'Банная печь Fireway ПароВар 18 Ковка без выноса (302)',
                'rrc'          => 1430.00, 'opt' => 1100.00,
                'img'          => self::I18_BEZ,
                'series'       => 'pr18_kovka',
                'code'         => '302', 'code_display' => '302',
                'bez_vynosa'   => true,
                'short'        => 'Чугунная банная печь Fireway ПароВар 18 Ковка (302) без выноса. Каменка ковка 120 кг, объём парной до 18 м³.',
            ],

            // ── ПАРОВАР 24 КОВКА (с выносом) ─────────────────────────────────
            [
                'slug'         => 'bannaya-pech-fireway-parovar-24-kovka-k505-zakrytaya-kamenka',
                'name'         => 'Банная печь Fireway ПароВар 24 Ковка с закрытой каменкой (K505)',
                'rrc'          => 2355.00, 'opt' => 1811.00,
                'img'          => self::I24_K505,
                'series'       => 'pr24_kovka',
                'code'         => 'k505', 'code_display' => 'K505',
                'bez_vynosa'   => false,
                'short'        => 'Fireway ПароВар 24 Ковка K505 с закрытой каменкой: влажный пар, 180 кг камней, выносная топка, парная до 24 м³.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-24-kovka-k201',
                'name'         => 'Банная печь Fireway ПароВар 24 Ковка (К201)',
                'rrc'          => 1624.00, 'opt' => 1250.00,
                'img'          => self::I24_WITH,
                'series'       => 'pr24_kovka',
                'code'         => 'k201', 'code_display' => 'К201',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 24 Ковка (К201). Парная до 24 м³, кованая каменка 180 кг, выносная топка.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-24-kovka-k505',
                'name'         => 'Банная печь Fireway ПароВар 24 Ковка (К505)',
                'rrc'          => 2135.00, 'opt' => 1643.00,
                'img'          => self::I24_K505,
                'series'       => 'pr24_kovka',
                'code'         => 'k505', 'code_display' => 'К505',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 24 Ковка (К505). Парная до 24 м³, 180 кг камней, декоративный портал К505.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-24-kovka-pr04',
                'name'         => 'Банная печь Fireway ПароВар 24 Ковка (PR-04)',
                'rrc'          => 2208.00, 'opt' => 1699.00,
                'img'          => self::I24_WITH,
                'series'       => 'pr24_kovka',
                'code'         => 'pr-04', 'code_display' => 'PR-04',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 24 Ковка (PR-04). Парная до 24 м³, 180 кг камней, портал PR-04.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-24-kovka-k414',
                'name'         => 'Банная печь Fireway ПароВар 24 Ковка (K414)',
                'rrc'          => 1953.00, 'opt' => 1502.00,
                'img'          => self::I24_WITH,
                'series'       => 'pr24_kovka',
                'code'         => 'k414', 'code_display' => 'K414',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 24 Ковка (K414). Парная до 24 м³, кованая каменка 180 кг, выносная топка.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-24-kovka-k211',
                'name'         => 'Банная печь Fireway ПароВар 24 Ковка (К211)',
                'rrc'          => 1588.00, 'opt' => 1221.00,
                'img'          => self::I24_WITH,
                'series'       => 'pr24_kovka',
                'code'         => 'k211', 'code_display' => 'К211',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 24 Ковка (К211). Парная до 24 м³, кованая каменка 180 кг, выносная топка.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-24-kovka-k404',
                'name'         => 'Банная печь Fireway ПароВар 24 Ковка (К404)',
                'rrc'          => 1953.00, 'opt' => 1502.00,
                'img'          => self::I24_K404,
                'series'       => 'pr24_kovka',
                'code'         => 'k404', 'code_display' => 'К404',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 24 Ковка (К404). Парная до 24 м³, кованая каменка 180 кг, выносная топка.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-24-kovka-k402',
                'name'         => 'Банная печь Fireway ПароВар 24 Ковка (К402)',
                'rrc'          => 1770.00, 'opt' => 1362.00,
                'img'          => self::I24_WITH,
                'series'       => 'pr24_kovka',
                'code'         => 'k402', 'code_display' => 'К402',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 24 Ковка (К402). Парная до 24 м³, кованая каменка 180 кг, выносная топка.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-24-kovka-k412',
                'name'         => 'Банная печь Fireway ПароВар 24 Ковка (К412)',
                'rrc'          => 1734.00, 'opt' => 1334.00,
                'img'          => self::I24_WITH,
                'series'       => 'pr24_kovka',
                'code'         => 'k412', 'code_display' => 'К412',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 24 Ковка (К412). Парная до 24 м³, кованая каменка 180 кг, выносная топка.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-24-kovka-k302',
                'name'         => 'Банная печь Fireway ПароВар 24 Ковка (К302)',
                'rrc'          => 1679.00, 'opt' => 1292.00,
                'img'          => self::I24_WITH,
                'series'       => 'pr24_kovka',
                'code'         => 'k302', 'code_display' => 'К302',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 24 Ковка (К302). Парная до 24 м³, кованая каменка 180 кг, выносная топка.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-24-kovka-k312',
                'name'         => 'Банная печь Fireway ПароВар 24 Ковка (К312)',
                'rrc'          => 1643.00, 'opt' => 1264.00,
                'img'          => self::I24_WITH,
                'series'       => 'pr24_kovka',
                'code'         => 'k312', 'code_display' => 'К312',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 24 Ковка (К312). Парная до 24 м³, кованая каменка 180 кг, выносная топка.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-24-kovka-k413',
                'name'         => 'Банная печь Fireway ПароВар 24 Ковка (К413)',
                'rrc'          => 1953.00, 'opt' => 1502.00,
                'img'          => self::I24_WITH,
                'series'       => 'pr24_kovka',
                'code'         => 'k413', 'code_display' => 'К413',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 24 Ковка (К413). Парная до 24 м³, кованая каменка 180 кг, выносная топка.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-24-kovka-k403',
                'name'         => 'Банная печь Fireway ПароВар 24 Ковка (К403)',
                'rrc'          => 1643.00, 'opt' => 1264.00,
                'img'          => self::I24_WITH,
                'series'       => 'pr24_kovka',
                'code'         => 'k403', 'code_display' => 'К403',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 24 Ковка (К403). Парная до 24 м³, кованая каменка 180 кг, выносная топка.',
            ],

            // ── ПАРОВАР 24 КОВКА (без выноса) ────────────────────────────────
            [
                'slug'         => 'bannaya-pech-fireway-parovar-24-kovka-k211-bez-vynosa',
                'name'         => 'Банная печь Fireway ПароВар 24 Ковка (К211) без выноса',
                'rrc'          => 1588.00, 'opt' => 1221.00,
                'img'          => self::I24_WITH,
                'series'       => 'pr24_kovka',
                'code'         => 'k211', 'code_display' => 'К211',
                'bez_vynosa'   => true,
                'short'        => 'Чугунная банная печь Fireway ПароВар 24 Ковка (К211) без выноса. Парная до 24 м³, 180 кг камней, топка в парной.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-24-kovka-k201-bez-vynosa',
                'name'         => 'Банная печь Fireway ПароВар 24 Ковка (К201) без выноса',
                'rrc'          => 1624.00, 'opt' => 1250.00,
                'img'          => self::I24_WITH,
                'series'       => 'pr24_kovka',
                'code'         => 'k201', 'code_display' => 'К201',
                'bez_vynosa'   => true,
                'short'        => 'Чугунная банная печь Fireway ПароВар 24 Ковка (К201) без выноса. Парная до 24 м³, 180 кг камней.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-24-kovka-k402-bez-vynosa',
                'name'         => 'Банная печь Fireway ПароВар 24 Ковка (К402) без выноса',
                'rrc'          => 1770.00, 'opt' => 1362.00,
                'img'          => self::I24_WITH,
                'series'       => 'pr24_kovka',
                'code'         => 'k402', 'code_display' => 'К402',
                'bez_vynosa'   => true,
                'short'        => 'Чугунная банная печь Fireway ПароВар 24 Ковка (К402) без выноса. Парная до 24 м³, 180 кг камней.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-24-kovka-k302-bez-vynosa',
                'name'         => 'Банная печь Fireway ПароВар 24 Ковка (К302) без выноса',
                'rrc'          => 1679.00, 'opt' => 1292.00,
                'img'          => self::I24_WITH,
                'series'       => 'pr24_kovka',
                'code'         => 'k302', 'code_display' => 'К302',
                'bez_vynosa'   => true,
                'short'        => 'Чугунная банная печь Fireway ПароВар 24 Ковка (К302) без выноса. Парная до 24 м³, 180 кг камней.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-24-kovka-k412-bez-vynosa',
                'name'         => 'Банная печь Fireway ПароВар 24 Ковка (К412) без выноса',
                'rrc'          => 1734.00, 'opt' => 1334.00,
                'img'          => self::I24_WITH,
                'series'       => 'pr24_kovka',
                'code'         => 'k412', 'code_display' => 'К412',
                'bez_vynosa'   => true,
                'short'        => 'Чугунная банная печь Fireway ПароВар 24 Ковка (К412) без выноса. Парная до 24 м³, 180 кг камней.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-24-kovka-k312-bez-vynosa',
                'name'         => 'Банная печь Fireway ПароВар 24 Ковка (К312) без выноса',
                'rrc'          => 1643.00, 'opt' => 1264.00,
                'img'          => self::I24_WITH,
                'series'       => 'pr24_kovka',
                'code'         => 'k312', 'code_display' => 'К312',
                'bez_vynosa'   => true,
                'short'        => 'Чугунная банная печь Fireway ПароВар 24 Ковка (К312) без выноса. Парная до 24 м³, 180 кг камней.',
            ],

            // ── ALMA (варианты) ───────────────────────────────────────────────
            [
                'slug'         => 'bannaya-pech-fireway-alma-pr04',
                'name'         => 'Банная печь Fireway ALMA c Сеткой (PR04) с выносом',
                'rrc'          => 2939.00, 'opt' => 2261.00,
                'img'          => self::IALMA,
                'series'       => 'alma',
                'code'         => 'pr04', 'code_display' => 'PR04',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ALMA с сеткой (PR04) и выносной топкой. Парная до 20 м³, 60 кг камней, вес 142 кг.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-alma-k505',
                'name'         => 'Банная печь Fireway ALMA c Сеткой (K505) с выносом',
                'rrc'          => 2684.00, 'opt' => 2065.00,
                'img'          => self::IALMA,
                'series'       => 'alma',
                'code'         => 'k505', 'code_display' => 'K505',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ALMA с сеткой (K505) и выносной топкой. Парная до 20 м³, 60 кг камней, вес 142 кг.',
            ],

            // ── ПАРОВАР 24 БЕЗ СЕТКИ (прут) ─────────────────────────────────
            [
                'slug'         => 'bannaya-pech-fireway-parovar-24-k201-prut',
                'name'         => 'Банная печь Fireway ПароВар 24 (К201)',
                'rrc'          => 1352.00, 'opt' => 1040.00,
                'img'          => self::I24_WITH,
                'series'       => 'pr24_prut',
                'code'         => 'k201', 'code_display' => 'К201',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 24 (К201) с прутковой каменкой. Парная до 24 м³, 180 кг камней, выносная топка.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-24-k414-prut',
                'name'         => 'Банная печь Fireway ПароВар 24 (К414)',
                'rrc'          => 1705.00, 'opt' => 1311.00,
                'img'          => self::I24_WITH,
                'series'       => 'pr24_prut',
                'code'         => 'k414', 'code_display' => 'К414',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 24 (К414) с прутковой каменкой. Парная до 24 м³, 180 кг камней, выносная топка.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-24-k412-prut',
                'name'         => 'Банная печь Fireway ПароВар 24 (К412)',
                'rrc'          => 1469.00, 'opt' => 1130.00,
                'img'          => self::I24_WITH,
                'series'       => 'pr24_prut',
                'code'         => 'k412', 'code_display' => 'К412',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 24 (К412) с прутковой каменкой. Парная до 24 м³, 180 кг камней, выносная топка.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-24-k211-prut',
                'name'         => 'Банная печь Fireway ПароВар 24 (К211)',
                'rrc'          => 1313.00, 'opt' => 1010.00,
                'img'          => self::I24_WITH,
                'series'       => 'pr24_prut',
                'code'         => 'k211', 'code_display' => 'К211',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 24 (К211) с прутковой каменкой. Парная до 24 м³, 180 кг камней, выносная топка.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-24-k505-prut',
                'name'         => 'Банная печь Fireway ПароВар 24 (К505)',
                'rrc'          => 1900.00, 'opt' => 1462.00,
                'img'          => self::I24_K505,
                'series'       => 'pr24_prut',
                'code'         => 'k505', 'code_display' => 'К505',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 24 (К505) с прутковой каменкой. Парная до 24 м³, 180 кг камней, портал К505.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-24-k302-prut',
                'name'         => 'Банная печь Fireway ПароВар 24 (К302)',
                'rrc'          => 1274.00, 'opt' => 980.00,
                'img'          => self::I24_WITH,
                'series'       => 'pr24_prut',
                'code'         => 'k302', 'code_display' => 'К302',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 24 (К302) с прутковой каменкой. Парная до 24 м³, 180 кг камней.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-24-k404-prut',
                'name'         => 'Банная печь Fireway ПароВар 24 (К404)',
                'rrc'          => 1567.00, 'opt' => 1206.00,
                'img'          => self::I24_K404,
                'series'       => 'pr24_prut',
                'code'         => 'k404', 'code_display' => 'К404',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 24 (К404) с прутковой каменкой. Парная до 24 м³, 180 кг камней.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-24-k402-prut',
                'name'         => 'Банная печь Fireway ПароВар 24 (К402)',
                'rrc'          => 1509.00, 'opt' => 1160.00,
                'img'          => self::I24_WITH,
                'series'       => 'pr24_prut',
                'code'         => 'k402', 'code_display' => 'К402',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 24 (К402) с прутковой каменкой. Парная до 24 м³, 180 кг камней.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-24-k413-prut',
                'name'         => 'Банная печь Fireway ПароВар 24 (К413)',
                'rrc'          => 1705.00, 'opt' => 1311.00,
                'img'          => self::I24_WITH,
                'series'       => 'pr24_prut',
                'code'         => 'k413', 'code_display' => 'К413',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 24 (К413) с прутковой каменкой. Парная до 24 м³, 180 кг камней.',
            ],

            // ── ПАРОВАР 24 БЕЗ ВЫНОСА (прут) ─────────────────────────────────
            [
                'slug'         => 'bannaya-pech-fireway-parovar-24-k402-prut-bez-vynosa',
                'name'         => 'Банная печь Fireway ПароВар 24 (К402) без выноса',
                'rrc'          => 1509.00, 'opt' => 1160.00,
                'img'          => self::I24_WITH,
                'series'       => 'pr24_prut',
                'code'         => 'k402', 'code_display' => 'К402',
                'bez_vynosa'   => true,
                'short'        => 'Чугунная банная печь Fireway ПароВар 24 (К402) без выноса. Прутковая каменка, парная до 24 м³, 180 кг камней.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-24-k201-prut-bez-vynosa',
                'name'         => 'Банная печь Fireway ПароВар 24 (К201) без выноса',
                'rrc'          => 1352.00, 'opt' => 1040.00,
                'img'          => self::I24_WITH,
                'series'       => 'pr24_prut',
                'code'         => 'k201', 'code_display' => 'К201',
                'bez_vynosa'   => true,
                'short'        => 'Чугунная банная печь Fireway ПароВар 24 (К201) без выноса. Прутковая каменка, парная до 24 м³, 180 кг камней.',
            ],

            // ── ПАРОВАР 18 БЕЗ СЕТКИ (прут) ─────────────────────────────────
            [
                'slug'         => 'bannaya-pech-fireway-parovar-18-k201-prut',
                'name'         => 'Банная печь Fireway ПароВар 18 (К201)',
                'rrc'          => 1215.00, 'opt' => 934.00,
                'img'          => self::I18_WITH,
                'series'       => 'pr18_prut',
                'code'         => 'k201', 'code_display' => 'К201',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 18 (К201) с прутковой каменкой. Парная до 18 м³, 120 кг камней, выносная топка.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-18-k505-prut',
                'name'         => 'Банная печь Fireway ПароВар 18 (К505)',
                'rrc'          => 1763.00, 'opt' => 1356.00,
                'img'          => self::I18_K505,
                'series'       => 'pr18_prut',
                'code'         => 'k505', 'code_display' => 'К505',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 18 (К505) с прутковой каменкой. Парная до 18 м³, 120 кг камней, портал К505.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-18-k404-prut',
                'name'         => 'Банная печь Fireway ПароВар 18 (К404)',
                'rrc'          => 1567.00, 'opt' => 1206.00,
                'img'          => self::I18_WITH,
                'series'       => 'pr18_prut',
                'code'         => 'k404', 'code_display' => 'К404',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 18 (К404) с прутковой каменкой. Парная до 18 м³, 120 кг камней.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-18-k414-prut',
                'name'         => 'Банная печь Fireway ПароВар 18 (К414)',
                'rrc'          => 1567.00, 'opt' => 1206.00,
                'img'          => self::I18_WITH,
                'series'       => 'pr18_prut',
                'code'         => 'k414', 'code_display' => 'К414',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 18 (К414) с прутковой каменкой. Парная до 18 м³, 120 кг камней.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-18-k211-prut',
                'name'         => 'Банная печь Fireway ПароВар 18 (К211)',
                'rrc'          => 1176.00, 'opt' => 904.00,
                'img'          => self::I18_WITH,
                'series'       => 'pr18_prut',
                'code'         => 'k211', 'code_display' => 'К211',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 18 (К211) с прутковой каменкой. Парная до 18 м³, 120 кг камней.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-18-k402-prut',
                'name'         => 'Банная печь Fireway ПароВар 18 (К402)',
                'rrc'          => 1371.00, 'opt' => 1055.00,
                'img'          => self::I18_WITH,
                'series'       => 'pr18_prut',
                'code'         => 'k402', 'code_display' => 'К402',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 18 (К402) с прутковой каменкой. Парная до 18 м³, 120 кг камней.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-18-k412-prut',
                'name'         => 'Банная печь Fireway ПароВар 18 (К412)',
                'rrc'          => 1332.00, 'opt' => 1025.00,
                'img'          => self::I18_WITH,
                'series'       => 'pr18_prut',
                'code'         => 'k412', 'code_display' => 'К412',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 18 (К412) с прутковой каменкой. Парная до 18 м³, 120 кг камней.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-18-k302-prut',
                'name'         => 'Банная печь Fireway ПароВар 18 (К302)',
                'rrc'          => 1136.00, 'opt' => 874.00,
                'img'          => self::I18_WITH,
                'series'       => 'pr18_prut',
                'code'         => 'k302', 'code_display' => 'К302',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 18 (К302) с прутковой каменкой. Парная до 18 м³, 120 кг камней.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-18-k413-prut',
                'name'         => 'Банная печь Fireway ПароВар 18 (К413)',
                'rrc'          => 1567.00, 'opt' => 1206.00,
                'img'          => self::I18_WITH,
                'series'       => 'pr18_prut',
                'code'         => 'k413', 'code_display' => 'К413',
                'bez_vynosa'   => false,
                'short'        => 'Чугунная банная печь Fireway ПароВар 18 (К413) с прутковой каменкой. Парная до 18 м³, 120 кг камней.',
            ],

            // ── ПАРОВАР 18 БЕЗ ВЫНОСА (прут) ─────────────────────────────────
            [
                'slug'         => 'bannaya-pech-fireway-parovar-18-k201-prut-bez-vynosa',
                'name'         => 'Банная печь Fireway ПароВар 18 (К201) без выноса',
                'rrc'          => 1215.00, 'opt' => 934.00,
                'img'          => self::I18_BEZ,
                'series'       => 'pr18_prut',
                'code'         => 'k201', 'code_display' => 'К201',
                'bez_vynosa'   => true,
                'short'        => 'Чугунная банная печь Fireway ПароВар 18 (К201) без выноса. Прутковая каменка, парная до 18 м³, 120 кг камней.',
            ],
            [
                'slug'         => 'bannaya-pech-fireway-parovar-18-k211-prut-bez-vynosa',
                'name'         => 'Банная печь Fireway ПароВар 18 (К211) без выноса',
                'rrc'          => 1176.00, 'opt' => 904.00,
                'img'          => self::I18_BEZ,
                'series'       => 'pr18_prut',
                'code'         => 'k211', 'code_display' => 'К211',
                'bez_vynosa'   => true,
                'short'        => 'Чугунная банная печь Fireway ПароВар 18 (К211) без выноса. Прутковая каменка, парная до 18 м³, 120 кг камней.',
            ],

            // ── АКСЕССУАРЫ ────────────────────────────────────────────────────
            [
                'slug'         => 'setka-na-trubu-fireway-kovka',
                'name'         => 'Сетка на трубу FireWay ковка',
                'rrc'          => 136.00, 'opt' => 104.00,
                'img'          => self::I18_WITH,
                'series'       => 'setka',
                'code'         => 'kovka', 'code_display' => 'ковка',
                'bez_vynosa'   => false,
                'short'        => 'Кованая сетка на дымовую трубу для банных печей Fireway. Увеличивает массу камней, улучшает качество пара.',
            ],
            [
                'slug'         => 'setka-na-trubu-fireway-kolchuga',
                'name'         => 'Сетка на трубу FireWay кольчуга',
                'rrc'          => 123.00, 'opt' => 105.00,
                'img'          => self::IKOLCHUGA,
                'series'       => 'setka',
                'code'         => 'kolchuga', 'code_display' => 'кольчуга',
                'bez_vynosa'   => false,
                'short'        => 'Сетка-кольчуга на дымовую трубу для банных печей Fireway. Увеличивает массу камней для бани.',
            ],
        ];
    }
};
