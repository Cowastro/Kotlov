<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = 'teplovye-nasosy-ge-r290-vysokotemperaturnye';

    private const REPLACEMENTS = [
        '/img/blog/works/ge-r290-heat-pump.png' => '/img/blog/works/ge-r290-heat-pump-hp4.jpg',
        '/img/blog/works/heat-pump-work-hp2.jpg' => '/img/blog/works/ge-r290-heat-pump-hp3.jpg',
    ];

    public function up(): void
    {
        $post = DB::table('blog_posts')->where('slug', self::SLUG)->first();

        if (! $post) {
            return;
        }

        $content = (string) $post->content;
        $updated = str_replace(array_keys(self::REPLACEMENTS), array_values(self::REPLACEMENTS), $content);

        if ($updated === $content) {
            return;
        }

        DB::table('blog_posts')
            ->where('id', $post->id)
            ->update([
                'content' => $updated,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $post = DB::table('blog_posts')->where('slug', self::SLUG)->first();

        if (! $post) {
            return;
        }

        $content = (string) $post->content;
        $updated = str_replace(array_values(self::REPLACEMENTS), array_keys(self::REPLACEMENTS), $content);

        if ($updated === $content) {
            return;
        }

        DB::table('blog_posts')
            ->where('id', $post->id)
            ->update([
                'content' => $updated,
                'updated_at' => now(),
            ]);
    }
};
