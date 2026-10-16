<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PublishKotlovCeramicBoilerCommand extends Command
{
    protected $signature = 'catalog:publish-kotlov-ceramic-boiler {--apply : Publish the confirmed boiler package}';

    protected $description = 'Publish the KOTLOV 95 kW Ceramic PRO package, without changing burner prices or 1C.';

    public const SLUG = 'pelletnyy-kotel-kotlov-xo-ceramic-pro-95-kvt-bunker-1500';

    public const SKU = 'KOTLOV-XO-95-CP-1500';

    public function handle(): int
    {
        $burner = Product::query()->with('brand')->where('slug', 'pelletnaya-gorelka-kotlov-xo-ceramic-pro-100-kvt')->first();
        $category = Category::query()->where('slug', 'kotly-na-pelletah')->where('is_active', true)->first();
        if (! $burner?->brand || ! str_contains(mb_strtoupper($burner->brand->name), 'KOTLOV') || ! $category) {
            $this->error('The existing KOTLOV brand and active pellet-boiler category are required.');

            return self::FAILURE;
        }
        $matches = Product::query()->where('slug', self::SLUG)->orWhere('sku', self::SKU)->get();
        if ($matches->count() > 1 || ($matches->isNotEmpty() && ($matches->first()->sku !== self::SKU || $matches->first()->slug !== self::SLUG))) {
            $this->error('Product identity conflict: no existing product will be overwritten.');

            return self::FAILURE;
        }
        $product = $matches->first();
        if ($product && ($product->brand_id !== $burner->brand_id || IntegrationProduct::query()->where('product_id', $product->id)->exists())) {
            $this->error('Unexpected brand or 1C link: manual review required.');

            return self::FAILURE;
        }
        $images = [
            '/img/products/kotlov-xo-boiler-95/kit-visualization-v5.png',
            '/img/products/kotlov-xo-boiler-95/ceramic-pro-100.jpg',
            '/img/promotions/kotlov-xo-ceramic-pro-real-firebox.jpg',
            '/img/promotions/kotlov-xo-ceramic-pro-real-controller.jpg',
        ];
        foreach ($images as $image) {
            if (! is_file(public_path(ltrim($image, '/')))) {
                $this->error('Missing package image: '.$image);

                return self::FAILURE;
            }
        }
        $name = 'Пеллетный котёл KOTLOV XO Ceramic PRO 95 кВт с бункером 1,5 м³';
        $data = [
            'category_id' => $category->id, 'brand_id' => $burner->brand_id,
            'sku' => self::SKU, 'slug' => self::SLUG, 'name' => $name, 'h1' => $name,
            // User-confirmed retail on 2026-10-16; not the 25,650 BYN alternative in the source DOC.
            'price' => 25600, 'price_old' => null, 'currency' => 'BYN', 'requires_price_confirmation' => false,
            'is_active' => true, 'is_archived' => false, 'is_sale' => false, 'is_new' => true,
            'short_description' => 'Стальной водогрейный котёл 95 кВт с горелкой KOTLOV XO Ceramic PRO 100, автоматической подачей пеллет и бункером 1,5 м³. Интернет-управление и Wi-Fi уже в комплекте — отдельный модуль не нужен. Цена — 25 600 BYN с НДС. Под заказ.',
            'content' => $this->content(), 'images' => $images,
            'specs' => $this->specs(), 'unit' => 'компл.',
            'documents' => [['label' => 'Инструкция контроллера XO 1.0S/2.0S (PDF)', 'url' => '/docs/kotlov-xo-controller-1-2os-ru.pdf']],
            'content_source_url' => 'https://pkmercury.by/product/kotel-kstb-pelletnyj-drevesnyj-i-agro-pellet/',
            'meta_title' => 'Пеллетный котёл KOTLOV XO Ceramic PRO 95 кВт, бункер 1,5 м³ — купить',
            'meta_description' => 'Котёл KOTLOV 95 кВт с горелкой XO Ceramic PRO 100, Wi-Fi, автоматическим розжигом и бункером 1,5 м³. Цена комплекта 25 600 BYN с НДС. Подбор и заказ.',
        ];
        // Never manufacture a stock balance; preserve subsequent warehouse updates on a rerun.
        if (! $product) {
            $data += ['in_stock' => false, 'stock_qty' => 0, 'availability_status' => Product::AVAILABILITY_CHECK];
        }
        $this->info('Mode: '.($this->option('apply') ? 'APPLY' : 'DRY RUN'));
        $this->line('planned_products=1 retail_byn=25600 hopper_litres=1500 boiler_kw=95 burner_max_kw=100');
        if ($this->option('apply')) {
            DB::transaction(function () use ($product, $data): void {
                $target = $product ?? new Product;
                $target->fill($data);
                if ($target->isDirty()) {
                    $target->save();
                }
            });
            $this->info('published_products=1');
        }

        return self::SUCCESS;
    }

    private function content(): string
    {
        return <<<'HTML'
<h2>Автоматизированное пеллетное отопление KOTLOV</h2>
<p><strong>KOTLOV XO Ceramic PRO 95 кВт</strong> — комплект для водяного отопления коммерческих, производственных и складских помещений: стальной котёл, пеллетная горелка <a href="/pelletnye-gorelki/pelletnaya-gorelka-kotlov-xo-ceramic-pro-100-kvt">KOTLOV XO Ceramic PRO 100</a>, система подачи топлива и вместительный бункер <strong>1,5 м³ (1500 л)</strong>. Цена всего комплекта — <strong>25 600 BYN с НДС</strong>.</p>
<h3>Что входит в комплект</h3>
<ul><li>Стальной водогрейный котёл номинальной мощностью 95 кВт на базе КСТБ-95.</li><li>Горелка KOTLOV XO Ceramic PRO 100 с шамотированной камерой сгорания и каскадной очисткой подвижных колосников.</li><li>Современный контроллер XO со встроенным Wi-Fi для контроля и управления через интернет.</li><li>Бункер для пеллет объёмом 1,5 м³ и шнековая система подачи топлива.</li></ul>
<h3>Горелка Ceramic PRO и автоматика XO</h3>
<p>Горелка автоматически выполняет розжиг, дозированную подачу пеллет, регулирование воздуха и очистку рабочей поверхности. Подвижные колосники изготовлены из жаропрочной стали AISI 310S. Два керамических розжигателя обеспечивают автоматический запуск. Съёмная топка позволяет обслуживать и заменять теплонагруженный узел отдельно от всей горелки.</p>
<p>Номинальная мощность котла — <strong>95 кВт</strong>. Диапазон самой горелки — 30–100 кВт; при пусконаладке её максимальную подачу ограничивают в соответствии с допустимой нагрузкой котла. Мощность горелки 100 кВт не означает, что номинальная мощность котла увеличивается до 100 кВт.</p>
<p>Топливо — древесные пеллеты диаметром 6–8 мм. Для этого комплекта не заявляется работа на агропеллетах без отдельного согласования. Wi-Fi входит в комплектацию; расширенные функции автоматики зависят от подключённых датчиков, насосов и схемы котельной.</p>
<h3>Интернет-управление уже в комплекте</h3>
<p><strong>Контроллер XO со встроенным Wi-Fi входит в указанную цену. Для управления через интернет не нужно покупать отдельный интернет-модуль.</strong> Для подключения требуется доступная в котельной сеть Wi-Fi с выходом в интернет.</p>
<ul><li><strong>Котельная под контролем онлайн:</strong> просмотр температуры котла, текущего состояния системы и графиков показаний; включение и выключение через интернет.</li><li><strong>Единый автоматический цикл:</strong> согласованное управление розжигом, вентилятором горелки, подачей топлива, очисткой и насосами.</li><li><strong>Настройка под топливо:</strong> регулировка подачи шнека, пауз и воздуха с учётом качества и зольности пеллет.</li><li><strong>Контроль и защита:</strong> работа с датчиками температуры котла, питателя, дымовых газов и пламени, вход аварийной сигнализации.</li></ul>
<p>Конкретная версия XO 1.0S/2.0S и набор подключений определяются схемой комплекта. Возможности датчиков и исполнительных устройств реализуются при их наличии и настройке во время пусконаладки.</p>
<h3>Стальной теплообменник и обслуживание</h3>
<p>Базовый котёл изготовлен из котловой стали с применением цельнотянутых бесшовных труб. Конструкция предусматривает доступ для очистки поверхностей нагрева и удаления золы. Автоматическая очистка горелки не отменяет периодическую ручную очистку теплообменника и зольника.</p>
<h3>Подбор и поставка</h3>
<p>Поставка под заказ. Срок изготовления и доставки уточняется при оформлении. Перед заказом проверяем тепловую нагрузку, дымоход, тягу, размеры котельной и место для бункера со шнеком. Монтаж, дымоход, обвязка и пусконаладка рассчитываются отдельно и в указанную стоимость оборудования не включены.</p>
<p>Гарантия на котёл — 30 месяцев; на горелку KOTLOV XO Ceramic PRO — 24 месяца при соблюдении условий эксплуатации и гарантийного обслуживания.</p>
<p><small>Главное изображение — визуализация компоновки комплекта, не фотография готовой установки. Фотографии горелки, топки и контроллера показывают оборудование серии Ceramic PRO / XO. Исполнение и размещение узлов согласуются при заказе.</small></p>
<p><a href="/docs/kotlov-xo-controller-1-2os-ru.pdf" target="_blank" rel="noopener">Скачать инструкцию контроллера XO 1.0S/2.0S (PDF)</a>. <a href="/akcii/kotlov-xo-ceramic-pro">Подробнее о горелке KOTLOV XO Ceramic PRO</a>.</p>
HTML;
    }

    private function specs(): array
    {
        $rows = [
            'Тип' => 'Пеллетный водогрейный котёл с автоматической подачей',
            'Бренд комплекта' => 'KOTLOV', 'Базовый котёл' => 'КСТБ-95',
            'Номинальная мощность котла' => '95 кВт', 'Горелка' => 'KOTLOV XO Ceramic PRO 100',
            'Диапазон мощности горелки' => '30–100 кВт; настройка по мощности котла',
            'Объём бункера' => '1,5 м³ / 1500 л, входит в комплект',
            'Топливо' => 'Древесные пеллеты Ø 6–8 мм', 'Установка' => 'Напольная',
            'Материал теплообменника' => 'Котловая сталь, бесшовные трубы',
            'Подача топлива' => 'Автоматическая, шнековая', 'Розжиг' => 'Автоматический, два керамических элемента',
            'Очистка горелки' => 'Автоматическая каскадная, подвижные колосники AISI 310S',
            'Камера сгорания горелки' => 'Шамотированная, съёмная топка',
            'Контроллер' => 'XO 1.0S/2.0S, версия по конфигурации комплекта',
            'Интернет-управление' => 'По умолчанию в комплекте; встроенный Wi-Fi, отдельный модуль не нужен',
            'Онлайн-функции' => 'Температура, состояние системы, графики, включение/выключение',
            'Подключение к интернету' => 'Сеть Wi-Fi с доступом в интернет в месте установки',
            'Гарантия на котёл' => '30 месяцев', 'Гарантия на горелку' => '24 месяца',
            'Комплектация' => 'Котёл, горелка, контроллер XO с Wi-Fi, шнек, бункер 1,5 м³',
            'Цена комплекта' => '25 600 BYN с НДС', 'Условия поставки' => 'Под заказ',
        ];

        return collect($rows)->map(fn ($value, $key) => ['key' => $key, 'value' => $value])->values()->all();
    }
}
