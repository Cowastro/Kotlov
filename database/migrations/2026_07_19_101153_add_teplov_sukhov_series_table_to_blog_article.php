<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = 'dymohody-teplov-i-sukhov-v-belarusi';
    private const IMAGE = '/img/blog/works/teplov-sukhov-series-table.png';

    public function up(): void
    {
        $post = DB::table('blog_posts')->where('slug', self::SLUG)->first();

        if (! $post || str_contains((string) $post->content, self::IMAGE)) {
            return;
        }

        $block = <<<'HTML'

<div class="blog-image-2" style="margin:34px 0">
    <div style="overflow-x:auto;border:1px solid #e5e7eb;border-radius:16px;background:#fff;padding:10px">
        <img loading="lazy" width="1024" height="449" src="/img/blog/works/teplov-sukhov-series-table.png" alt="Сравнительная таблица систем дымоходов Теплов и Сухов: Феррит, Стандарт, Промо и Энерго">
    </div>
    <p class="text text-body-3" style="margin-top:10px;color:#6b7280">Сравнение основных систем Теплов и Сухов по стали, толщине, изоляции, температуре газов и назначению.</p>
</div>
HTML;

        $content = (string) $post->content;

        if (str_contains($content, '<blockquote>')) {
            $content = preg_replace('/<blockquote>/', $block . "\n\n" . '<blockquote>', $content, 1);
        } else {
            $content .= $block;
        }

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

        if (! $post || ! str_contains((string) $post->content, self::IMAGE)) {
            return;
        }

        $content = preg_replace(
            '#\n?<div class="blog-image-2" style="margin:34px 0">\s*<div style="overflow-x:auto;border:1px solid \#e5e7eb;border-radius:16px;background:\#fff;padding:10px">\s*<img loading="lazy" width="1024" height="449" src="/img/blog/works/teplov-sukhov-series-table\.png" alt="[^"]+">\s*</div>\s*<p class="text text-body-3" style="margin-top:10px;color:\#6b7280">[^<]+</p>\s*</div>\n?#s',
            '',
            (string) $post->content,
            1,
        );

        DB::table('blog_posts')
            ->where('id', $post->id)
            ->update([
                'content' => $content,
                'updated_at' => now(),
            ]);
    }
};
