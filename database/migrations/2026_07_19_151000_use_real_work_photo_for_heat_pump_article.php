<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = 'kak-vybrat-teplovoy-nasos';
    private const IMAGE = 'img/blog/works/heat-pump-real-work-instagram-DO5TCrgCPcM-4.jpg';

    public function up(): void
    {
        $post = DB::table('blog_posts')
            ->where('slug', self::SLUG)
            ->first(['content']);

        if (! $post) {
            return;
        }

        $content = (string) $post->content;
        $content = $this->replaceOldImageGrid($content);

        DB::table('blog_posts')
            ->where('slug', self::SLUG)
            ->update([
                'cover_image' => self::IMAGE,
                'content' => $content,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('blog_posts')
            ->where('slug', self::SLUG)
            ->update([
                'cover_image' => 'img/blog/blog-heatpump.jpg',
                'updated_at' => now(),
            ]);
    }

    private function replaceOldImageGrid(string $content): string
    {
        $gridStart = strpos($content, '<div class="tf-grid-layout sm-col-2 gap-30">');

        if ($gridStart === false) {
            return str_replace('/img/hero/heatpump-hero.jpg', '/'.self::IMAGE, $content);
        }

        $secondImage = strpos($content, '/img/popular/heatpump.jpg', $gridStart);

        if ($secondImage === false) {
            return str_replace('/img/hero/heatpump-hero.jpg', '/'.self::IMAGE, $content);
        }

        $gridEnd = strpos($content, '</div>', $secondImage);
        $gridEnd = $gridEnd === false ? false : strpos($content, '</div>', $gridEnd + 6);

        if ($gridEnd === false) {
            return str_replace('/img/hero/heatpump-hero.jpg', '/'.self::IMAGE, $content);
        }

        $replacement = <<<'HTML'
<figure class="kotlov-real-work-photo">
    <img loading="lazy" width="900" height="620" src="/img/blog/works/heat-pump-real-work-instagram-DO5TCrgCPcM-4.jpg" alt="Реальный монтаж теплового насоса KOTLOV.BY">
    <figcaption>Реальный объект KOTLOV.BY: наружный блок теплового насоса после монтажа.</figcaption>
</figure>
HTML;

        return substr($content, 0, $gridStart)
            .$replacement
            .substr($content, $gridEnd + 6);
    }
};
