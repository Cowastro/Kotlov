<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = 'teplovoy-nasos-115-kvt-r290-ostroshitskiy-gorodok';
    private const CATEGORY_SLUG = 'montazh-i-obekty';

    public function up(): void
    {
        $now = now();

        $categoryId = DB::table('blog_categories')->where('slug', self::CATEGORY_SLUG)->value('id');

        if (! $categoryId) {
            DB::table('blog_categories')->insert([
                'name' => 'Монтаж и объекты',
                'slug' => self::CATEGORY_SLUG,
                'sort_order' => 30,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $categoryId = DB::table('blog_categories')->where('slug', self::CATEGORY_SLUG)->value('id');
        }

        DB::table('blog_posts')->updateOrInsert(
            ['slug' => self::SLUG],
            [
                'category_id' => $categoryId,
                'author_id' => null,
                'title' => 'Тепловой насос 11.5 кВт R290 на радиаторы: объект в дачном кооперативе, Острошицкий Городок',
                'excerpt' => 'KOTLOV.BY смонтировал тепловой насос 11.5 кВт на экологичном хладагенте R290 для дома в дачном кооперативе в Острошицком Городке. Радиаторная система отопления, встроенная буферная ёмкость на 90 литров, температура подачи до 75°C, управление — беспроводной WiFi-терморегулятор с поддержкой Алисы.',
                'content' => $this->content(),
                'cover_image' => 'img/blog/works/heatpump-ostroshitsky-cover.jpg',
                'images' => json_encode([
                    'img/blog/works/heatpump-ostroshitsky-cover.jpg',
                    'img/blog/works/heatpump-ostroshitsky-2.jpg',
                    'img/blog/works/heatpump-ostroshitsky-3.jpg',
                    'img/blog/works/heatpump-ostroshitsky-4.jpg',
                    'img/blog/works/heatpump-ostroshitsky-5.jpg',
                ], JSON_UNESCAPED_UNICODE),
                'tags' => json_encode([
                    'тепловые насосы',
                    'R290',
                    'монтаж',
                    'Острошицкий Городок',
                    'радиаторное отопление',
                    'умный дом',
                ], JSON_UNESCAPED_UNICODE),
                'is_published' => true,
                'published_at' => $now,
                'meta_title' => 'Тепловой насос 11.5 кВт R290 на радиаторы — Острошицкий Городок | KOTLOV.BY',
                'meta_description' => 'Кейс KOTLOV.BY: тепловой насос 11.5 кВт на хладагенте R290, радиаторная система, буферная ёмкость 90 л, подача до 75°C, WiFi-терморегулятор с Алисой. Дачный кооператив, Острошицкий Городок.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        DB::table('blog_posts')->where('slug', self::SLUG)->delete();
    }

    private function content(): string
    {
        return <<<'HTML'
<p class="text text-body-1">KOTLOV.BY смонтировал тепловой насос мощностью 11.5 кВт на экологичном хладагенте R290 для дома в дачном кооперативе в Острошицком Городке. Особенность объекта — насос работает не на тёплый пол, а на обычную радиаторную систему отопления, а подача теплоносителя разгоняется до 75°C. Для теплового насоса это нетипично высокая температура, и именно здесь раскрывается разница между хладагентами.</p>

<div class="blog-video" style="margin:24px 0;max-width:420px">
    <video src="/video/blog/heatpump-ostroshitsky-working.mp4" poster="/img/blog/works/heatpump-ostroshitsky-cover.jpg" autoplay muted loop playsinline style="width:100%;height:auto;border-radius:12px;display:block"></video>
</div>

<blockquote>
    Радиаторы — это высокая температура подачи, и не каждый тепловой насос тянет такой режим без потери эффективности. R290 в этом смысле — один из немногих хладагентов, которые действительно выдают высокую температуру нагрева без просадки по мощности.
</blockquote>

<h2>Почему R290 для радиаторной системы</h2>

<p class="text text-body-1">Большинство бытовых тепловых насосов проектируются с прицелом на тёплый пол — там температура теплоносителя редко превышает 35–40°C, и почти любой хладагент справляется без проблем. Радиаторы — другая история: старой системе часто требуется 60–75°C, чтобы прогреть помещение так же эффективно, как привычный котёл.</p>

<p class="text text-body-1">Хладагент R290 (пропан) как раз рассчитан на такой диапазон: он позволяет тепловому насосу стабильно выдавать высокую температуру подачи без резкого падения коэффициента полезного действия. Это и стало решающим фактором выбора для этого объекта — переводить дом на тепловой насос, но при этом сохранять существующую радиаторную разводку без её замены на низкотемпературную.</p>

<div class="blog-image-2">
    <img loading="lazy" width="1200" height="1600" src="/img/blog/works/heatpump-ostroshitsky-2.jpg" alt="Тепловой насос 11.5 кВт на хладагенте R290, установлен у стены дома">
</div>

<h2>Встроенная буферная ёмкость на 90 литров</h2>

<p class="text text-body-1">В корпусе теплового насоса — встроенная буферная ёмкость на 90 литров. Она сглаживает работу компрессора: вместо частых включений/выключений насос набирает и отдаёт тепло более плавно, что продлевает срок службы оборудования и стабилизирует температуру в доме.</p>

<p class="text text-body-1">Встроенный, а не выносной бак — заметный плюс для дачного объекта: меньше труб, меньше точек соединения, компактнее занимаемое место в доме. Всё, что нужно для буферизации теплоносителя, уже внутри самого агрегата.</p>

<div class="blog-image-2">
    <img loading="lazy" width="1200" height="1600" src="/img/blog/works/heatpump-ostroshitsky-3.jpg" alt="Тепловой насос установлен на улице у дома в дачном кооперативе, Острошицкий Городок">
</div>

<h2>Управление: WiFi-терморегулятор и Алиса</h2>

<p class="text text-body-1">Управляется система беспроводным терморегулятором с поддержкой WiFi — задать или изменить температуру можно удалённо, не выходя к самому тепловому насосу. Отдельная деталь для дачного формата: терморегулятор интегрирован с голосовым помощником Алиса, так что температуру можно скорректировать голосовой командой прямо из дома.</p>

<p class="text text-body-1">Для дачного кооператива, где владелец бывает не каждый день, это особенно полезно: приехав в холодный дом, можно поднять температуру голосом или через приложение ещё до того, как снял куртку — или вовсе сделать это заранее, находясь в дороге.</p>

<div class="blog-image-2">
    <img loading="lazy" width="1200" height="1600" src="/img/blog/works/heatpump-ostroshitsky-4.jpg" alt="Панель управления умным домом на стене">
</div>

<div class="blog-image-2">
    <img loading="lazy" width="1200" height="1600" src="/img/blog/works/heatpump-ostroshitsky-5.jpg" alt="WiFi-терморегулятор теплового насоса с поддержкой Алисы">
</div>

<h2>Итог</h2>

<p class="text text-body-1">Этот объект — хороший пример того, что переход на тепловой насос не обязательно требует замены радиаторной системы отопления на тёплый пол. При правильном подборе хладагента и мощности (в данном случае — R290 и 11.5 кВт) насос спокойно держит температуру подачи до 75°C, которой достаточно для полноценной работы обычных радиаторов.</p>

<blockquote>
    Дом в дачном кооперативе не потерял ни одного радиатора при переходе на тепловой насос — и получил вдобавок буферную ёмкость, беспроводное управление и голосовое управление через Алису.
</blockquote>
HTML;
    }
};
