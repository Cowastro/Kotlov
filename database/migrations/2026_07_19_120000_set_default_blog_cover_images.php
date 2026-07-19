<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $covers = [
        'kak-vybrat-teplovoy-nasos' => 'img/blog/blog-heatpump.jpg',
        'pelletnyy-kotel-ili-gazovyy' => 'img/blog/blog-boiler.jpg',
        'dymohod-dlya-kamina' => 'img/blog/blog-chimney.jpg',
    ];

    public function up(): void
    {
        foreach ($this->covers as $slug => $cover) {
            DB::table('blog_posts')
                ->where('slug', $slug)
                ->where(function ($query) {
                    $query->whereNull('cover_image')
                        ->orWhere('cover_image', '');
                })
                ->update(['cover_image' => $cover]);
        }
    }

    public function down(): void
    {
        foreach ($this->covers as $slug => $cover) {
            DB::table('blog_posts')
                ->where('slug', $slug)
                ->where('cover_image', $cover)
                ->update(['cover_image' => null]);
        }
    }
};
