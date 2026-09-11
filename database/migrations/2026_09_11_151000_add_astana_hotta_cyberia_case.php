<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = 'hotta-ceramik-20-30-kvt-rasprodazha-s-wifi-kontrollerom';
    private const IMAGE = 'img/promotions/hotta-ceramik/hotta-cyberia-40-astana-2018.jpg';

    public function up(): void
    {
        $post = DB::table('blog_posts')->where('slug', self::SLUG)->first();

        if (! $post) {
            return;
        }

        $content = (string) $post->content;
        $marker = 'data-hotta-astana-2018="1"';
        $anchor = '<h2>Совместимость с котлом важнее площади дома</h2>';

        if (! str_contains($content, $marker) && str_contains($content, $anchor)) {
            $block = <<<'HTML'
<div data-hotta-astana-2018="1">
<p class="text text-body-1">Астана, Казахстан, 31 октября 2018 года: горелка HOTTA Cyberia 40 кВт установлена в котёл Kronas. На реальном объекте видны бункер, шнековая подача пеллет, гибкий страховочный рукав и отдельная автоматика.</p>
<div class="blog-image-2"><img loading="lazy" width="1800" height="1350" src="/img/promotions/hotta-ceramik/hotta-cyberia-40-astana-2018.jpg" alt="Пеллетная горелка HOTTA Cyberia 40 кВт в котле Kronas, Астана, 31 октября 2018 года"></div>
<p class="text text-body-1"><em>Это архивный объект модели 40 кВт, а не один из акционных комплектов 20/30 кВт. Фотография подтверждает практическую историю серии HOTTA Cyberia/Ceramik.</em></p>
</div>
HTML;
            $content = str_replace($anchor, $block."\n\n".$anchor, $content);
        }

        $images = json_decode((string) $post->images, true) ?: [];

        if (! in_array(self::IMAGE, $images, true)) {
            $images[] = self::IMAGE;
        }

        DB::table('blog_posts')->where('id', $post->id)->update([
            'content' => $content,
            'images' => json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Не удаляем опубликованный контент автоматически.
    }
};
