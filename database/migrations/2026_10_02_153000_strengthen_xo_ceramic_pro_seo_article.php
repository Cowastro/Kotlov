<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = 'pelletnaya-gorelka-100-kvt-kotlov-xo-ceramic-pro';

    private const IMAGE_REPLACEMENTS = [
        '/img/promotions/oxi-ceramic-100-kazakhstan-2018.jpg' => '/img/blog/works/oxi-ceramic-100-kazakhstan-2018-enhanced.jpg',
        '/img/promotions/hotta-oxi-ceramic-100-minsk-2018.jpg' => '/img/blog/works/hotta-oxi-100-minsk-2018-enhanced.jpg',
        '/img/promotions/hotta-oxi-ceramic-100-minsk-2018-detail.jpg' => '/img/blog/works/hotta-oxi-burner-detail-2018-enhanced.jpg',
    ];

    public function up(): void
    {
        $post = DB::table('blog_posts')->where('slug', self::SLUG)->first();

        if (! $post) {
            return;
        }

        $content = str_replace(
            array_keys(self::IMAGE_REPLACEMENTS),
            array_values(self::IMAGE_REPLACEMENTS),
            (string) $post->content
        );

        // These legacy proxy files currently resolve to the generic
        // "Фото скоро появится" placeholder. The article already has verified
        // current-product photos immediately after the opening paragraph, so
        // remove the misleading duplicates instead of publishing a placeholder.
        $content = str_replace([
            '<div class="blog-image-2"><img loading="lazy" width="1400" height="1050" src="/proxy-image/product/0012/012203/cp-100_1.jpg" alt="Пеллетная горелка KOTLOV XO Ceramic PRO 100 кВт"></div>',
            '<div class="blog-image-2"><img loading="lazy" width="1400" height="1050" src="/proxy-image/product/0012/012203/cp-100_2.jpg" alt="Подвижные колосники горелки KOTLOV XO Ceramic PRO"></div>',
        ], '', $content);

        $historyAnchor = '<h2>Преемственность OXI Ceramic и новая автоматика</h2>';

        if (! str_contains($content, 'data-xo-history-2018="1"') && str_contains($content, $historyAnchor)) {
            $content = str_replace($historyAnchor, $historyAnchor."\n".$this->historyProof(), $content);
        }

        $faqAnchor = '<h2>Акция и инженерный расчёт</h2>';

        if (! str_contains($content, 'data-xo-faq-links="1"') && str_contains($content, $faqAnchor)) {
            $content = str_replace($faqAnchor, $this->faqAndLinks()."\n\n".$faqAnchor, $content);
        }

        $images = json_decode((string) $post->images, true) ?: [];
        $oldRelative = array_map(fn (string $path) => ltrim($path, '/'), array_keys(self::IMAGE_REPLACEMENTS));
        $images = array_values(array_filter($images, fn ($image) => ! in_array($image, $oldRelative, true)));

        foreach (self::IMAGE_REPLACEMENTS as $replacement) {
            $relative = ltrim($replacement, '/');
            if (! in_array($relative, $images, true)) {
                $images[] = $relative;
            }
        }

        DB::table('blog_posts')->where('id', $post->id)->update([
            'title' => 'Пеллетная горелка 100 кВт KOTLOV XO Ceramic PRO: реальные котельные с 2018 года',
            'excerpt' => 'Большой разбор KOTLOV XO Ceramic PRO 100 кВт: реальные котельные с 2018 года, расход пеллет, самоочистка, Wi‑Fi, ресурс, монтаж и подбор.',
            'content' => $content,
            'images' => json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'tags' => json_encode([
                'пеллетные горелки',
                'пеллетная горелка 100 кВт',
                'KOTLOV XO',
                'OXI Ceramic',
                'Ceramic PRO',
                'автоматизация котельной',
                'промышленное отопление',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'meta_title' => 'Пеллетная горелка 100 кВт KOTLOV XO: обзор и монтаж',
            'meta_description' => 'KOTLOV XO Ceramic PRO 100 кВт: расход пеллет, самоочистка, Wi‑Fi, ресурс и реальные котельные с 2018 года. Цена, подбор и монтаж в Беларуси.',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $post = DB::table('blog_posts')->where('slug', self::SLUG)->first();

        if (! $post) {
            return;
        }

        $content = str_replace($this->historyProof(), '', (string) $post->content);
        $content = str_replace($this->faqAndLinks()."\n\n", '', $content);
        $content = str_replace(
            array_values(self::IMAGE_REPLACEMENTS),
            array_keys(self::IMAGE_REPLACEMENTS),
            $content
        );

        DB::table('blog_posts')->where('id', $post->id)->update([
            'title' => 'Пеллетная горелка 100 кВт для производства и склада: KOTLOV XO Ceramic PRO',
            'excerpt' => 'Технический разбор KOTLOV XO Ceramic PRO 100 кВт: самоочистка, шамотированная камера, встроенный Wi‑Fi, рыночное сравнение, монтаж и подбор.',
            'content' => $content,
            'meta_title' => 'Пеллетная горелка 100 кВт с Wi‑Fi: KOTLOV XO Ceramic PRO',
            'meta_description' => 'KOTLOV XO Ceramic PRO 100 кВт со встроенным Wi‑Fi: самоочистка, шамот, расход, сравнение с аналогами, монтаж и инженерный подбор.',
            'updated_at' => now(),
        ]);
    }

    private function historyProof(): string
    {
        return <<<'HTML'
<div data-xo-history-2018="1">
<blockquote><strong>Архив реальных объектов начинается в 2018 году.</strong> Фотографии ниже подтверждают, что горелки родственной платформы OXI/HOTTA Ceramic применялись в котельных мощностью около 100 кВт уже восемь лет назад. Это честная история развития конструкции: архивные модели не выдаются за актуальную KOTLOV XO Ceramic PRO.</blockquote>
<p class="text text-body-1">Для коммерческого отопления важны не только характеристики новой горелки, но и ремонтопригодность платформы. Архив показывает компоновку с внешним шнеком, бункером, автоматикой и адаптацией дверцы котла. Современная Ceramic PRO развивает этот подход: получила актуальный контроллер, интернет‑управление и съёмный теплонагруженный узел.</p>
</div>
HTML;
    }

    private function faqAndLinks(): string
    {
        return <<<'HTML'
<div data-xo-faq-links="1">
<h2>Частые вопросы о пеллетной горелке 100 кВт</h2>
<h3>Можно ли установить Ceramic PRO в существующий твердотопливный котёл?</h3>
<p class="text text-body-1">Да, если подходят геометрия топки, дверца, теплообменник и дымоход. До заказа нужны размеры и фотографии котла: иногда достаточно фланца, а иногда требуется изготовление новой дверцы и изменение схемы котельной.</p>
<h3>Сколько пеллет расходует горелка 100 кВт?</h3>
<p class="text text-body-1">При работе на полной мощности ориентир составляет около 20 кг/ч, но фактический средний расход обычно ниже благодаря модуляции. Точный сезонный расход считают по теплопотерям объекта, режиму работы и качеству топлива.</p>
<h3>Как часто нужна очистка?</h3>
<p class="text text-body-1">Подвижные колосники очищаются автоматически, однако зольник, теплообменник, фотодатчик и дымовой тракт всё равно требуют регламентного обслуживания. Интервал определяется зольностью пеллет и фактической нагрузкой.</p>
<h3>Что выбрать для объекта меньшей мощности?</h3>
<p class="text text-body-1">Для частных домов и небольших коммерческих объектов посмотрите технический разбор <a href="/blog/pelletnaya-gorelka-kotlov-xo-evo-26-kvt"><strong>KOTLOV XO EVO 26 кВт</strong></a> и материал о комплектах <a href="/blog/hotta-ceramik-20-30-kvt-rasprodazha-s-wifi-kontrollerom"><strong>HOTTA Ceramik 20/30 кВт</strong></a>. Практику длительной эксплуатации твердотопливной котельной разбираем в статье <a href="/blog/tverdotoplivnyy-kotel-posle-7-let-ekspluatacii"><strong>«Котёл после 7 лет работы»</strong></a>.</p>
</div>
HTML;
    }
};
