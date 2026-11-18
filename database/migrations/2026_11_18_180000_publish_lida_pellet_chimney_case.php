<?php

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\City;
use App\Models\PartnerBranch;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $city = City::where('slug', 'lida')->where('is_active', true)->where('storefront_enabled', true)->first();
        if (! $city || ! PartnerBranch::query()->visibleIn($city)->exists()) {
            return;
        }

        $category = BlogCategory::firstOrCreate(['slug' => 'montazh-i-obekty'], ['name' => 'Монтаж и объекты', 'is_active' => true]);
        BlogPost::firstOrCreate(['slug' => 'dymohod-teplov-suhov-pelletnyy-kotel-lida'], [
            'city_id' => $city->id,
            'category_id' => $category->id,
            'title' => 'Дымоход «Теплов и Сухов» для пеллетного котла',
            'excerpt' => 'Показываем подключение пеллетного котла и наружный дымоход «Теплов и Сухов». Фото работы, подготовка к подбору комплекта и контакты магазина KOTLOV в Лиде.',
            'content' => file_get_contents(resource_path('content/lida-pellet-chimney/article.html')),
            'cover_image' => 'img/blog/works/lida-pellet-chimney/cover.webp',
            'images' => array_map(fn ($name) => 'img/blog/works/lida-pellet-chimney/'.$name.'.webp', ['pellet-boiler', 'boiler-connection', 'chimney-exterior', 'chimney-overview']),
            'tags' => ['Лида', 'Теплов и Сухов', 'Дымоходы', 'Пеллетные котлы', 'Монтаж'],
            'meta_title' => 'Дымоход для пеллетного котла — монтаж | KOTLOV Лида',
            'meta_description' => 'Фото монтажа дымохода «Теплов и Сухов» для пеллетного котла. Подбор комплекта в KOTLOV Лида: Калинина, 57. Пришлите модель котла и фото объекта.',
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Preserve the publication and subsequent editorial changes.
    }
};
