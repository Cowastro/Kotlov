<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = 'kotlov-by-i-oxi-v-varshave-2025';

    public function up(): void
    {
        $post = DB::table('blog_posts')->where('slug', self::SLUG)->first();

        if (! $post) {
            return;
        }

        $content = preg_replace(
            '#\s*<div class="tf-grid-layout sm-col-2 gap-30">\s*<div class="blog-image-2">\s*<img loading="lazy" width="900" height="640" src="/img/blog/works/oxi-warsaw-2025-partners\.jpg" alt="[^"]+">\s*</div>\s*<div class="blog-image-2">\s*<img loading="lazy" width="900" height="640" src="/img/blog/works/oxi-warsaw-2025-evo\.jpg" alt="[^"]+">\s*</div>\s*</div>#s',
            '',
            (string) $post->content,
            1,
        );

        DB::table('blog_posts')
            ->where('id', $post->id)
            ->update([
                'content' => $content,
                'images' => json_encode([], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $post = DB::table('blog_posts')->where('slug', self::SLUG)->first();

        if (! $post || str_contains((string) $post->content, 'oxi-warsaw-2025-partners.jpg')) {
            return;
        }

        $block = <<<'HTML'

<div class="tf-grid-layout sm-col-2 gap-30">
    <div class="blog-image-2">
        <img loading="lazy" width="900" height="640" src="/img/blog/works/oxi-warsaw-2025-partners.jpg" alt="Встреча KOTLOV.BY с производителем пеллетных горелок OXI на выставке в Варшаве">
    </div>
    <div class="blog-image-2">
        <img loading="lazy" width="900" height="640" src="/img/blog/works/oxi-warsaw-2025-evo.jpg" alt="Пеллетные горелки OXI EVO на выставочном стенде в Варшаве">
    </div>
</div>
HTML;

        $content = str_replace('<h2>Новые пеллетные горелки EVO</h2>', $block . "\n\n" . '<h2>Новые пеллетные горелки EVO</h2>', (string) $post->content);

        DB::table('blog_posts')
            ->where('id', $post->id)
            ->update([
                'content' => $content,
                'images' => json_encode([
                    'img/blog/works/oxi-warsaw-2025-partners.jpg',
                    'img/blog/works/oxi-warsaw-2025-evo.jpg',
                ], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }
};
