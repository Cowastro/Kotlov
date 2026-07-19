<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $post = DB::table('blog_posts')
            ->where('slug', 'dymohod-dlya-kamina')
            ->first(['content']);

        if (! $post) {
            return;
        }

        DB::table('blog_posts')
            ->where('slug', 'dymohod-dlya-kamina')
            ->update([
                'content' => $this->addPhotoBlock((string) $post->content),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('blog_posts')
            ->where('slug', 'dymohod-dlya-kamina')
            ->update([
                'updated_at' => now(),
            ]);
    }

    private function addPhotoBlock(string $content): string
    {
        if (str_contains($content, 'chimney-work-dym1.jpg')) {
            return $content;
        }

        $photoBlock = <<<'HTML'

<div class="tf-grid-layout sm-col-2 gap-30">
    <div class="blog-image-2">
        <img loading="lazy" width="900" height="640" src="/img/blog/works/chimney-work-dym1.jpg" alt="Наружный нержавеющий дымоход на фасаде частного дома">
    </div>
    <div class="blog-image-2">
        <img loading="lazy" width="900" height="640" src="/img/blog/works/chimney-work-dym2.jpg" alt="Внутренний участок дымохода камина в деревянном доме">
    </div>
</div>
HTML;

        $needle = '</blockquote>';
        $position = strpos($content, $needle);

        if ($position === false) {
            return $photoBlock."\n\n".$content;
        }

        return substr($content, 0, $position + strlen($needle))
            .$photoBlock
            .substr($content, $position + strlen($needle));
    }
};
