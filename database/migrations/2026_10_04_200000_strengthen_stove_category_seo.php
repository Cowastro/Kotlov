<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $categories = [
            'pechki' => [
                'h1' => 'Печи для дома и дачи',
                'meta_title' => 'Печи для дома: цены, купить в %city% | KOTLOV',
                'meta_description' => 'Печи для дома и дачи: дровяные, стальные и чугунные модели. Сравните цены, мощность и площадь обогрева. Доставка по Беларуси.',
            ],
            'pechi-kaminy' => [
                'h1' => 'Печи-камины для дома',
                'meta_title' => 'Печи-камины: цены, купить в %city% | KOTLOV',
                'meta_description' => 'Печи-камины из стали и чугуна для отопления дома. Цены, наличие, площадь обогрева, подбор дымохода и доставка по Беларуси.',
            ],
            'pechi' => [
                'h1' => 'Дровяные отопительные печи',
                'meta_title' => 'Дровяные печи: цены, купить в %city% | KOTLOV',
                'meta_description' => 'Дровяные отопительные печи для дома и дачи. Сравните цены, материал, мощность и площадь обогрева. Подбор печи и дымохода.',
            ],
            'peci-drovianye-otopitelnye' => [
                'h1' => 'Дровяные отопительные печи',
                'meta_title' => 'Дровяные печи: цены, купить в %city% | KOTLOV',
                'meta_description' => 'Дровяные отопительные печи для дома и дачи. Сравните цены, материал, мощность и площадь обогрева. Подбор печи и дымохода.',
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
        // SEO content migrations intentionally keep the latest reviewed copy.
    }
};
