<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = 'dymohody-teplov-i-sukhov-v-belarusi';
    private const OLD_IMAGE = '/img/blog/works/chimney-work-dym2.jpg';
    private const NEW_IMAGE = '/img/blog/works/chimney-work-tis2.jpg';

    public function up(): void
    {
        $post = DB::table('blog_posts')->where('slug', self::SLUG)->first();

        if (! $post || ! str_contains((string) $post->content, self::OLD_IMAGE)) {
            return;
        }

        DB::table('blog_posts')
            ->where('id', $post->id)
            ->update([
                'content' => str_replace(
                    self::OLD_IMAGE,
                    self::NEW_IMAGE,
                    (string) $post->content,
                ),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $post = DB::table('blog_posts')->where('slug', self::SLUG)->first();

        if (! $post || ! str_contains((string) $post->content, self::NEW_IMAGE)) {
            return;
        }

        DB::table('blog_posts')
            ->where('id', $post->id)
            ->update([
                'content' => str_replace(
                    self::NEW_IMAGE,
                    self::OLD_IMAGE,
                    (string) $post->content,
                ),
                'updated_at' => now(),
            ]);
    }
};
