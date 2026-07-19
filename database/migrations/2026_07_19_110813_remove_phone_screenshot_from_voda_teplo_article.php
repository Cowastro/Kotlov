<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = 'kotlov-by-na-vystavke-voda-i-teplo-2026';

    public function up(): void
    {
        $post = DB::table('blog_posts')->where('slug', self::SLUG)->first();

        if (! $post || ! str_contains((string) $post->content, 'voda-teplo-2026-poster.jpg')) {
            return;
        }

        $content = (string) $post->content;

        $content = preg_replace(
            '#\s*<div class="blog-image-2">\s*<img loading="lazy" width="900" height="640" src="/img/blog/works/voda-teplo-2026-poster\.jpg" alt="[^"]+">\s*</div>#s',
            "\n" . $this->infoBlock(),
            $content,
            1,
        );

        DB::table('blog_posts')
            ->where('id', $post->id)
            ->update([
                'content' => $content,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $post = DB::table('blog_posts')->where('slug', self::SLUG)->first();

        if (! $post || str_contains((string) $post->content, 'voda-teplo-2026-poster.jpg')) {
            return;
        }

        $content = str_replace(
            $this->infoBlock(),
            '<div class="blog-image-2">
        <img loading="lazy" width="900" height="640" src="/img/blog/works/voda-teplo-2026-poster.jpg" alt="Анонс участия KOTLOV.BY в выставке Вода и тепло 2026">
    </div>',
            (string) $post->content,
        );

        DB::table('blog_posts')
            ->where('id', $post->id)
            ->update([
                'content' => $content,
                'updated_at' => now(),
            ]);
    }

    private function infoBlock(): string
    {
        return <<<'HTML'
<div class="kotlov-article-event-card">
    <span class="event-eyebrow">Архив события</span>
    <strong>«Вода и тепло 2026»</strong>
    <p>14–17 апреля 2026 · Минск, Футбольный манеж</p>
    <p>KOTLOV.BY представила на стенде тепловые насосы GE, пеллетные решения KOTLOV XO, камины Ecokamin и дымоходы Теплов и Сухов.</p>
</div>
HTML;
    }
};
