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
        BlogPost::firstOrCreate(['slug' => 'pech-kamin-everest-chernyy-dymohod-lida'], [
            'city_id' => $city->id,
            'category_id' => $category->id,
            'title' => 'Печь-камин EVEREST и чёрный дымоход «Теплов и Сухов»: фото и видео работы',
            'excerpt' => 'Новая работа команды KOTLOV в Лиде: печь-камин EVEREST, чёрный дымоход «Теплов и Сухов», подвод воздуха и задняя заслонка. Показываем фотографии и видео печи во время топки.',
            'content' => file_get_contents(resource_path('content/lida-everest-chimney/article.html')),
            'cover_image' => 'img/blog/works/lida-everest-chimney/cover.webp',
            'images' => ['img/blog/works/lida-everest-chimney/stove.webp', 'img/blog/works/lida-everest-chimney/chimney.webp'],
            'tags' => ['Лида', 'EVEREST', 'Теплов и Сухов', 'Печи-камины', 'Дымоходы', 'Монтаж', 'Видео'],
            'meta_title' => 'Печь EVEREST и чёрный дымоход: фото и видео | KOTLOV Лида',
            'meta_description' => 'Печь-камин EVEREST и чёрный дымоход «Теплов и Сухов»: фотографии монтажа и видео огня. Подбор печи и дымохода в KOTLOV Лида, Калинина, 57.',
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Preserve the published article and any subsequent editorial changes.
    }
};
