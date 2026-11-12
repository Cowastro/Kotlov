<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Wide 1600×900 covers for the ДЕЛЬТА ПРАЙМ 1100 news and the cast iron lining article: the square promo picture was
 * cut at the top in the 16:9 article header. The promo stays inside the news text, shown whole.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('blog_posts')->where('slug', 'novinka-ekokamin-delta-praim-1100-chugunnaya-topochnaya-kamera')->update([
            'cover_image' => 'img/blog/ecokamin-prime/delta-prime-1100-chugun-cover.webp',
            'updated_at' => now(),
        ]);
        DB::table('blog_posts')->where('slug', 'chugunnaya-futerovka-kamina-preimushchestva')->update([
            'cover_image' => 'img/blog/chugunnaya-futerovka/chugunnaya-futerovka-cover.webp',
            'updated_at' => now(),
        ]);
        Cache::forget('home:data');
    }

    public function down(): void
    {
        DB::table('blog_posts')->where('slug', 'novinka-ekokamin-delta-praim-1100-chugunnaya-topochnaya-kamera')
            ->update(['cover_image' => 'img/blog/ecokamin-prime/delta-prime-1100-chugun-novinka.webp']);
        DB::table('blog_posts')->where('slug', 'chugunnaya-futerovka-kamina-preimushchestva')
            ->update(['cover_image' => 'img/blog/chugunnaya-futerovka/preimushchestva-chugunnoy-futerovki.webp']);
    }
};
