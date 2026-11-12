<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Lida dacha article: a wide 1600×900 cover (stove and chimney over the roof side by side) instead of the cut
 * portrait photo, and retouched photos — plain walls instead of the patterned wallpaper, a clean skirting and floor,
 * an even grey roof — with a note that the finish is a visualisation, as in the other Lida article.
 */
return new class extends Migration
{
    private const SLUG = 'dymohod-dlya-pechi-na-dache-lida';

    public function up(): void
    {
        $post = DB::table('blog_posts')->where('slug', self::SLUG)->first(['id', 'content']);
        if (! $post) {
            return;
        }
        $content = str_replace(
            ['pech-i-dymohod-v-dome.webp" alt', 'pech-i-dymohod-v-dome.webp">', 'dymohod-nad-krovley.webp" alt', 'dymohod-nad-krovley.webp">', 'width="835" height="1225"'],
            ['pech-i-dymohod-v-dome-2.webp" alt', 'pech-i-dymohod-v-dome-2.webp">', 'dymohod-nad-krovley-2.webp" alt', 'dymohod-nad-krovley-2.webp">', 'width="811" height="1225"'],
            (string) $post->content,
        );
        $note = '<p><small>На снимках показана визуализация финишной отделки стен, пола и кровли.</small></p>';
        if (! str_contains($content, 'визуализация финишной отделки')) {
            $content = preg_replace('~(</figure>\s*</div>)~u', '$1'."\n".$note, $content, 1) ?? $content;
        }
        DB::table('blog_posts')->where('id', $post->id)->update([
            'cover_image' => 'img/blog/works/lida-dacha-pech/lida-dacha-cover.webp',
            'content' => $content,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('blog_posts')->where('slug', self::SLUG)->update(['cover_image' => 'img/blog/works/lida-dacha-pech/pech-i-dymohod-v-dome.webp']);
    }
};
