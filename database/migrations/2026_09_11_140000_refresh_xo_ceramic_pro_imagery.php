<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ARTICLE_SLUG = 'pelletnaya-gorelka-100-kvt-kotlov-xo-ceramic-pro';
    private const COVER = 'img/promotions/kotlov-xo-ceramic-pro-sale-cover-v2.webp';

    public function up(): void
    {
        $post = DB::table('blog_posts')->where('slug', self::ARTICLE_SLUG)->first();

        if (! $post) {
            return;
        }

        $content = (string) $post->content;
        $marker = 'data-xo-current-photos="1"';

        if (! str_contains($content, $marker)) {
            $anchor = '<div class="blog-image-2"><img loading="lazy" width="1400" height="1050" src="/proxy-image/product/0012/012203/cp-100_1.jpg" alt="Пеллетная горелка KOTLOV XO Ceramic PRO 100 кВт"></div>';
            $realPhotos = <<<'HTML'
<div data-xo-current-photos="1">
<div class="blog-image-2"><img loading="lazy" width="1200" height="1600" src="/img/promotions/kotlov-xo-ceramic-pro-real-firebox.jpg" alt="Реальная шамотированная топка и подвижные колосники KOTLOV XO Ceramic PRO 100 кВт"></div>
<div class="blog-image-2"><img loading="lazy" width="1200" height="1600" src="/img/promotions/kotlov-xo-ceramic-pro-real-controller.jpg" alt="Реальный контроллер XO со встроенным Wi-Fi"></div>
<p class="text text-body-1"><em>Актуальное оборудование: реальная камера сгорания с каскадом подвижных колосников и контроллер XO со встроенным Wi‑Fi.</em></p>
</div>
HTML;
            $content = str_replace($anchor, $anchor."\n".$realPhotos, $content);
        }

        $images = json_decode((string) $post->images, true) ?: [];
        $images = array_values(array_filter($images, fn ($image) => $image !== 'img/promotions/kotlov-xo-ceramic-pro-sale-cover.jpg'));
        array_unshift($images, self::COVER);

        foreach ([
            'img/promotions/kotlov-xo-ceramic-pro-real-firebox.jpg',
            'img/promotions/kotlov-xo-ceramic-pro-real-controller.jpg',
        ] as $image) {
            if (! in_array($image, $images, true)) {
                $images[] = $image;
            }
        }

        DB::table('blog_posts')->where('id', $post->id)->update([
            'cover_image' => self::COVER,
            'images' => json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'content' => $content,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('blog_posts')
            ->where('slug', self::ARTICLE_SLUG)
            ->where('cover_image', self::COVER)
            ->update([
                'cover_image' => 'img/promotions/kotlov-xo-ceramic-pro-sale-cover.jpg',
                'updated_at' => now(),
            ]);
    }
};
