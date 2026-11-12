<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Local SEO per city and category, edited in the admin («Каталог → SEO городов»). The first two pages are the
 * gas boilers of the two partner stores: in Search Console «купить газовый котел в лиде» is the city's main query.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('city_category_seo')) {
            Schema::create('city_category_seo', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('city_id')->constrained('cities')->cascadeOnDelete();
                $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
                $table->string('h1')->nullable();
                $table->string('meta_title')->nullable();
                $table->string('meta_description', 500)->nullable();
                $table->longText('content')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['city_id', 'category_id']);
            });
        }

        $category = DB::table('categories')->where('slug', 'gazovye')->value('id');
        if (! $category) {
            return;
        }
        foreach ($this->pages() as $citySlug => $page) {
            $city = DB::table('cities')->where('slug', $citySlug)->value('id');
            if ($city && ! DB::table('city_category_seo')->where('city_id', $city)->where('category_id', $category)->exists()) {
                DB::table('city_category_seo')->insert($page + [
                    'city_id' => $city, 'category_id' => $category, 'is_active' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        Cache::flush();
    }

    public function down(): void
    {
        Schema::dropIfExists('city_category_seo');
    }

    private function pages(): array
    {
        $brands = 'Сравните <a href="/brands/lemaks">Лемакс</a>, <a href="/brands/alfa-kalor">Alfa Kalor</a>, '
            .'<a href="/brands/protherm">Protherm</a>, <a href="/brands/baxi">BAXI</a>, <a href="/brands/ariston">Ariston</a> '
            .'и <a href="/brands/junkers">Junkers</a>.';
        $choose = '<h3>Настенный или напольный</h3><p>Для дома до 200–250 м² обычно берут настенный котёл: он компактный и ставится '
            .'на кухне или в котельной. Напольный выбирают для больших домов и систем с естественной циркуляцией. '.$brands.'</p>'
            .'<h3>Одно- или двухконтурный</h3><p>Двухконтурный котёл греет дом и готовит горячую воду — удобно, если в доме одна-две '
            .'точки водоразбора. Если санузлов несколько, берут одноконтурный котёл с бойлером косвенного нагрева.</p>'
            .'<h3>Мощность</h3><p>Ориентир — около 1 кВт на 10 м² утеплённого дома с потолками до 2,7 м. Для старого кирпичного дома '
            .'или дома с большими окнами нужен запас 20–30 %. Точнее посчитаем по площади, стенам и окнам.</p>';

        return [
            'lida' => [
                'h1' => 'Газовые котлы в Лиде',
                'meta_title' => 'Купить газовый котёл в Лиде — цены, наличие | KOTLOV',
                'meta_description' => 'Газовые котлы в Лиде: настенные и напольные, одно- и двухконтурные. Магазин на ул. Калинина, 57 — образцы, подбор мощности, доставка.',
                'content' => '<h2>Как купить газовый котёл в Лиде</h2>'
                    .'<p>Газовые котлы в Лиде можно посмотреть вживую в магазине KOTLOV на ул. Калинина, 57 — рядом с Курганом Бессмертия. '
                    .'В зале есть образцы оборудования, консультант поможет подобрать котёл под ваш дом и уточнит наличие и срок поставки '
                    .'моделей, которых нет на месте. Заказ можно оформить на сайте и забрать в Лиде или получить с доставкой.</p>'
                    .$choose
                    .'<h3>Подключение к газу в Лиде</h3><p>Котёл устанавливают по проекту газоснабжения дома. В Лиде и Лидском районе '
                    .'газоснабжение обслуживает РУП «Гроднооблгаз»: проект, монтаж специализированной организацией и пуск газа согласуются с ними. '
                    .'Перед покупкой сверьте мощность и тип котла с проектом.</p>'
                    .'<h3>Получение заказа</h3><p>Самовывоз из магазина: г. Лида, ул. Калинина, 57 — наличие уточняйте по телефону. '
                    .'Доставка по Лиде и району — <a href="/dostavka">условия доставки и оплаты</a>. Вместе с котлом посмотрите '
                    .'<a href="/schetchiki-gaza">газовые счётчики</a> и <a href="/dymohody">дымоходы</a>.</p>',
            ],
            'borisov' => [
                'h1' => 'Газовые котлы в Борисове',
                'meta_title' => 'Газовые котлы в Борисове — цены, купить | KOTLOV',
                'meta_description' => 'Газовые котлы в Борисове: подбор по площади дома, одно- и двухконтурные модели. Магазин «Печкiн» на ул. Демина, 39, пав. 222.',
                'content' => '<h2>Газовый котёл в Борисове: подбор и покупка</h2>'
                    .'<p>Заказы KOTLOV в Борисове принимает и выдаёт магазин «Печкiн» (ООО «СБГ бай») на ул. Демина, 39, павильон 222. '
                    .'Работаем со вторника по пятницу с 9:00 до 18:00, в субботу с 9:00 до 17:00. В магазине помогут выбрать котёл, '
                    .'дымоход и комплектующие, а модели, которых нет на месте, привезут под заказ.</p>'
                    .$choose
                    .'<h3>Подключение к газу</h3><p>Газовый котёл ставят только по проекту газоснабжения: проект, монтаж специализированной '
                    .'организацией и пуск газа согласуются с газоснабжающей организацией района. Если проект уже есть, возьмите его с собой — '
                    .'по нему сразу подберём подходящую модель.</p>'
                    .'<h3>Получение заказа</h3><p>Самовывоз из магазина в Борисове или доставка — <a href="/dostavka">условия доставки и оплаты</a>. '
                    .'Вместе с котлом нужны <a href="/schetchiki-gaza">газовый счётчик</a> и <a href="/dymohody">дымоход</a>.</p>',
            ],
        ];
    }
};
