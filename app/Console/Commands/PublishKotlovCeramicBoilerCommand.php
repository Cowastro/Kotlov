<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\Product;
use App\Support\Catalog\Kotlov95SeoContent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PublishKotlovCeramicBoilerCommand extends Command
{
    protected $signature = 'catalog:publish-kotlov-ceramic-boiler {--apply : Publish the confirmed boiler package}';

    protected $description = 'Publish the KOTLOV 95 kW Ceramic PRO package, without changing burner prices or 1C.';

    public const SLUG = 'pelletnyy-kotel-kotlov-xo-ceramic-pro-95-kvt-bunker-1500';

    public const SKU = 'KOTLOV-XO-95-CP-1500';

    public const PHOTO_NOTE = '<p data-kotlov-95-photos="2026-10-16"><small>В галерее — реальные фотографии базового котла с нижним зольником и актуальным красным бункером производителя. Горелка и автоматика на этих снимках отличаются от предлагаемого комплекта: поставка KOTLOV включает горелку XO Ceramic PRO и контроллер XO с Wi-Fi, показанные на отдельных фотографиях. Конструкцию на исходных снимках не дорисовывали. Окончательное исполнение и расположение узлов согласуются при заказе.</small></p>';

    public static function packageImages(): array
    {
        return [
            '/img/products/kotlov-xo-boiler-95/installation-current-wide-original.jpg',
            '/img/products/kotlov-xo-boiler-95/installation-current-side-original.jpg',
            '/img/products/kotlov-xo-boiler-95/installation-current-opposite-original.jpg',
            '/img/products/kotlov-xo-boiler-95/installation-current-front-original.jpg',
            '/img/products/kotlov-xo-boiler-95/installation-current-detail-original.jpg',
            '/img/products/kotlov-xo-boiler-95/ceramic-pro-100.jpg',
            '/img/promotions/kotlov-xo-ceramic-pro-real-firebox.jpg',
            '/img/promotions/kotlov-xo-ceramic-pro-real-controller.jpg',
            '/img/products/kotlov-xo-boiler-95/base-boiler-cutaway.png',
        ];
    }

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
        $images = self::packageImages();
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
            'meta_title' => 'Пеллетный котёл KOTLOV 95 кВт — Ceramic PRO, Wi-Fi, бункер 1500 л',
            'meta_description' => 'Купить пеллетный котёл KOTLOV 95 кВт с Ceramic PRO, Wi-Fi и бункером 1,5 м³ за 25 600 BYN с НДС. Разрез, характеристики, требования к дымоходу. Доставка по Беларуси.',
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
        return self::PHOTO_NOTE."\n".<<<'HTML'
<h2>Пеллетный котёл KOTLOV 95 кВт с горелкой XO Ceramic PRO</h2>
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
<h3>Котёл в разрезе: топка, теплообменные поверхности и газоходы</h3>
<figure><a href="/img/products/kotlov-xo-boiler-95/base-boiler-cutaway.png" target="_blank" rel="noopener"><img src="/img/products/kotlov-xo-boiler-95/base-boiler-cutaway.png" alt="Разрез базового стального котла КСТБ: топка, радиационные трубы, перегородки, дымоход, бункер и шнек" width="1058" height="580" loading="lazy" style="max-width:100%;height:auto"></a><figcaption>Разрез базовой конструкции котла с обозначениями узлов. Нажмите на схему для увеличения. Изображение поясняет устройство корпуса, но не заменяет монтажный чертёж комплекта с горелкой XO.</figcaption></figure>
<p>На разрезе обозначены <strong>топка (4)</strong>, <strong>радиационные трубы (6)</strong>, перегородки (5 и 11), поворотная камера (19) и выход в дымоход (16). Перегородки формируют газовый тракт: продукты сгорания передают тепло поверхностям котла перед выходом в дымоход. Изоляция (9) расположена под наружной обшивкой (8).</p>
<p>Нижний патрубок обратки обозначен цифрой 2, верхний патрубок — 13. Для обслуживания предусмотрены верхняя и нижняя дверцы (7 и 3), зольник (18), лючок зольника (17) и дверца зольного ящика (22). На схеме также показаны бункер (26), шнековая подача (27) и место установки горелки (25). В предлагаемом комплекте используется именно KOTLOV XO Ceramic PRO, а не горелка и контроллер другого исполнения из базовой документации.</p>
<h3>Температура дымовых газов: что означает «не менее 140 °C»</h3>
<p>Для базового котла в техническом предложении указана <strong>температура уходящих газов не менее 140 °C</strong>. Это нижний предел данного параметра, а не обещание постоянной температуры 140 °C, не верхняя граница и не температура пламени в топке. Фактическое значение зависит от нагрузки, топлива, настройки горелки и состояния теплообменных поверхностей.</p>
<p>В каталоге горелки отдельно приведён справочный <strong>расход дымовых газов до 335 м³/ч при 200 °C</strong>. Здесь 200 °C — условие, при котором указан объёмный расход; эту цифру нельзя подменять температурой уходящих газов котла или считать его максимальной допустимой температурой. Параметры котла и горелки относятся к разным узлам и разным условиям.</p>
<p>При подборе дымохода учитывают фактический газовый поток, температуру и требуемую тягу. Диаметр и высоту дымохода определяют для конкретной котельной; их нельзя назначить только по мощности горелки или одной температуре 140 °C. Рабочий режим проверяют при пусконаладке по паспорту поставленного оборудования. КПД сгорания горелки не равен КПД котла или всей котельной.</p>
<h3>Технические параметры корпуса и горелки</h3>
<p>Для базового котла указаны рабочее давление воды <strong>не более 0,25 МПа (2,5 бар)</strong> и минимальная температура воды <strong>55 °C</strong>. Это эксплуатационные параметры корпуса; они не являются уставками интернет-контроллера. Окончательные ограничения проверяются по паспорту поставленного котла.</p>
<p>Параметры <strong>отдельной горелки Ceramic PRO 100</strong>: средняя электрическая мощность 75 Вт, при розжиге — 475 Вт, масса — 71 кг. Габариты горелки Д × В × Ш — 877 × 532 × 430 мм, размер топочной части В × Ш — 268 × 288 мм. Эти размеры не являются габаритами котла или всей установки. Каталожный параметр горелки не заменяет расчёт общей электрической нагрузки котельной: при проектировании учитывают все потребители, включая шнек, насосы и дополнительное оборудование.</p>
<section class="product-description-faq" aria-labelledby="kotlov-boiler-faq-title">
<header><p class="product-description-faq__eyebrow">Комплектация и эксплуатация</p><h3 id="kotlov-boiler-faq-title">Частые вопросы</h3><p class="product-description-faq__intro">Коротко о том, что важно перед заказом котла.</p></header>
<details name="kotlov-boiler-faq" open><summary>Какую площадь может отапливать котёл?</summary><div class="product-description-faq__answer"><p>Оборудование выбирают по расчётной тепловой нагрузке здания, температурному графику и потребности в горячей воде. Одна и та же площадь склада, утеплённого цеха и теплицы требует разной мощности, поэтому подбор только по квадратным метрам некорректен.</p></div></details>
<details name="kotlov-boiler-faq"><summary>Нужен ли отдельный модуль для управления через интернет?</summary><div class="product-description-faq__answer"><p>Нет. Контроллер XO со встроенным Wi-Fi входит в комплект за 25 600 BYN с НДС. Для удалённого доступа в котельной необходима сеть Wi-Fi с выходом в интернет.</p></div></details>
<details name="kotlov-boiler-faq"><summary>Входит ли бункер в цену?</summary><div class="product-description-faq__answer"><p>Да. Комплект включает бункер объёмом 1,5 м³ (1500 л) и шнековую подачу. Масса запаса топлива зависит от насыпной плотности пеллет; литры бункера не равны килограммам топлива.</p></div></details>
<details name="kotlov-boiler-faq"><summary>Нужно ли чистить котёл при автоматической очистке горелки?</summary><div class="product-description-faq__answer"><p>Да. Подвижные колосники очищают рабочую поверхность горелки. Теплообменные поверхности котла, газоходы и зольник обслуживаются отдельно; периодичность зависит от топлива, нагрузки и фактического загрязнения.</p></div></details>
</section>
<h3>Подбор и поставка</h3>
<p>Поставка под заказ. Срок изготовления и доставки уточняется при оформлении. Перед заказом проверяем тепловую нагрузку, дымоход, тягу, размеры котельной и место для бункера со шнеком. Монтаж, дымоход, обвязка и пусконаладка рассчитываются отдельно и в указанную стоимость оборудования не включены.</p>
<p>Гарантия на котёл — 30 месяцев; на горелку KOTLOV XO Ceramic PRO — 24 месяца при соблюдении условий эксплуатации и гарантийного обслуживания.</p>
<p><a href="/docs/kotlov-xo-controller-1-2os-ru.pdf" target="_blank" rel="noopener">Скачать инструкцию контроллера XO 1.0S/2.0S (PDF)</a>. <a href="/akcii/kotlov-xo-ceramic-pro">Подробнее о горелке KOTLOV XO Ceramic PRO</a>.</p>
HTML."\n".Kotlov95SeoContent::productBlock();
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
            'Рабочее давление воды базового котла' => 'не более 0,25 МПа / 2,5 бар',
            'Минимальная температура воды базового котла' => '55 °C',
            'Температура уходящих газов базового котла' => 'не менее 140 °C; нижний предел, не максимум',
            'Справочный расход дымовых газов горелки' => 'до 335 м³/ч при 200 °C (условие указания расхода)',
            'Средняя электрическая мощность горелки' => '75 Вт; параметр горелки, не всей котельной',
            'Электрическая мощность горелки при розжиге' => '475 Вт',
            'Масса отдельной горелки' => '71 кг',
            'Габариты горелки Д × В × Ш' => '877 × 532 × 430 мм; не размеры котла',
            'Размер топочной части горелки В × Ш' => '268 × 288 мм',
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
