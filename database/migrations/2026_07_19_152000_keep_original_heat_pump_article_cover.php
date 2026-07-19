<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('blog_posts')
            ->where('slug', 'kak-vybrat-teplovoy-nasos')
            ->update([
                'cover_image' => 'img/blog/blog-heatpump.jpg',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('blog_posts')
            ->where('slug', 'kak-vybrat-teplovoy-nasos')
            ->update([
                'cover_image' => 'img/blog/works/heat-pump-real-work-instagram-DO5TCrgCPcM-4.jpg',
                'updated_at' => now(),
            ]);
    }
};
