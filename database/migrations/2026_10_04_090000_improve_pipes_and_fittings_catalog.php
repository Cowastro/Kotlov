<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $categories = [
            'truby-i-fitingi' => [
                'h1' => 'Трубы и фитинги',
                'meta_title' => 'Трубы и фитинги — купить в %city% | KOTLOV',
                'meta_description' => 'Трубы и фитинги для отопления, водоснабжения и тёплого пола. Varmega и другие бренды. Цены, наличие и доставка по Беларуси.',
            ],
            'truby-iz-sshitogo-polietilena' => [
                'h1' => 'Трубы из сшитого полиэтилена',
                'meta_title' => 'Трубы из сшитого полиэтилена — купить в %city% | KOTLOV',
                'meta_description' => 'Трубы из сшитого полиэтилена для отопления и водяного тёплого пола. Диаметры, цены, наличие и доставка по Беларуси.',
            ],
            'rezbovye-fitingi' => [
                'h1' => 'Резьбовые фитинги',
                'meta_title' => 'Резьбовые фитинги — купить в %city% | KOTLOV',
                'meta_description' => 'Резьбовые фитинги для отопления и водоснабжения: муфты, тройники, угольники и переходники. Цены и наличие.',
            ],
            'vodyanoy-teplyy-pol' => [
                'h1' => 'Водяной тёплый пол',
                'meta_title' => 'Водяной тёплый пол — купить в %city% | KOTLOV',
                'meta_description' => 'Трубы, фитинги и комплектующие для водяного тёплого пола. Цены, наличие, доставка по Беларуси и помощь с подбором.',
            ],
            'press-fitingi' => [
                'h1' => 'Пресс-фитинги',
                'meta_title' => 'Пресс-фитинги — купить в %city% | KOTLOV',
                'meta_description' => 'Пресс-фитинги для систем отопления и водоснабжения. Выбор по диаметру, профилю, материалу и назначению.',
            ],
            'kompressionnye-fitingi' => [
                'h1' => 'Компрессионные фитинги',
                'meta_title' => 'Компрессионные фитинги — купить в %city% | KOTLOV',
                'meta_description' => 'Компрессионные фитинги для труб отопления и водоснабжения. Цены, наличие, доставка и помощь с подбором соединений.',
            ],
            'krepleniya-dlya-trub' => [
                'h1' => 'Крепления для труб',
                'meta_title' => 'Крепления для труб — купить в %city% | KOTLOV',
                'meta_description' => 'Клипсы, хомуты и опоры для крепления труб. Подбор по диаметру, материалу и способу монтажа. Доставка по Беларуси.',
            ],
        ];

        foreach ($categories as $slug => $data) {
            DB::table('categories')
                ->where('slug', $slug)
                ->update($data + ['updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $categories = [
            'truby-i-fitingi' => [
                'h1' => 'Трубы и фитинги для отопления',
                'meta_title' => 'Купить трубы отопления и фитинги для частного дома. Цены в Минске, отзывы, каталог. Доставка по РБ',
                'meta_description' => 'Продажа труб и фитингов для котлов отопления в частном доме. Металлические, полипропиленовые и др. Каталог с ценами, фото и отзывами. Доставка по Беларуси!',
            ],
            'truby-iz-sshitogo-polietilena' => ['h1' => 'Трубы из сшитого полиэтилена', 'meta_title' => null, 'meta_description' => null],
            'rezbovye-fitingi' => ['h1' => 'Резьбовые фитинги', 'meta_title' => null, 'meta_description' => null],
            'vodyanoy-teplyy-pol' => ['h1' => 'Водяной теплый пол', 'meta_title' => null, 'meta_description' => null],
            'press-fitingi' => ['h1' => 'Пресс-фитинги', 'meta_title' => null, 'meta_description' => null],
            'kompressionnye-fitingi' => ['h1' => 'Компрессионные фитинги', 'meta_title' => null, 'meta_description' => null],
            'krepleniya-dlya-trub' => ['h1' => 'Крепления для труб', 'meta_title' => null, 'meta_description' => null],
        ];

        foreach ($categories as $slug => $data) {
            DB::table('categories')
                ->where('slug', $slug)
                ->update($data + ['updated_at' => now()]);
        }
    }
};
