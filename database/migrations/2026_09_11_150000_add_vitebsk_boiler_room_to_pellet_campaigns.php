<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const IMAGE = 'img/promotions/hotta-oxi-ceramic-100-vitebsk-region-2018.jpg';

    public function up(): void
    {
        $this->addToPost(
            'pelletnaya-gorelka-100-kvt-kotlov-xo-ceramic-pro',
            'data-xo-vitebsk-2018="1"',
            '<h2>Сравнение с другими горелками 100 кВт</h2>',
            <<<'HTML'
<div data-xo-vitebsk-2018="1">
<div class="blog-image-2"><img loading="lazy" width="1800" height="1350" src="/img/promotions/hotta-oxi-ceramic-100-vitebsk-region-2018.jpg" alt="Пеллетная котельная 100 кВт с горелкой HOTTA OXI Ceramic в Витебской области, 16 октября 2018 года"></div>
<p class="text text-body-1"><em>Витебская область, 16 октября 2018 года: котельная 100 кВт с двумя котловыми агрегатами, внешней шнековой подачей и горелками платформы OXI/HOTTA. Архивный объект показывает практическое применение старшего поколения Ceramic; он не заявлен как монтаж актуальной KOTLOV XO Ceramic PRO.</em></p>
</div>
HTML
        );

        $this->addToPost(
            'hotta-ceramik-20-30-kvt-rasprodazha-s-wifi-kontrollerom',
            'data-hotta-vitebsk-2018="1"',
            '<h2>Совместимость с котлом важнее площади дома</h2>',
            <<<'HTML'
<div data-hotta-vitebsk-2018="1">
<p class="text text-body-1">Витебская область, 16 октября 2018 года: ещё один архивный объект на 100 кВт. На фотографии видны два котловых агрегата, горелки, внешняя шнековая подача и отдельная автоматика.</p>
<div class="blog-image-2"><img loading="lazy" width="1800" height="1350" src="/img/promotions/hotta-oxi-ceramic-100-vitebsk-region-2018.jpg" alt="Котельная 100 кВт с горелкой HOTTA OXI в Витебской области, 2018 год"></div>
</div>
HTML
        );
    }

    private function addToPost(string $slug, string $marker, string $anchor, string $block): void
    {
        $post = DB::table('blog_posts')->where('slug', $slug)->first();

        if (! $post) {
            return;
        }

        $content = (string) $post->content;

        if (! str_contains($content, $marker) && str_contains($content, $anchor)) {
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
        // Контентные доказательства не удаляем автоматически из опубликованных материалов.
    }
};
