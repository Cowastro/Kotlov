<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ARTICLE_SLUG = 'invicta-shaman-novinka-dostupen-k-zakazu';

    public function up(): void
    {
        DB::table('blog_posts')
            ->where('slug', self::ARTICLE_SLUG)
            ->update([
                'is_published' => true,
                'published_at' => '2026-10-20 00:00:00',
                'updated_at' => now(),
            ]);

        Cache::forget('home:data');
    }

    public function down(): void
    {
        DB::table('blog_posts')
            ->where('slug', self::ARTICLE_SLUG)
            ->update([
                'published_at' => '2026-10-20 14:30:00',
                'updated_at' => now(),
            ]);

        Cache::forget('home:data');
    }
};
