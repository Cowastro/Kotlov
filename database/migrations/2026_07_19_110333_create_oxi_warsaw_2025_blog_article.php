<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = 'kotlov-by-i-oxi-v-varshave-2025';
    private const CATEGORY_SLUG = 'novosti-kompanii';

    public function up(): void
    {
        $now = now();

        $categoryId = DB::table('blog_categories')->where('slug', self::CATEGORY_SLUG)->value('id');

        if (! $categoryId) {
            DB::table('blog_categories')->insert([
                'name' => 'Новости компании',
                'slug' => self::CATEGORY_SLUG,
                'sort_order' => 5,
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
                'title' => 'KOTLOV.BY и OXI в Варшаве: встреча партнёров и новые пеллетные горелки EVO',
                'excerpt' => 'Владелец KOTLOV.BY посетил ежегодную отраслевую выставку в Варшаве и встретился с производителем пеллетных горелок OXI. Обсудили развитие сотрудничества и новые решения серии EVO.',
                'content' => $this->content(),
                'cover_image' => 'img/blog/works/oxi-warsaw-2025-cover.jpg',
                'images' => json_encode([
                    'img/blog/works/oxi-warsaw-2025-partners.jpg',
                    'img/blog/works/oxi-warsaw-2025-evo.jpg',
                ], JSON_UNESCAPED_UNICODE),
                'tags' => json_encode([
                    'KOTLOV.BY',
                    'OXI',
                    'EVO',
                    'пеллетные горелки',
                    'Варшава',
                    'партнёры',
                    'выставка 2025',
                ], JSON_UNESCAPED_UNICODE),
                'is_published' => true,
                'published_at' => '2025-09-20 10:00:00',
                'meta_title' => 'KOTLOV.BY и OXI в Варшаве 2025 | Пеллетные горелки EVO',
                'meta_description' => 'Владелец KOTLOV.BY посетил ежегодную выставку в Варшаве, встретился с партнёрами OXI и обсудил развитие сотрудничества по пеллетным горелкам EVO.',
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
<p class="text text-body-1">В 2025 году владелец KOTLOV.BY посетил ежегодную отраслевую выставку в Варшаве, где состоялась встреча с партнёрами и производителем пеллетных горелок OXI. Такие поездки важны не только ради знакомства с новинками: это возможность лично обсудить развитие рынка, требования клиентов и планы дальнейшего сотрудничества.</p>

<p class="text text-body-1">Для KOTLOV.BY партнёрство с производителями — это не формальная строка в каталоге. Нам важно понимать, как оборудование развивается, какие задачи ставит перед собой завод и какие решения будут актуальны для частных домов и котельных в Беларуси.</p>

<blockquote>
    Хорошее партнёрство строится не только на поставках. Это доверие, постоянный обмен опытом и общее понимание, каким должен быть продукт для реального клиента.
</blockquote>

<div class="tf-grid-layout sm-col-2 gap-30">
    <div class="blog-image-2">
        <img loading="lazy" width="900" height="640" src="/img/blog/works/oxi-warsaw-2025-partners.jpg" alt="Встреча KOTLOV.BY с производителем пеллетных горелок OXI на выставке в Варшаве">
    </div>
    <div class="blog-image-2">
        <img loading="lazy" width="900" height="640" src="/img/blog/works/oxi-warsaw-2025-evo.jpg" alt="Пеллетные горелки OXI EVO на выставочном стенде в Варшаве">
    </div>
</div>

<h2>Новые пеллетные горелки EVO</h2>

<p class="text text-body-1">Одним из ключевых направлений встречи стала серия пеллетных горелок EVO. Это оборудование разрабатывается для автоматизации твердотопливного отопления и помогает сделать котельную более удобной в эксплуатации: меньше ручных операций, понятнее управление, стабильнее режим горения.</p>

<p class="text text-body-1">Для владельцев домов такие решения интересны прежде всего возможностью модернизировать существующую котельную и получить более комфортный режим отопления без полного отказа от твердотопливной системы.</p>

<h2>Почему личные встречи важны</h2>

<p class="text text-body-1">Когда речь идёт об отопительном оборудовании, важно видеть не только цену и паспортные характеристики. На выставке можно оценить качество исполнения, конструктив, удобство обслуживания, автоматику и то, как производитель видит развитие своей линейки.</p>

<p class="text text-body-1">Встреча в Варшаве стала именно таким рабочим диалогом: обсудили текущий опыт, перспективы поставок, развитие серии EVO и задачи, которые чаще всего возникают у клиентов на белорусском рынке.</p>

<div class="kotlov-article-note">
    <strong>Встреча друзей и партнёров</strong>
    Для KOTLOV.BY такие поездки — это способ держать руку на пульсе рынка и привозить клиентам не случайные товары, а понятные решения от производителей, с которыми есть живой контакт и общие планы.
</div>

<h2>Что это значит для клиентов KOTLOV.BY</h2>

<p class="text text-body-1">Мы продолжаем расширять горизонты и усиливать направление автоматизированного твердотопливного отопления. Пеллетные горелки OXI и серия EVO — один из примеров оборудования, которое может быть интересно тем, кто хочет повысить комфорт котельной, снизить ручной труд и получить более предсказуемую работу системы.</p>

<p class="text text-body-1">Если вы рассматриваете модернизацию котельной, переход на пеллетное отопление или подбор горелки под существующий котёл, специалисты KOTLOV.BY помогут оценить объект и предложить решение под вашу задачу.</p>
HTML;
    }
};
