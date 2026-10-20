<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PRODUCT_SLUG = 'ecokamin-shaman-chugunnyy-kamin';
    private const ARTICLE_SLUG = 'ecokamin-shaman-analog-legendarnogo-kamina';
    private const BANNER_IMAGE = 'banners/invicta-shaman-launch.svg';

    private const REAL_PHOTOS = [
        'img/products/ecokamin/shaman/ecokamin-shaman-real-front.webp',
        'img/products/ecokamin/shaman/ecokamin-shaman-real-firebox.webp',
        'img/products/ecokamin/shaman/ecokamin-shaman-real-door-handle.webp',
        'img/products/ecokamin/shaman/ecokamin-shaman-real-rear-flue.webp',
    ];

    public function up(): void
    {
        $now = now();
        $product = DB::table('products')->where('slug', self::PRODUCT_SLUG)->first();

        if ($product) {
            $currentImages = json_decode((string) $product->images, true);
            $currentImages = is_array($currentImages) ? $currentImages : [];

            DB::table('products')->where('id', $product->id)->update([
                'short_description' => 'ЭкоКамин ШАМАН — высокий чугунный камин 14 кВт с панорамной дверцей, вторичным дожигом и системой «чистое стекло». Самостоятельная российская модель в узнаваемой стилистике легендарного Invicta Shaman.',
                'content' => $this->productContent(),
                'images' => json_encode(array_values(array_unique([
                    ...self::REAL_PHOTOS,
                    ...$currentImages,
                ])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'meta_title' => 'Камин ЭкоКамин ШАМАН 14 кВт купить в %city% | KOTLOV.BY',
                'meta_description' => 'Купить чугунный камин ЭкоКамин ШАМАН 14 кВт в Беларуси. Реальные фото, КПД 78,7%, обогрев 75–170 м², дымоход 180 мм. Подбор и доставка KOTLOV.BY.',
                'updated_at' => $now,
            ]);
        }

        DB::table('blog_posts')->where('slug', self::ARTICLE_SLUG)->update([
            'title' => 'ЭкоКамин ШАМАН: реальный обзор современной альтернативы Invicta Shaman',
            'excerpt' => 'Подробно показываем реальный ЭкоКамин ШАМАН: чугунную конструкцию, топку, дверцу, верхнее подключение дымохода и отличия самостоятельной российской модели от знаменитого Invicta Shaman.',
            'content' => $this->articleContent(),
            'cover_image' => self::REAL_PHOTOS[0],
            'images' => json_encode(self::REAL_PHOTOS, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'tags' => json_encode([
                'ЭкоКамин',
                'ШАМАН',
                'Invicta Shaman',
                'чугунный камин',
                'камин 14 кВт',
                'печь-камин',
                'камины в Беларуси',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'meta_title' => 'ЭкоКамин ШАМАН 14 кВт: обзор, фото и цена в Беларуси',
            'meta_description' => 'Реальный обзор камина ЭкоКамин ШАМАН 14 кВт: фото конструкции и топки, характеристики, сравнение с идеей Invicta Shaman, цена и заказ в Беларуси.',
            'updated_at' => $now,
        ]);

        DB::table('banners')
            ->where('image', self::BANNER_IMAGE)
            ->where('position', 'hero')
            ->update([
                'title' => 'ЭкоКамин ШАМАН',
                'subtitle' => 'Легендарный силуэт · новая модель',
                'description' => 'В духе знаменитого Shaman — самостоятельный чугунный камин ЭкоКамин. Цена от 5 414,64 BYN.',
                'button_text' => 'Смотреть камин',
                'updated_at' => $now,
            ]);

        Cache::forget('home:data');
    }

    public function down(): void
    {
        // Marketing content and real product photos are intentionally durable.
    }

    private function productContent(): string
    {
        return <<<'HTML'
<p><strong>ЭкоКамин ШАМАН</strong> — самостоятельная модель российского производителя «ЭкоКамин». Её высокий вертикальный силуэт и большое прямое стекло напоминают легендарный французский Invicta Shaman, но это <strong>не изделие Invicta и не ребрендинг</strong>.</p>

<p>Камин рассчитан на дровяное отопление загородного дома, просторной гостиной или помещения с высоким потолком. Номинальная мощность 14 кВт, официальный диапазон обогрева — от 75 до 170 м². Фактическая площадь зависит от утепления, высоты потолков, климата и режима эксплуатации.</p>

<figure><img loading="eager" width="1122" height="1402" src="/img/products/ecokamin/shaman/ecokamin-shaman-real-front.webp" alt="Реальный камин ЭкоКамин ШАМАН — вид спереди"><figcaption>Реальный ЭкоКамин ШАМАН: высокий чугунный корпус и панорамная дверца.</figcaption></figure>

<h3>Конструкция и горение</h3>
<p>Корпус и футеровка топочной камеры выполнены из чугуна. Вторичный дожиг помогает полнее использовать древесные газы, а подача воздуха на стекло уменьшает оседание сажи. Боковое открывание и выразительная литая ручка дают свободный доступ к топке.</p>

<figure><img loading="lazy" width="1085" height="1449" src="/img/products/ecokamin/shaman/ecokamin-shaman-real-firebox.webp" alt="Открытая чугунная топка камина ЭкоКамин ШАМАН"><figcaption>Топочная камера, уплотнение дверцы, колосниковая зона и канал подачи воздуха.</figcaption></figure>

<h3>Основные характеристики</h3>
<ul>
    <li>номинальная мощность — 14 кВт, регулировка от 9,5 до 21 кВт;</li>
    <li>официальная площадь обогрева — от 75 до 170 м²;</li>
    <li>КПД — 78,7%;</li>
    <li>режим длительного горения — до 8 часов;</li>
    <li>системы вторичного дожига и очистки стекла;</li>
    <li>максимальная длина полена — 44 см;</li>
    <li>верхнее подключение дымохода диаметром 180 мм;</li>
    <li>габариты — 560 × 1200 × 578 мм;</li>
    <li>масса — 160 кг, цвет — антрацит;</li>
    <li>гарантия производителя — 5 лет.</li>
</ul>

<p>Первая партия ожидается на складе поставщика в Москве <strong>26–30 октября 2026 года</strong>. Перед заказом специалисты KOTLOV.BY проверят расчётную тепловую нагрузку, параметры дымохода, основание и безопасные расстояния до горючих конструкций.</p>
HTML;
    }

    private function articleContent(): string
    {
        return <<<'HTML'
<p class="text text-body-1"><strong>ЭкоКамин ШАМАН — новая самостоятельная модель российского производителя «ЭкоКамин».</strong> Она продолжает идею высокого чугунного камина с большим вертикальным стеклом, которую многие покупатели знают по французскому Invicta Shaman. При этом перед нами не Invicta, не лицензионная копия и не товар с заменённым шильдиком: у модели собственный производитель, конструкция и гарантия.</p>

<div class="blog-image-2"><img loading="eager" width="1122" height="1402" src="/img/products/ecokamin/shaman/ecokamin-shaman-real-front.webp" alt="ЭкоКамин ШАМАН 14 кВт — реальная фотография готового камина"></div>

<p class="text text-body-1">На этой странице — реальные фотографии первого собранного изделия, а не только заводской рендер. На них хорошо видны качество литья, посадка элементов, устройство топочной камеры, дверная ручка и верхний выход дымохода.</p>

<div class="kotlov-article-note"><strong>Коротко о модели</strong> 14 кВт · 75–170 м² · КПД 78,7% · чугунный корпус и футеровка · дымоход 180 мм · полено до 44 см · масса 160 кг.</div>

<h2>Почему ШАМАН сравнивают с Invicta Shaman</h2>

<p class="text text-body-1">Сходство прежде всего в архитектуре: высокий узкий корпус, вертикальная панорамная дверца, выразительная пластика чугуна и камин как самостоятельный объект интерьера. Именно этот образ сделал Invicta Shaman узнаваемым. ЭкоКамин использует близкую идею и предлагает её в собственной российской модели, рассчитанной на покупателя, которому важны эффектный силуэт, высокая мощность и более доступная цена.</p>

<p class="text text-body-1"><strong>Корректная формулировка:</strong> ЭкоКамин ШАМАН — современная альтернатива в духе Invicta Shaman. Мы специально не называем его французским камином и не приписываем ему производство Invicta.</p>

<h2>Что видно на реальных фотографиях</h2>

<h3>Чугунная топочная камера</h3>
<p class="text text-body-1">При открытой дверце видна глубокая топочная камера с чугунной футеровкой, колосниковой зоной и каналами подачи воздуха. Чугун устойчив к высоким температурам и аккумулирует тепло, но из-за массы 160 кг требует ровного негорючего основания.</p>

<div class="blog-image-2"><img loading="lazy" width="1085" height="1449" src="/img/products/ecokamin/shaman/ecokamin-shaman-real-firebox.webp" alt="Чугунная топка и открытая дверца ЭкоКамин ШАМАН"></div>

<h3>Дверца, уплотнение и литая ручка</h3>
<p class="text text-body-1">Высокая дверца открывается вбок. По периметру расположен термостойкий уплотнительный шнур, а длинная фигурная ручка вынесена от горячей поверхности. Отдельный нижний регулятор управляет подачей воздуха на горение.</p>

<div class="blog-image-2"><img loading="lazy" width="1085" height="1449" src="/img/products/ecokamin/shaman/ecokamin-shaman-real-door-handle.webp" alt="Литая ручка, дверца и регулятор воздуха камина ЭкоКамин ШАМАН"></div>

<h3>Верхнее подключение дымохода</h3>
<p class="text text-body-1">Дымоход подключается сверху, диаметр патрубка — 180 мм. На реальном ракурсе также видна округлая задняя часть корпуса с выраженными рёбрами. Конкретную высоту и конфигурацию дымохода необходимо рассчитывать под дом: по высоте здания, проходам перекрытий, материалу стен и расположению конька.</p>

<div class="blog-image-2"><img loading="lazy" width="941" height="1672" src="/img/products/ecokamin/shaman/ecokamin-shaman-real-rear-flue.webp" alt="Верхний выход дымохода и задняя часть ЭкоКамин ШАМАН"></div>

<h2>Технические характеристики</h2>

<ul>
    <li><strong>топливо:</strong> дрова;</li>
    <li><strong>номинальная мощность:</strong> 14 кВт;</li>
    <li><strong>диапазон мощности:</strong> 9,5–21 кВт;</li>
    <li><strong>площадь обогрева по данным производителя:</strong> 75–170 м²;</li>
    <li><strong>объём помещения:</strong> 185–425 м³;</li>
    <li><strong>КПД:</strong> 78,7%;</li>
    <li><strong>габариты:</strong> 560 × 1200 × 578 мм;</li>
    <li><strong>масса:</strong> 160 кг;</li>
    <li><strong>дымоход:</strong> верхний, 180 мм;</li>
    <li><strong>максимальная длина полена:</strong> 44 см;</li>
    <li><strong>длительное горение:</strong> до 8 часов;</li>
    <li><strong>системы:</strong> вторичный дожиг и подача воздуха на стекло;</li>
    <li><strong>гарантия производителя:</strong> 5 лет.</li>
</ul>

<h2>Кому подойдёт ЭкоКамин ШАМАН</h2>

<p class="text text-body-1">Модель стоит рассматривать для просторной гостиной, загородного дома, зала с высоким потолком или интерьера, где печь должна быть не только источником тепла, но и заметным архитектурным акцентом. Диапазон 75–170 м² нельзя применять без расчёта: в хорошо утеплённом доме и в здании с большими теплопотерями одна и та же мощность даст разный результат.</p>

<p class="text text-body-1">Перед покупкой нужно проверить несущую способность основания, защиту пола и стен, приток воздуха, высоту дымохода и пожарные отступы. KOTLOV.BY может подобрать дымоход и комплектующие, а также проверить схему монтажа.</p>

<h2>Цена, поступление и заказ в Беларуси</h2>

<p class="text text-body-1">Розничная цена на 20 октября 2026 года — <strong>5 414,64 BYN</strong>. Первая партия ожидается на складе поставщика в Москве <strong>26–30 октября 2026 года</strong>. Заказ можно оформить заранее; срок доставки в Беларусь менеджер подтвердит после резервирования.</p>

<p class="text text-body-1"><a href="/pechi-kaminy/ecokamin-shaman-chugunnyy-kamin"><strong>Посмотреть карточку и заказать ЭкоКамин ШАМАН →</strong></a></p>

<p class="text text-body-1">Если вы выбираете между несколькими печами, сравните модель с другими решениями в разделе <a href="/pechki"><strong>печей и каминов</strong></a>. Для безопасного подключения также понадобится подходящая <a href="/dymohody"><strong>дымоходная система</strong></a>.</p>
HTML;
    }
};
