<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PRODUCT_SLUG = 'pelletnaya-gorelka-kotlov-xo-evo-18-kvt-eb140';
    private const ARTICLE_SLUG = 'pelletnaya-gorelka-kotlov-xo-evo-26-kvt';

    public function up(): void
    {
        $now = now();
        $product = DB::table('products')->where('slug', self::PRODUCT_SLUG)->first();

        if ($product) {
            $regularPrice = (float) ($product->price_old ?: $product->price);

            DB::table('products')->where('id', $product->id)->update([
                'price_old' => $regularPrice,
                'price' => round($regularPrice * .8, 2),
                'is_sale' => true,
                'is_featured' => true,
                'in_stock' => true,
                'availability_status' => 'in_stock',
                'stock_qty' => 2,
                'short_description' => 'Распродажа складского остатка: 2 горелки в наличии со скидкой 20%. Диапазон 8–26 кВт, сменная топка AISI 310S, самоочистка и контроллер XO-1.0S.',
                'video_url' => 'https://www.youtube.com/watch?v=v7n7WoDPUTA&t=67s',
                'updated_at' => $now,
            ]);
        }

        $categoryId = DB::table('blog_categories')->where('slug', 'kotly-i-otoplenie')->value('id');

        DB::table('blog_posts')->updateOrInsert(
            ['slug' => self::ARTICLE_SLUG],
            [
                'category_id' => $categoryId,
                'author_id' => null,
                'title' => 'KOTLOV XO EVO 26 кВт: распродажа двух пеллетных горелок со скидкой 20%',
                'excerpt' => 'Технический разбор KOTLOV XO EVO 26 кВт EB140: сменная топка AISI 310S, самоочистка, розжиг, автоматика, совместимость с котлом и распродажа двух горелок.',
                'content' => $this->articleContent(),
                'cover_image' => 'img/promotions/kotlov-xo-evo-26-stock-cover.jpg',
                'images' => json_encode([
                    'img/promotions/kotlov-xo-evo-26-stock-cover.jpg',
                    'img/promotions/kotlov-xo-evo-26-stock-side.jpg',
                    'img/promotions/kotlov-xo-evo-26-firebox.jpg',
                    'img/promotions/kotlov-xo-evo-26-fan.jpg',
                    'product/0012/012188/evo_18_1.jpg',
                    'product/0012/012188/EVO_size.png',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'tags' => json_encode(['пеллетные горелки', 'KOTLOV XO', 'EVO', '26 кВт', 'автоматизация котельной'], JSON_UNESCAPED_UNICODE),
                'is_published' => true,
                'published_at' => $now,
                'views_count' => 0,
                'meta_title' => 'Пеллетная горелка KOTLOV XO EVO 26 кВт — скидка 20%',
                'meta_description' => 'KOTLOV XO EVO 26 кВт EB140: две горелки в наличии со скидкой 20%. Сменная топка AISI 310S, самоочистка, автоматика и инженерный подбор.',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('blog_posts')->where('slug', self::ARTICLE_SLUG)->delete();

        $product = DB::table('products')->where('slug', self::PRODUCT_SLUG)->first();
        if ($product && (float) $product->price === 5120.0 && (float) $product->price_old === 6400.0) {
            DB::table('products')->where('id', $product->id)->update([
                'price' => 6400,
                'price_old' => null,
                'is_sale' => true,
                'is_featured' => true,
                'in_stock' => true,
                'availability_status' => 'in_stock',
                'stock_qty' => null,
                'short_description' => null,
                'video_url' => null,
                'updated_at' => now(),
            ]);
        }
    }

    private function articleContent(): string
    {
        return <<<'HTML'
<p class="text text-body-1"><strong>KOTLOV XO EVO 26 кВт EB140</strong> — компактная автоматическая факельная горелка для водогрейных котлов. Сейчас на складе KOTLOV находятся две такие горелки. На обе действует скидка 20%: цена снижена с 6 400 до <strong>5 120 BYN</strong>. Это реальный складской остаток, а не поставка под заказ.</p>
<blockquote>В распродаже участвуют ровно две KOTLOV XO EVO 26 кВт EB140. Наличие уже подтверждено: перед покупкой требуется проверить не склад, а совместимость горелки с вашим котлом, дверцей и дымоходом.</blockquote>
<div class="blog-image-2"><img loading="lazy" width="1600" height="1200" src="/img/promotions/kotlov-xo-evo-26-stock-cover.jpg" alt="Пеллетная горелка KOTLOV XO EVO 26 кВт из складского остатка"></div>

<h2>Для каких задач рассчитана EVO 26 кВт</h2>
<p class="text text-body-1">Рабочий диапазон модели составляет <strong>8–26 кВт</strong>. Производитель указывает ориентировочную площадь 100–300 м², однако площадь сама по себе не определяет нужную мощность. Важно учитывать утепление, высоту помещений, вентиляцию, температуру внутри, регион и фактические теплопотери.</p>
<p class="text text-body-1">EVO 26 можно использовать в новом пеллетном котле или рассматривать для модернизации подходящего твердотопливного котла. Горелка автоматически подаёт топливо, выполняет розжиг, регулирует мощность и останавливает горение по команде контроллера. Это позволяет получить более предсказуемое отопление по сравнению с ручной загрузкой дров или угля.</p>

<h2>Что именно находится на складе</h2>
<p class="text text-body-1">На фотографиях ниже показана одна из фактически находящихся у нас горелок EVO. Видны корпус, центробежный вентилятор, стальная топочная часть и колосниковый блок. Фотографии сделаны без рекламного рендера: можно рассмотреть конструкцию того оборудования, которое участвует в распродаже.</p>
<div class="blog-image-2"><img loading="lazy" width="1600" height="1200" src="/img/promotions/kotlov-xo-evo-26-stock-side.jpg" alt="Корпус и сменная топка KOTLOV XO EVO 26 кВт"></div>
<div class="blog-image-2"><img loading="lazy" width="1200" height="1600" src="/img/promotions/kotlov-xo-evo-26-fan.jpg" alt="Вентилятор пеллетной горелки KOTLOV XO EVO 26 кВт"></div>

<h2>Сменная камера сгорания из AISI 310S</h2>
<p class="text text-body-1">Топка — наиболее теплонагруженная часть любой пеллетной горелки. В EVO она выполнена отдельным сменным узлом из жаропрочной нержавеющей стали AISI 310S. Если после длительной эксплуатации камера естественно износится, её можно заменить отдельно, не покупая новую горелку целиком.</p>
<p class="text text-body-1">Для владельца это означает ремонтопригодность и понятную стоимость будущего обслуживания. В продаже существуют стальная и шамотированная версии EVO, но акционная модель EB140 оснащена именно компактной стальной топкой.</p>
<div class="blog-image-2"><img loading="lazy" width="1200" height="1600" src="/img/promotions/kotlov-xo-evo-26-firebox.jpg" alt="Колосниковый блок и камера сгорания KOTLOV XO EVO 26 кВт"></div>

<h2>Самоочистка колосника</h2>
<p class="text text-body-1">Внутри хорошо виден подпружиненный верхний колосник. Во время цикла очистки он помогает удалять продукты сгорания с основного колосника и препятствует попаданию пеллет внутрь колосникового блока. Сам блок отсоединяется для обслуживания.</p>
<p class="text text-body-1">Автоматическая очистка уменьшает частоту ручной работы, особенно при стабильном качестве топлива. Но она не отменяет регулярную очистку зольника, теплообменника котла, дымового канала и проверку фотодатчика. Период обслуживания зависит от зольности и прочности конкретных пеллет.</p>

<h2>Первичный и вторичный воздух</h2>
<p class="text text-body-1">Конструкция EVO позволяет раздельно настраивать пропорцию первичного и вторичного воздуха. Первичный воздух поддерживает горение топлива на колоснике, вторичный участвует в дожиге газов над факелом. Правильное соотношение помогает получить устойчивое пламя и уменьшить количество несгоревших частиц.</p>
<p class="text text-body-1">На официальной странице EVO производитель указывает КПД сгорания до 98% в настроенном режиме. В технических характеристиках карточки KOTLOV указано 92%. Эти цифры нельзя трактовать как гарантированный сезонный КПД всей котельной: реальный результат зависит от котла, дымохода, топлива, температуры теплоносителя и качества пусконаладки.</p>

<h2>Керамический розжиг и автоматика XO</h2>
<p class="text text-body-1">Керамический запальник рассчитан производителем на ресурс до 10 000 розжигов. Контроллер XO-1.0S управляет подачей пеллет, вентилятором, розжигом, модуляцией мощности и остановкой. Средняя электрическая мощность горелки указана на уровне 48 Вт, во время розжига — 407 Вт.</p>
<p class="text text-body-1">Воздушный затвор в тракте подачи уменьшает риск обратного движения дымовых газов в сторону шнека. Между шнеком и горелкой используется плавкий гофрированный рукав. Это важные элементы общей схемы безопасности, но монтаж всё равно должен учитывать требования паспорта, тягу дымохода и защиту котельной.</p>

<h2>Видео: устройство и работа KOTLOV XO EVO</h2>
<p class="text text-body-1">В видео показана серия EVO и основные конструктивные решения. Просмотр начинается с технической части ролика.</p>
<div style="position:relative;width:100%;padding-top:56.25%;overflow:hidden;border-radius:18px;background:#111;margin:28px 0;"><iframe loading="lazy" src="https://www.youtube-nocookie.com/embed/v7n7WoDPUTA?start=67" title="KOTLOV XO EVO — пеллетная горелка нового поколения" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen style="position:absolute;inset:0;width:100%;height:100%;border:0;"></iframe></div>

<h2>Технические характеристики EVO 26 EB140</h2>
<ul>
<li><strong>Мощность:</strong> 8–26 кВт.</li>
<li><strong>Питание:</strong> 230 В / 50 Гц.</li>
<li><strong>Среднее электропотребление:</strong> 48 Вт.</li>
<li><strong>Мощность во время розжига:</strong> 407 Вт.</li>
<li><strong>Габариты:</strong> 601 × 341 × 273 мм.</li>
<li><strong>Сечение топочной части:</strong> 139 × 142 мм.</li>
<li><strong>Минимальное разрежение в котле:</strong> 20 Па.</li>
<li><strong>Масса:</strong> 18 кг.</li>
<li><strong>Топливо:</strong> древесные пеллеты диаметром 6–8 мм.</li>
<li><strong>Гарантия:</strong> 24 месяца.</li>
</ul>

<h2>Что входит в комплект</h2>
<p class="text text-body-1">Стандартная поставка объединена по принципу All‑in‑One: горелка EVO 26 кВт EB140, контроллер XO-1.0S, шнек подачи топлива, поворотное колено, гофрированный рукав диаметром 60 мм с хомутами, кабели и эксплуатационная документация. Бункер, переходной фланец, новая дверца котла и монтажные работы рассчитываются отдельно под объект.</p>

<h2>Что проверить до установки</h2>
<ul>
<li><strong>Тепловую нагрузку.</strong> Горелка должна работать в своём диапазоне без постоянного тактования и без нехватки мощности в мороз.</li>
<li><strong>Топку котла.</strong> Нужны достаточный объём для факела и правильное расстояние до стенок и теплообменника.</li>
<li><strong>Дверцу.</strong> Проверяются размер отверстия, жёсткость и возможность герметично установить фланец.</li>
<li><strong>Дымоход.</strong> Важны сечение, высота, разрежение, ревизия и отсутствие неконтролируемого подсоса воздуха.</li>
<li><strong>Место для топлива.</strong> Бункер и шнек располагают так, чтобы пеллеты оставались сухими, а к оборудованию сохранялся сервисный доступ.</li>
<li><strong>Гидравлику.</strong> Для котла проверяют защиту обратной воды, насосы, автоматику контуров и необходимость буферной ёмкости.</li>
</ul>

<h2>Условия распродажи</h2>
<p class="text text-body-1">Обычная цена KOTLOV XO EVO 26 кВт EB140 — 6 400 BYN. Цена со скидкой 20% — <strong>5 120 BYN</strong>, экономия составляет 1 280 BYN. В акции участвуют две горелки, физически находящиеся на складе KOTLOV. После продажи второй единицы предложение завершается.</p>
<p class="text text-body-1">Посмотреть фотографии, характеристики и оформить заказ можно в карточке <a href="/pelletnye-gorelki/pelletnaya-gorelka-kotlov-xo-evo-18-kvt-eb140"><strong>KOTLOV XO EVO 26 кВт EB140</strong></a>. Все действующие предложения собраны в разделе <a href="/akcii"><strong>«Акции»</strong></a>, а другие мощности доступны в каталоге <a href="/pelletnye-gorelki"><strong>пеллетных горелок</strong></a>.</p>
<p class="text text-body-1">Перед заказом инженер KOTLOV может проверить совместимость с вашим котлом, подобрать фланец, бункер и схему подачи пеллет, а также рассчитать монтаж. Для этого понадобятся модель котла, фотографии топки и дверцы, параметры дымохода и площадь объекта.</p>
HTML;
    }
};
