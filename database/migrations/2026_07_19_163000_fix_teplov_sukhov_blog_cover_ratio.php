<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('blog_posts')
            ->where('slug', 'dymohody-teplov-i-sukhov-v-belarusi')
            ->update([
                'cover_image' => 'img/blog/teplov-sukhov-chimneys-cover-1800x1040.jpg',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('blog_posts')
            ->where('slug', 'dymohody-teplov-i-sukhov-v-belarusi')
            ->update([
                'cover_image' => 'img/blog/blog-chimney.jpg',
                'updated_at' => now(),
            ]);
    }
};
