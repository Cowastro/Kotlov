<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The ЭкоКамин ПРАЙМ hero slide becomes a magazine-cover layout (the art is public/img/banners/ecokamin-prime), and the
 * article photos are the sharp studio shots of ecokamin.ru with the fire set in, so their sizes change.
 */
return new class extends Migration
{
    private const SIZES = [
        'width="739" height="965" src="/img/blog/ecokamin-prime/delta-prime-1100.webp"' => 'width="1000" height="1177" src="/img/blog/ecokamin-prime/delta-prime-1100.webp"',
        'width="684" height="1045" src="/img/blog/ecokamin-prime/vega-prime-1000.webp"' => 'width="913" height="1400" src="/img/blog/ecokamin-prime/vega-prime-1000.webp"',
        'width="762" height="970" src="/img/blog/ecokamin-prime/delta-prime-1000.webp"' => 'width="1000" height="1320" src="/img/blog/ecokamin-prime/delta-prime-1000.webp"',
    ];

    public function up(): void
    {
        $post = DB::table('blog_posts')->where('slug', 'ekokamin-praim-novye-topki-vega-i-delta')->first(['id', 'content']);
        if ($post) {
            DB::table('blog_posts')->where('id', $post->id)->update([
                'content' => strtr((string) $post->content, self::SIZES),
                'updated_at' => now(),
            ]);
        }
        DB::table('banners')->where('image', 'banners/ecokamin-prime-launch')->update([
            'subtitle' => 'Новая серия каминных топок',
            'updated_at' => now(),
        ]);
        Cache::forget('home:data');
    }

    public function down(): void
    {
    }
};
