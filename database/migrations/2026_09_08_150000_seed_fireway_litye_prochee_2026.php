<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;

/**
 * Seed Fireway «Печное литьё» (33 новых) + «Прочее» (11 новых) = 44 товара
 * Прайс kaminproff 07.2026. Категория: Печное и каминное литьё (cat_id=287)
 */
return new class extends Migration
{
    private const BRAND_ID      = 299;  // Fireway
    private const CAT_ID        = 287;  // Печное и каминное литьё
    private const SUPPLIER_CODE = 'kaminproff';

    // ── Image URL constants ──────────────────────────────────────────────
    // fireway.pro — оригинальные фото
    private const I_PR01   = 'https://fireway.pro/assets/media/products/574/small/9299beeb79df3bd1d44540d2902822a083b980c4.jpg';
    private const I_PR02   = 'https://fireway.pro/assets/media/products/575/small/adff9b2b7e652171a791fc81fad4fc4022a0a65a.jpg';
    private const I_PR03   = 'https://fireway.pro/assets/media/products/576/small/c89f124791ba7bc491135a87d7bec0308a7a914d.jpg';
    private const I_PR04   = 'https://fireway.pro/assets/media/products/577/small/498c568e2a5f13cc6add53e4acda2501c3f4caf1.jpg';
    private const I_PR05   = 'https://fireway.pro/assets/media/products/578/small/f85dcdf2cebca42bb5e2a5a7f9538b10458c4c0a.jpg';
    private const I_K505   = 'https://fireway.pro/assets/media/products/28/small/80456.jpg';
    private const I_K403   = 'https://fireway.pro/assets/media/products/141/bhb-k403-250x280.jpg';
    private const I_A101   = 'https://fireway.pro/assets/media/products/30/small/bhb-a101-2.jpg';
    private const I_B103   = 'https://fireway.pro/assets/media/products/128/small/bhb-b103-240x140.jpg';
    private const I_B104   = 'https://fireway.pro/assets/media/products/127/small/bhb-b102-240x140.jpg';
    // pechnoydom.ru — фото задвижек/колосников/плит
    private const I_R103   = 'https://www.pechnoydom.ru/files/products/bhb-r103-350h200-2-min.400x400.jpg';
    private const I_R104   = 'https://www.pechnoydom.ru/files/products/bhb-r104-380x250-2-min.400x400.jpg';
    private const I_Z102   = 'https://www.pechnoydom.ru/files/products/bhb-z102-min.400x400.jpg';
    private const I_Z104   = 'https://www.pechnoydom.ru/files/products/z104-min.400x400.jpg';
    private const I_Z105   = 'https://www.pechnoydom.ru/files/products/z105-min.400x400.jpg';
    private const I_Z106   = 'https://www.pechnoydom.ru/files/products/bhb-z106-min.400x400.jpg';
    private const I_Z107   = 'https://www.pechnoydom.ru/files/products/z107-min.400x400.jpg';
    private const I_Z108   = 'https://www.pechnoydom.ru/files/products/bhb-z108-min.400x400.jpg';
    private const I_T201   = 'https://www.pechnoydom.ru/files/products/bhb-t201-690x450-min.400x400.jpg';
    private const I_T202   = 'https://www.pechnoydom.ru/files/products/plita-vid-sverxu2.400x400.jpg';
    // fireway.pro — светильники
    private const I_MAYAK1 = 'https://fireway.pro/assets/media/products/39/small/fireway-svetilnik-mayak-1.jpg';
    private const I_MAYAK2 = 'https://fireway.pro/assets/media/products/40/small/fireway-svetilnik-mayak-2.jpg';
    private const I_SHAJBA = 'https://fireway.pro/assets/media/products/41/small/fireway-svetilnik-shaiba.jpg';
    private const I_LINDER = 'https://fireway.pro/assets/media/products/42/small/fireway-svetilnik-linder.jpg';
    private const I_MAYAKM = 'https://fireway.pro/assets/media/products/43/small/fireway-svetilnik-mayak-mini.jpg';
    private const I_OBLIK  = 'https://fireway.pro/assets/media/products/44/small/fireway-svetilnik-oblik.jpg';

    // ── up ───────────────────────────────────────────────────────────────
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
                    'specs'               => $p['specs'] ?? [],
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

    public function down(): void {}

    // ── Генератор content (HTML) ─────────────────────────────────────────
    private function content(array $p): string
    {
        $name  = htmlspecialchars($p['name']);
        $short = htmlspecialchars($p['short']);

        $specsHtml = '';
        if (!empty($p['specs'])) {
            $rows = '';
            foreach ($p['specs'] as $k => $v) {
                $rows .= '<tr><th>' . htmlspecialchars((string) $k) . '</th>'
                       . '<td>' . htmlspecialchars((string) $v) . '</td></tr>';
            }
            $specsHtml = '<h3>Характеристики</h3>'
                       . '<table class="specs-table">' . $rows . '</table>';
        }

        return <<<HTML
<p>{$short}</p>
<p>Производитель: <strong>Fireway</strong> — российский производитель чугунного оборудования для каминов и печей. Продукция изготовлена из качественного серого чугуна, проходит контроль качества литья и толщины стенок.</p>
{$specsHtml}
HTML;
    }

    // ── Массив товаров (44 шт.) ──────────────────────────────────────────
    private function products(): array
    {
        return [

            // ═══════════════════════════════════════════════════════════════
            // ПЕЧНОЕ ЛИТЬЁ (33 новых товара)
            // ═══════════════════════════════════════════════════════════════

            // ── PR-серия — декоративные дверцы ──────────────────────────
            [
                'slug'  => 'fireway-dverza-pr-01-prochistnaya',
                'name'  => 'Дверца прочистная Fireway PR-01',
                'rrc'   => 112.00,
                'opt'   => 84.00,
                'img'   => self::I_PR01,
                'short' => 'Чугунная прочистная дверца PR-01 для технического обслуживания дымового канала.',
                'specs' => [
                    'Артикул'  => 'PR-01',
                    'Тип'      => 'Прочистная',
                    'Материал' => 'Чугун',
                ],
            ],
            [
                'slug'  => 'fireway-dverza-pr-02-podduvalnaya',
                'name'  => 'Дверца поддувальная Fireway PR-02',
                'rrc'   => 231.00,
                'opt'   => 174.00,
                'img'   => self::I_PR02,
                'short' => 'Чугунная поддувальная дверца PR-02 для регулировки подачи воздуха в топку.',
                'specs' => [
                    'Артикул'  => 'PR-02',
                    'Тип'      => 'Поддувальная',
                    'Материал' => 'Чугун',
                ],
            ],
            [
                'slug'  => 'fireway-dverza-pr-03-topochnaya',
                'name'  => 'Дверца топочная Fireway PR-03',
                'rrc'   => 599.00,
                'opt'   => 450.00,
                'img'   => self::I_PR03,
                'short' => 'Чугунная топочная дверца PR-03 для загрузки дров и контроля горения.',
                'specs' => [
                    'Артикул'  => 'PR-03',
                    'Тип'      => 'Топочная',
                    'Материал' => 'Чугун',
                ],
            ],
            [
                'slug'  => 'fireway-dverza-pr-04-kaminnaya',
                'name'  => 'Дверца каминная Fireway PR-04',
                'rrc'   => 786.00,
                'opt'   => 591.00,
                'img'   => self::I_PR04,
                'short' => 'Чугунная каминная дверца PR-04 с декоративным литьём.',
                'specs' => [
                    'Артикул'  => 'PR-04',
                    'Тип'      => 'Каминная',
                    'Материал' => 'Чугун',
                ],
            ],
            [
                'slug'  => 'fireway-dverza-pr-05-hlebnaya',
                'name'  => 'Дверца хлебная Fireway PR-05',
                'rrc'   => 695.00,
                'opt'   => 522.00,
                'img'   => self::I_PR05,
                'short' => 'Чугунная хлебная дверца PR-05 для хлебопекарной камеры печи.',
                'specs' => [
                    'Артикул'  => 'PR-05',
                    'Тип'      => 'Хлебная',
                    'Материал' => 'Чугун',
                ],
            ],

            // ── K-серия — топочные застеклённые ─────────────────────────
            [
                'slug'  => 'fireway-dverza-k505-topochnaya-zasteklennaya',
                'name'  => 'Дверца К505 топочная застеклённая 410×410 мм Fireway',
                'rrc'   => 744.00,
                'opt'   => 559.00,
                'img'   => self::I_K505,
                'short' => 'Чугунная топочная дверца К505 с жаростойким стеклом, размер 410×410 мм.',
                'specs' => [
                    'Артикул'  => 'К505',
                    'Размер'   => '410×410 мм',
                    'Стекло'   => 'Да',
                    'Тип'      => 'Топочная',
                    'Материал' => 'Чугун',
                ],
            ],
            [
                'slug'  => 'fireway-dverza-k403-topochnaya-zasteklennaya',
                'name'  => 'Дверца К-403 топочная застеклённая 403×350 мм Fireway',
                'rrc'   => 424.00,
                'opt'   => 319.00,
                'img'   => self::I_K403,
                'short' => 'Чугунная топочная дверца К-403 с жаростойким стеклом, размер 403×350 мм.',
                'specs' => [
                    'Артикул'  => 'К-403',
                    'Размер'   => '403×350 мм',
                    'Стекло'   => 'Да',
                    'Тип'      => 'Топочная',
                    'Материал' => 'Чугун',
                ],
            ],
            [
                'slug'  => 'fireway-dverza-k501-topochnaya-zasteklennaya',
                'name'  => 'Дверца К-501 топочная застеклённая 410×410 мм Fireway',
                'rrc'   => 689.00,
                'opt'   => 518.00,
                'img'   => self::I_K505,  // placeholder — тот же размер 410×410
                'short' => 'Чугунная топочная дверца К-501 с жаростойким стеклом, размер 410×410 мм.',
                'specs' => [
                    'Артикул'  => 'К-501',
                    'Размер'   => '410×410 мм',
                    'Стекло'   => 'Да',
                    'Тип'      => 'Топочная',
                    'Материал' => 'Чугун',
                ],
            ],

            // ── K-серия — топочные без стекла ───────────────────────────
            [
                'slug'  => 'fireway-dverza-k413-topochnaya',
                'name'  => 'Дверца K413 топочная 250×350 мм Fireway',
                'rrc'   => 299.00,
                'opt'   => 225.00,
                'img'   => self::I_K403,  // placeholder — тот же типоразмер
                'short' => 'Чугунная топочная дверца K413 без стекла, размер 250×350 мм.',
                'specs' => [
                    'Артикул'  => 'K413',
                    'Размер'   => '250×350 мм',
                    'Стекло'   => 'Нет',
                    'Тип'      => 'Топочная',
                    'Материал' => 'Чугун',
                ],
            ],
            [
                'slug'  => 'fireway-dverza-k313-topochnaya',
                'name'  => 'Дверца K313 топочная 250×350 мм Fireway',
                'rrc'   => 247.00,
                'opt'   => 186.00,
                'img'   => self::I_K403,  // placeholder
                'short' => 'Чугунная топочная дверца K313 без стекла, размер 250×350 мм.',
                'specs' => [
                    'Артикул'  => 'K313',
                    'Размер'   => '250×350 мм',
                    'Стекло'   => 'Нет',
                    'Тип'      => 'Топочная',
                    'Материал' => 'Чугун',
                ],
            ],
            [
                'slug'  => 'fireway-dverza-k411-topochnaya',
                'name'  => 'Дверца K-411 топочная 250×240 мм Fireway',
                'rrc'   => 256.00,
                'opt'   => 192.00,
                'img'   => self::I_K403,  // placeholder
                'short' => 'Чугунная топочная дверца K-411 без стекла, размер 250×240 мм.',
                'specs' => [
                    'Артикул'  => 'K-411',
                    'Размер'   => '250×240 мм',
                    'Стекло'   => 'Нет',
                    'Тип'      => 'Топочная',
                    'Материал' => 'Чугун',
                ],
            ],

            // ── Крепёж и фурнитура ───────────────────────────────────────
            [
                'slug'  => 'fireway-nabor-krepezha-k4',
                'name'  => 'Набор крепёжных элементов для дверок К4 Fireway',
                'rrc'   => 6.00,
                'opt'   => 5.00,
                'img'   => self::I_A101,
                'short' => 'Комплект крепёжных элементов для установки дверок серии К4 Fireway.',
                'specs' => [
                    'Тип'        => 'Крепёж',
                    'Применение' => 'Дверки серии К4',
                ],
            ],
            [
                'slug'  => 'fireway-a101-nabor-furnitury',
                'name'  => 'A101 набор фурнитуры Fireway',
                'rrc'   => 163.00,
                'opt'   => 122.00,
                'img'   => self::I_A101,
                'short' => 'Набор монтажной фурнитуры A101 для каминного и печного литья Fireway.',
                'specs' => [
                    'Артикул' => 'A101',
                    'Тип'     => 'Фурнитура',
                ],
            ],

            // ── P-серия — прочистные дверцы ──────────────────────────────
            [
                'slug'  => 'fireway-dverza-p104-prochistnaya',
                'name'  => 'Дверца P104 прочистная 130×130 мм Fireway',
                'rrc'   => 71.00,
                'opt'   => 53.00,
                'img'   => self::I_PR01,
                'short' => 'Чугунная прочистная дверца P104, размер 130×130 мм.',
                'specs' => [
                    'Артикул'  => 'P104',
                    'Размер'   => '130×130 мм',
                    'Тип'      => 'Прочистная',
                    'Материал' => 'Чугун',
                ],
            ],
            [
                'slug'  => 'fireway-dverza-p105-prochistnaya',
                'name'  => 'Дверца P105 прочистная 130×130 мм Fireway',
                'rrc'   => 71.00,
                'opt'   => 53.00,
                'img'   => self::I_PR01,
                'short' => 'Чугунная прочистная дверца P105, размер 130×130 мм.',
                'specs' => [
                    'Артикул'  => 'P105',
                    'Размер'   => '130×130 мм',
                    'Тип'      => 'Прочистная',
                    'Материал' => 'Чугун',
                ],
            ],
            [
                'slug'  => 'fireway-dverza-p106-prochistnaya',
                'name'  => 'Дверца P106 прочистная 130×65 мм Fireway',
                'rrc'   => 42.00,
                'opt'   => 32.00,
                'img'   => self::I_PR01,
                'short' => 'Чугунная прочистная дверца P106, размер 130×65 мм.',
                'specs' => [
                    'Артикул'  => 'P106',
                    'Размер'   => '130×65 мм',
                    'Тип'      => 'Прочистная',
                    'Материал' => 'Чугун',
                ],
            ],

            // ── B-серия — поддувальные двери ─────────────────────────────
            [
                'slug'  => 'fireway-dver-podduvalnaya-b104',
                'name'  => 'Дверь поддувальная B-104 Fireway',
                'rrc'   => 173.00,
                'opt'   => 130.00,
                'img'   => self::I_B104,
                'short' => 'Чугунная поддувальная дверь B-104 для регулировки подачи воздуха.',
                'specs' => [
                    'Артикул'  => 'B-104',
                    'Тип'      => 'Поддувальная',
                    'Материал' => 'Чугун',
                ],
            ],
            [
                'slug'  => 'fireway-dver-podduvalnaya-b103',
                'name'  => 'Дверь поддувальная B-103 Fireway',
                'rrc'   => 151.00,
                'opt'   => 114.00,
                'img'   => self::I_B103,
                'short' => 'Чугунная поддувальная дверь B-103.',
                'specs' => [
                    'Артикул'  => 'B-103',
                    'Тип'      => 'Поддувальная',
                    'Материал' => 'Чугун',
                ],
            ],
            [
                'slug'  => 'fireway-dver-podduvalnaya-b107',
                'name'  => 'Дверь поддувальная B-107 Fireway',
                'rrc'   => 82.00,
                'opt'   => 62.00,
                'img'   => self::I_B103,  // placeholder — та же серия
                'short' => 'Чугунная поддувальная дверь B-107 малого размера.',
                'specs' => [
                    'Артикул'  => 'B-107',
                    'Тип'      => 'Поддувальная',
                    'Материал' => 'Чугун',
                ],
            ],

            // ── Плиты ────────────────────────────────────────────────────
            [
                'slug'  => 'fireway-plita-t201-dve-konforki',
                'name'  => 'Плита Т201 с двумя конфорками Fireway',
                'rrc'   => 499.00,
                'opt'   => 384.00,
                'img'   => self::I_T201,
                'short' => 'Чугунная варочная плита Т201 с двумя конфорками для установки на печь.',
                'specs' => [
                    'Артикул'  => 'Т201',
                    'Конфорки' => '2',
                    'Тип'      => 'Плита чугунная',
                    'Материал' => 'Чугун',
                ],
            ],
            [
                'slug'  => 'fireway-plita-t202-dve-konforki',
                'name'  => 'Плита Т202 с двумя конфорками Fireway',
                'rrc'   => 398.00,
                'opt'   => 300.00,
                'img'   => self::I_T202,
                'short' => 'Чугунная варочная плита Т202 облегчённой конструкции с двумя конфорками.',
                'specs' => [
                    'Артикул'  => 'Т202',
                    'Конфорки' => '2',
                    'Тип'      => 'Плита чугунная',
                    'Материал' => 'Чугун',
                ],
            ],

            // ── Колосники ────────────────────────────────────────────────
            [
                'slug'  => 'fireway-kolosnik-bhb-r103',
                'name'  => 'Колосник BHB-R103 350×200 мм Fireway',
                'rrc'   => 157.00,
                'opt'   => 118.00,
                'img'   => self::I_R103,
                'short' => 'Чугунный колосник BHB-R103, размер 350×200 мм. Для дровяных печей и каминов.',
                'specs' => [
                    'Артикул'  => 'BHB-R103',
                    'Размер'   => '350×200 мм',
                    'Тип'      => 'Колосник',
                    'Материал' => 'Чугун',
                ],
            ],
            [
                'slug'  => 'fireway-kolosnik-bhb-r104',
                'name'  => 'Колосник BHB-R104 380×250 мм Fireway',
                'rrc'   => 142.00,
                'opt'   => 106.00,
                'img'   => self::I_R104,
                'short' => 'Чугунный колосник BHB-R104, размер 380×250 мм. Для дровяных печей и каминов.',
                'specs' => [
                    'Артикул'  => 'BHB-R104',
                    'Размер'   => '380×250 мм',
                    'Тип'      => 'Колосник',
                    'Материал' => 'Чугун',
                ],
            ],

            // ── Задвижки ─────────────────────────────────────────────────
            [
                'slug'  => 'fireway-zadvizhka-bhb-z102',
                'name'  => 'Задвижка дымохода BHB-Z102 240×130 мм Fireway',
                'rrc'   => 91.00,
                'opt'   => 68.00,
                'img'   => self::I_Z102,
                'short' => 'Чугунная задвижка дымохода BHB-Z102, размер 240×130 мм.',
                'specs' => [
                    'Артикул'  => 'BHB-Z102',
                    'Размер'   => '240×130 мм',
                    'Тип'      => 'Задвижка',
                    'Материал' => 'Чугун',
                ],
            ],
            [
                'slug'  => 'fireway-zadvizhka-z104',
                'name'  => 'Задвижка Z-104 Fireway',
                'rrc'   => 99.00,
                'opt'   => 75.00,
                'img'   => self::I_Z104,
                'short' => 'Чугунная задвижка дымохода Z-104.',
                'specs' => [
                    'Артикул'  => 'Z-104',
                    'Тип'      => 'Задвижка',
                    'Материал' => 'Чугун',
                ],
            ],
            [
                'slug'  => 'fireway-zadvizhka-z105',
                'name'  => 'Задвижка Z-105 Fireway',
                'rrc'   => 113.00,
                'opt'   => 85.00,
                'img'   => self::I_Z105,
                'short' => 'Чугунная задвижка дымохода Z-105.',
                'specs' => [
                    'Артикул'  => 'Z-105',
                    'Тип'      => 'Задвижка',
                    'Материал' => 'Чугун',
                ],
            ],
            [
                'slug'  => 'fireway-zadvizhka-z106',
                'name'  => 'Задвижка Z-106 Fireway',
                'rrc'   => 113.00,
                'opt'   => 85.00,
                'img'   => self::I_Z106,
                'short' => 'Чугунная задвижка дымохода Z-106.',
                'specs' => [
                    'Артикул'  => 'Z-106',
                    'Тип'      => 'Задвижка',
                    'Материал' => 'Чугун',
                ],
            ],
            [
                'slug'  => 'fireway-zadvizhka-z107',
                'name'  => 'Задвижка Z-107 Fireway',
                'rrc'   => 142.00,
                'opt'   => 107.00,
                'img'   => self::I_Z107,
                'short' => 'Чугунная задвижка дымохода Z-107.',
                'specs' => [
                    'Артикул'  => 'Z-107',
                    'Тип'      => 'Задвижка',
                    'Материал' => 'Чугун',
                ],
            ],
            [
                'slug'  => 'fireway-zadvizhka-z108',
                'name'  => 'Задвижка Z-108 Fireway',
                'rrc'   => 143.00,
                'opt'   => 107.00,
                'img'   => self::I_Z108,
                'short' => 'Чугунная задвижка дымохода Z-108.',
                'specs' => [
                    'Артикул'  => 'Z-108',
                    'Тип'      => 'Задвижка',
                    'Материал' => 'Чугун',
                ],
            ],

            // ── Духовые шкафы ─────────────────────────────────────────────
            [
                'slug'  => 'fireway-shkaf-duhovoj-ds-k201',
                'name'  => 'Шкаф духовой DS-K201 260×288×432 мм Fireway',
                'rrc'   => 372.00,
                'opt'   => 280.00,
                'img'   => self::I_A101,  // placeholder — фото не найдено
                'short' => 'Чугунный духовой шкаф DS-K201, размер 260×288×432 мм, с эмалированным покрытием.',
                'specs' => [
                    'Артикул'  => 'DS-K201',
                    'Размер'   => '260×288×432 мм',
                    'Покрытие' => 'Эмаль',
                    'Тип'      => 'Духовой шкаф',
                    'Материал' => 'Чугун',
                ],
            ],
            [
                'slug'  => 'fireway-shkaf-duhovoj-ds-k211',
                'name'  => 'Шкаф духовой DS-K211 260×288×432 мм Fireway',
                'rrc'   => 279.00,
                'opt'   => 210.00,
                'img'   => self::I_A101,  // placeholder
                'short' => 'Чугунный духовой шкаф DS-K211, размер 260×288×432 мм.',
                'specs' => [
                    'Артикул'  => 'DS-K211',
                    'Размер'   => '260×288×432 мм',
                    'Тип'      => 'Духовой шкаф',
                    'Материал' => 'Чугун',
                ],
            ],
            [
                'slug'  => 'fireway-shkaf-duhovoj-ds-k202',
                'name'  => 'Шкаф духовой DS-K202 382×308×432 мм Fireway',
                'rrc'   => 505.00,
                'opt'   => 379.00,
                'img'   => self::I_A101,  // placeholder
                'short' => 'Чугунный духовой шкаф DS-K202 увеличенного размера 382×308×432 мм, с эмалированным покрытием.',
                'specs' => [
                    'Артикул'  => 'DS-K202',
                    'Размер'   => '382×308×432 мм',
                    'Покрытие' => 'Эмаль',
                    'Тип'      => 'Духовой шкаф',
                    'Материал' => 'Чугун',
                ],
            ],
            [
                'slug'  => 'fireway-shkaf-duhovoj-ds-k212',
                'name'  => 'Шкаф духовой DS-K212 382×308×432 мм Fireway',
                'rrc'   => 416.00,
                'opt'   => 313.00,
                'img'   => self::I_A101,  // placeholder
                'short' => 'Чугунный духовой шкаф DS-K212 увеличенного размера 382×308×432 мм.',
                'specs' => [
                    'Артикул'  => 'DS-K212',
                    'Размер'   => '382×308×432 мм',
                    'Тип'      => 'Духовой шкаф',
                    'Материал' => 'Чугун',
                ],
            ],

            // ═══════════════════════════════════════════════════════════════
            // ПРОЧЕЕ (11 новых товаров)
            // ═══════════════════════════════════════════════════════════════

            // ── Светильники для бани ──────────────────────────────────────
            [
                'slug'  => 'fireway-svetilnik-mayak-1',
                'name'  => 'Лампа для бани Маяк-1 Fireway',
                'rrc'   => 54.00,
                'opt'   => 36.00,
                'img'   => self::I_MAYAK1,
                'short' => 'Термостойкий светильник для бани и сауны Маяк-1 от Fireway. Подходит для парной.',
                'specs' => [
                    'Серия'      => 'Маяк',
                    'Модель'     => 'Маяк-1',
                    'Применение' => 'Баня/сауна',
                ],
            ],
            [
                'slug'  => 'fireway-svetilnik-mayak-2',
                'name'  => 'Лампа для бани Маяк-2 Fireway',
                'rrc'   => 72.00,
                'opt'   => 48.00,
                'img'   => self::I_MAYAK2,
                'short' => 'Термостойкий светильник для бани и сауны Маяк-2 от Fireway. Подходит для парной.',
                'specs' => [
                    'Серия'      => 'Маяк',
                    'Модель'     => 'Маяк-2',
                    'Применение' => 'Баня/сауна',
                ],
            ],
            [
                'slug'  => 'fireway-svetilnik-shajba',
                'name'  => 'Лампа для бани Шайба Fireway',
                'rrc'   => 74.00,
                'opt'   => 49.00,
                'img'   => self::I_SHAJBA,
                'short' => 'Термостойкий светильник Шайба для бани и сауны от Fireway. Компактная круглая форма.',
                'specs' => [
                    'Модель'     => 'Шайба',
                    'Применение' => 'Баня/сауна',
                ],
            ],
            [
                'slug'  => 'fireway-svetilnik-linder',
                'name'  => 'Лампа для бани Линдер Fireway',
                'rrc'   => 33.00,
                'opt'   => 22.00,
                'img'   => self::I_LINDER,
                'short' => 'Термостойкий светильник Линдер для бани от Fireway. Простая надёжная конструкция.',
                'specs' => [
                    'Модель'     => 'Линдер',
                    'Применение' => 'Баня/сауна',
                ],
            ],
            [
                'slug'  => 'fireway-svetilnik-mayak-mini',
                'name'  => 'Лампа для бани Маяк Мини Fireway',
                'rrc'   => 54.00,
                'opt'   => 36.00,
                'img'   => self::I_MAYAKM,
                'short' => 'Термостойкий светильник Маяк Мини для бани от Fireway. Компактная версия серии Маяк.',
                'specs' => [
                    'Серия'      => 'Маяк',
                    'Модель'     => 'Маяк Мини',
                    'Применение' => 'Баня/сауна',
                ],
            ],
            [
                'slug'  => 'fireway-svetilnik-oblik',
                'name'  => 'Лампа для бани Облик Fireway',
                'rrc'   => 38.00,
                'opt'   => 25.00,
                'img'   => self::I_OBLIK,
                'short' => 'Термостойкий светильник Облик для бани от Fireway. Эстетичный современный дизайн.',
                'specs' => [
                    'Модель'     => 'Облик',
                    'Применение' => 'Баня/сауна',
                ],
            ],

            // ── Master Flash — проходные элементы ────────────────────────
            [
                'slug'  => 'fireway-master-flash-1',
                'name'  => 'Master Flash №1 (75-200 мм) Fireway',
                'rrc'   => 41.00,
                'opt'   => 31.00,
                'img'   => self::I_A101,  // placeholder
                'short' => 'Проходной элемент Master Flash №1 для монтажа дымовой трубы через кровлю, диаметр 75–200 мм.',
                'specs' => [
                    'Модель'  => 'Master Flash №1',
                    'Диаметр' => '75–200 мм',
                    'Тип'     => 'Проходной элемент',
                ],
            ],
            [
                'slug'  => 'fireway-master-flash-2',
                'name'  => 'Master Flash №2 (175-275 мм) Fireway',
                'rrc'   => 60.00,
                'opt'   => 45.00,
                'img'   => self::I_A101,  // placeholder
                'short' => 'Проходной элемент Master Flash №2 для монтажа дымовой трубы через кровлю, диаметр 175–275 мм.',
                'specs' => [
                    'Модель'  => 'Master Flash №2',
                    'Диаметр' => '175–275 мм',
                    'Тип'     => 'Проходной элемент',
                ],
            ],
            [
                'slug'  => 'fireway-master-flash-3',
                'name'  => 'Master Flash №3 (254-467 мм) Fireway',
                'rrc'   => 144.00,
                'opt'   => 108.00,
                'img'   => self::I_A101,  // placeholder
                'short' => 'Проходной элемент Master Flash №3 для монтажа дымовой трубы через кровлю, диаметр 254–467 мм.',
                'specs' => [
                    'Модель'  => 'Master Flash №3',
                    'Диаметр' => '254–467 мм',
                    'Тип'     => 'Проходной элемент',
                ],
            ],
            [
                'slug'  => 'fireway-master-flash-8-pryamoj',
                'name'  => 'Master Flash №8 прямой (178-330 мм) Fireway',
                'rrc'   => 56.00,
                'opt'   => 42.00,
                'img'   => self::I_A101,  // placeholder
                'short' => 'Прямой проходной элемент Master Flash №8 для монтажа дымовой трубы через кровлю, диаметр 178–330 мм.',
                'specs' => [
                    'Модель'  => 'Master Flash №8',
                    'Диаметр' => '178–330 мм',
                    'Тип'     => 'Проходной элемент (прямой)',
                ],
            ],

            // ── Электрика ─────────────────────────────────────────────────
            [
                'slug'  => 'fireway-vtulka-izolyator-keramicheskaya',
                'name'  => 'Втулка-изолятор керамическая дистанционная 30×12 мм Fireway',
                'rrc'   => 12.00,
                'opt'   => 6.00,
                'img'   => self::I_A101,  // placeholder
                'short' => 'Дистанционная керамическая втулка-изолятор 30×12 мм для монтажа светильников в бане.',
                'specs' => [
                    'Размер'   => '30×12 мм',
                    'Материал' => 'Керамика',
                    'Тип'      => 'Втулка-изолятор',
                ],
            ],

        ]; // end products()
    }
};
