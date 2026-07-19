<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const BIOTEP_SLUG = 'pelletnyy-kotel-biotep-25';
    private const HEAT_PUMP_SLUG = 'teplovoy-nasos-hotta-flm80-r32-30-kvt';
    private const ARTICLE_SLUG = 'montazh-teplovogo-nasosa-hotta-30-kvt-i-rezervnogo-pelletnogo-kotla-biotep-25';
    private const BLOG_CATEGORY_SLUG = 'montazh-i-obekty';

    public function up(): void
    {
        $now = now();

        $this->updateBiotepProduct($now);
        $this->createCaseArticle($now);
    }

    public function down(): void
    {
        DB::table('blog_posts')->where('slug', self::ARTICLE_SLUG)->delete();
    }

    private function updateBiotepProduct($now): void
    {
        $brandId = DB::table('brands')->where('slug', 'biodom')->value('id');

        if (! $brandId) {
            $brandId = DB::table('brands')->insertGetId([
                'name' => 'Biodom',
                'slug' => 'biodom',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $categoryId = DB::table('categories')->where('slug', 'kotly-na-pelletah')->value('id')
            ?: DB::table('categories')->where('slug', 'tverdotoplivnye')->value('id');

        DB::table('products')->updateOrInsert(
            ['slug' => self::BIOTEP_SLUG],
            [
                'category_id' => $categoryId,
                'brand_id' => $brandId,
                'supplier_id' => null,
                'name' => 'Пеллетный котёл BIOTEP 25 с самоочисткой',
                'h1' => 'Пеллетный котёл BIOTEP 25 с системой самоочистки',
                'price' => 13180.00,
                'price_old' => null,
                'currency' => 'BYN',
                'content' => $this->biotepContent(),
                'short_description' => 'Автоматический пеллетный котёл BIOTEP 25 / Biodom 25L мощностью до 22 кВт с авторозжигом, саморегуляцией горения и системой самоочистки.',
                'images' => json_encode([
                    'img/products/biodom/biotep-25-main.jpg',
                    'img/products/biodom/biotep-25-side.jpg',
                    'img/products/biodom/biotep-25-construction.png',
                    'img/products/biodom/biotep-25-schema.png',
                ], JSON_UNESCAPED_UNICODE),
                'specs' => json_encode([
                    ['key' => 'Бренд', 'value' => 'Biodom / BIOTEP'],
                    ['key' => 'Модель', 'value' => 'BIOTEP 25 / Biodom 25L'],
                    ['key' => 'Тип', 'value' => 'пеллетный котёл'],
                    ['key' => 'Диапазон мощности', 'value' => '4–22 кВт'],
                    ['key' => 'Рекомендуемая площадь', 'value' => 'до 300 м²'],
                    ['key' => 'Топливо', 'value' => 'древесные пеллеты'],
                    ['key' => 'КПД', 'value' => '91,6%'],
                    ['key' => 'Бункер', 'value' => '130 кг'],
                    ['key' => 'Питание', 'value' => '220 В'],
                    ['key' => 'Вес', 'value' => '230 кг'],
                    ['key' => 'Автоматика', 'value' => 'авторозжиг, саморегуляция, система самоочистки'],
                ], JSON_UNESCAPED_UNICODE),
                'service_info' => json_encode([
                    ['key' => 'Подбор', 'value' => 'Подберём котёл как основной или резервный источник тепла под тепловой насос.'],
                    ['key' => 'Монтаж', 'value' => 'Возможна установка котла, дымохода, обвязки и автоматики специалистами KOTLOV.BY.'],
                    ['key' => 'Обслуживание', 'value' => 'Подскажем требования к пеллетам, чистке и сезонному обслуживанию.'],
                ], JSON_UNESCAPED_UNICODE),
                'warranty' => 'Уточняйте у менеджера',
                'is_active' => true,
                'is_archived' => false,
                'in_stock' => true,
                'availability_status' => 'in_stock',
                'is_new' => true,
                'meta_title' => 'Пеллетный котёл BIOTEP 25 с самоочисткой купить в Беларуси',
                'meta_keywords' => 'BIOTEP 25, Biodom 25L, пеллетный котел с самоочисткой, пеллетный котел 25 кВт',
                'meta_description' => 'Пеллетный котёл BIOTEP 25 / Biodom 25L с авторозжигом, саморегуляцией и самоочисткой. Мощность 4–22 кВт, бункер 130 кг, КПД 91,6%.',
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        $this->assignPowerFilterToBiotep($now);
    }

    private function assignPowerFilterToBiotep($now): void
    {
        $productId = (int) DB::table('products')->where('slug', self::BIOTEP_SLUG)->value('id');
        $categoryId = (int) DB::table('categories')->where('slug', 'kotly-na-pelletah')->value('id');

        if (! $productId || ! $categoryId) {
            return;
        }

        $attributeId = (int) DB::table('attributes')
            ->where('category_id', $categoryId)
            ->where('type', 'select')
            ->where(function ($query) {
                $query->where('name', 'Мощность')->orWhere('name', 'Мощность (кВт)');
            })
            ->value('id');

        if (! $attributeId) {
            return;
        }

        $optionId = (int) DB::table('attribute_options')
            ->where('attribute_id', $attributeId)
            ->where(function ($query) {
                $query->where('name', '25')->orWhere('name', '25 кВт')->orWhere('name', '21–25 кВт');
            })
            ->value('id');

        if (! $optionId) {
            DB::table('attribute_options')->insert([
                'attribute_id' => $attributeId,
                'name' => '25 кВт',
                'sort_order' => 250,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $optionId = (int) DB::table('attribute_options')
                ->where('attribute_id', $attributeId)
                ->where('name', '25 кВт')
                ->value('id');
        }

        DB::table('product_attribute_values')->updateOrInsert(
            [
                'product_id' => $productId,
                'attribute_id' => $attributeId,
            ],
            [
                'option_id' => $optionId,
                'value' => null,
                'is_checked' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    private function createCaseArticle($now): void
    {
        DB::table('blog_categories')->updateOrInsert(
            ['slug' => self::BLOG_CATEGORY_SLUG],
            [
                'name' => 'Монтаж и объекты',
                'sort_order' => 20,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $categoryId = DB::table('blog_categories')->where('slug', self::BLOG_CATEGORY_SLUG)->value('id');

        DB::table('blog_posts')->updateOrInsert(
            ['slug' => self::ARTICLE_SLUG],
            [
                'category_id' => $categoryId,
                'author_id' => null,
                'title' => 'Монтаж теплового насоса Hotta 30 кВт с резервным пеллетным котлом BIOTEP 25',
                'excerpt' => 'Кейс KOTLOV.BY: наши специалисты смонтировали тепловой насос Hotta Flamingo FLM80-R32 30 кВт и резервный пеллетный котёл BIOTEP 25 с системой самоочистки. Разбираем, зачем нужна такая связка и что важно в котельной.',
                'content' => $this->articleContent(),
                'cover_image' => 'img/blog/works/hotta-30kw-biotep-case-cover.jpg',
                'images' => json_encode([
                    'img/blog/works/hotta-30kw-biotep-boiler-room.jpg',
                    'img/blog/works/hotta-30kw-biotep-project-gallery.jpg',
                ], JSON_UNESCAPED_UNICODE),
                'tags' => json_encode([
                    'тепловой насос 30 кВт',
                    'Hotta FLM80-R32',
                    'BIOTEP 25',
                    'пеллетный котёл',
                    'резервное отопление',
                    'монтаж KOTLOV.BY',
                ], JSON_UNESCAPED_UNICODE),
                'is_published' => true,
                'published_at' => '2026-07-12 10:00:00',
                'meta_title' => 'Монтаж Hotta 30 кВт и BIOTEP 25 | Кейс KOTLOV.BY',
                'meta_description' => 'Реальный монтаж KOTLOV.BY: тепловой насос Hotta Flamingo FLM80-R32 30 кВт плюс резервный пеллетный котёл BIOTEP 25 с самоочисткой.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    private function biotepContent(): string
    {
        return <<<'HTML'
<p>BIOTEP 25 / Biodom 25L — автоматический пеллетный котёл для отопления частного дома или коммерческого объекта. Его можно использовать как основной источник тепла или как резерв к тепловому насосу, когда нужно обеспечить стабильную работу системы в сильные морозы.</p>

<p>Котёл работает на древесных пеллетах, поддерживает автоматический розжиг, саморегуляцию горения и систему самоочистки. Такой формат снижает количество ручных операций по сравнению с классическим твердотопливным котлом и делает котельную более удобной в эксплуатации.</p>

<h2>Почему BIOTEP 25 удобно ставить в резерв</h2>

<p>В гибридной системе тепловой насос берёт на себя основную часть сезона, а пеллетный котёл подключается как резервный или пиковый источник тепла. Это особенно полезно для домов с большой площадью, высоким расходом горячей воды или требованиями к бесперебойному отоплению.</p>

<h2>Что важно предусмотреть</h2>

<p>Для правильной работы котла важны дымоход, гидравлическая обвязка, буферная ёмкость или распределительная схема, место для хранения пеллет и доступ для обслуживания. Специалисты KOTLOV.BY помогут подобрать комплектацию и связать котёл с основной системой отопления.</p>
HTML;
    }

    private function articleContent(): string
    {
        $heatPumpUrl = '/teplovyie-nasosyi/'.self::HEAT_PUMP_SLUG;
        $boilerUrl = '/kotly-na-pelletah/'.self::BIOTEP_SLUG;

        return <<<HTML
<p class="text text-body-1">В этом объекте специалисты KOTLOV.BY выполнили монтаж теплового насоса <a href="{$heatPumpUrl}">Hotta Flamingo FLM80-R32 30 кВт</a> и резервного пеллетного котла <a href="{$boilerUrl}">BIOTEP 25 с системой самоочистки</a>. Это не просто установка двух источников тепла, а полноценная гибридная котельная: основной сезон закрывает тепловой насос, а пеллетный котёл остаётся надёжным резервом и поддержкой в пиковые нагрузки.</p>

<p class="text text-body-1">Такой подход особенно уместен для домов большой площади, объектов с высоким расходом горячей воды и ситуаций, где владелец хочет получить комфорт теплового насоса, но сохранить независимость от одного источника энергии.</p>

<blockquote>
    Гибридная котельная — это когда тепловой насос работает большую часть сезона экономично, а пеллетный котёл страхует систему зимой и даёт запас по мощности.
</blockquote>

<div class="blog-image-2">
    <img loading="lazy" width="1200" height="854" src="/img/blog/works/hotta-30kw-biotep-project-gallery.jpg" alt="Монтаж теплового насоса Hotta 30 кВт и резервного пеллетного котла BIOTEP 25">
</div>

<h2>Наружный блок Hotta 30 кВт</h2>

<p class="text text-body-1">Тепловой насос установлен снаружи дома на отдельном металлическом основании. Для оборудования такой мощности важно обеспечить свободный забор и выброс воздуха, нормальный отвод конденсата, удобный доступ для обслуживания и правильную трассу до котельной.</p>

<p class="text text-body-1">Модель Hotta FLM80-R32 мощностью 30 кВт рассчитана на серьёзные задачи: отопление, охлаждение и подготовку горячей воды. В этой системе она выступает основным источником тепла, а котельная внутри дома собрана так, чтобы оборудование работало не само по себе, а как единая связанная схема.</p>

<h2>Котельная с резервным BIOTEP 25</h2>

<p class="text text-body-1">Внутри котельной установлен пеллетный котёл BIOTEP 25 с системой самоочистки. Его задача — резерв и поддержка системы в те периоды, когда дому требуется больше тепла или когда владелец хочет иметь независимый источник отопления на пеллетах.</p>

<p class="text text-body-1">Также в котельной видны буферная ёмкость, насосные группы, расширительный бак и аккуратная обвязка. Именно эти “невидимые” элементы часто определяют, насколько спокойно будет работать система зимой: без скачков температуры, лишних включений и постоянных ручных вмешательств.</p>

<div class="blog-image-2">
    <img loading="lazy" width="1200" height="854" src="/img/blog/works/hotta-30kw-biotep-boiler-room.jpg" alt="Котельная с буферной ёмкостью, насосными группами и резервным пеллетным котлом BIOTEP 25">
</div>

<h2>Почему тепловой насос плюс пеллетный котёл — сильная связка</h2>

<p class="text text-body-1">Тепловой насос удобен и экономичен в большую часть отопительного сезона. Пеллетный котёл даёт автономность, запас мощности и уверенность в морозы. Вместе они позволяют сделать систему более гибкой: автоматика может распределять нагрузку между источниками, а владелец не зависит от одного сценария отопления.</p>

<div class="kotlov-article-note">
    <strong>Что важно в такой системе</strong>
    Подбор мощности, буферная ёмкость, гидравлическая схема, дымоход для пеллетного котла, электропитание теплового насоса и доступ для обслуживания. Ошибка в одном узле может испортить работу всей котельной.
</div>

<h2>Что получает владелец дома</h2>

<p class="text text-body-1">В результате объект получает современную систему отопления с запасом: тепловой насос работает как основной источник, пеллетный котёл остаётся резервом, а котельная собрана с учётом обслуживания и дальнейшей эксплуатации. Для частного дома это спокойствие: есть комфорт, автоматика и понятный план на холодный период.</p>

<p class="text text-body-1">Если вы планируете похожую систему — тепловой насос, резервный котёл, буферную ёмкость или модернизацию существующей котельной — специалисты KOTLOV.BY помогут рассчитать схему под ваш дом и подобрать оборудование без лишних экспериментов.</p>
HTML;
    }
};
