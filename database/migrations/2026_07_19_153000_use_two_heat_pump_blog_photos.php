<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = 'kak-vybrat-teplovoy-nasos';

    public function up(): void
    {
        $post = DB::table('blog_posts')
            ->where('slug', self::SLUG)
            ->first(['content']);

        if (! $post) {
            return;
        }

        DB::table('blog_posts')
            ->where('slug', self::SLUG)
            ->update([
                'cover_image' => 'img/blog/blog-heatpump.jpg',
                'content' => $this->replacePhotoBlock((string) $post->content),
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

    private function replacePhotoBlock(string $content): string
    {
        $replacement = <<<'HTML'
<div class="tf-grid-layout sm-col-2 gap-30">
    <div class="blog-image-2">
        <img loading="lazy" width="900" height="640" src="/img/blog/works/heat-pump-work-hp2.jpg" alt="Тепловой насос у фасада частного дома">
    </div>
    <div class="blog-image-2">
        <img loading="lazy" width="900" height="640" src="/img/blog/works/heat-pump-work-hp1.jpg" alt="Наружный блок теплового насоса на объекте">
    </div>
</div>
HTML;

        $pattern = '~<figure class="kotlov-real-work-photo">.*?</figure>~s';

        if (preg_match($pattern, $content)) {
            return preg_replace($pattern, $replacement, $content, 1) ?? $content;
        }

        $oldGridPattern = '~<div class="tf-grid-layout sm-col-2 gap-30">\s*<div class="blog-image-2">.*?</div>\s*</div>~s';

        if (preg_match($oldGridPattern, $content)) {
            return preg_replace($oldGridPattern, $replacement, $content, 1) ?? $content;
        }

        return $content;
    }
};
