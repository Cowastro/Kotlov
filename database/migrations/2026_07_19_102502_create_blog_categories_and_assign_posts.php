<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<int, array{name:string, slug:string, sort_order:int}>
     */
    private array $categories = [
        ['name' => 'Тепловые насосы', 'slug' => 'teplovye-nasosy', 'sort_order' => 10],
        ['name' => 'Котлы и отопление', 'slug' => 'kotly-i-otoplenie', 'sort_order' => 20],
        ['name' => 'Дымоходы', 'slug' => 'dymohody', 'sort_order' => 30],
        ['name' => 'Камины и печи', 'slug' => 'kaminy-i-pechi', 'sort_order' => 40],
        ['name' => 'Баня и сауна', 'slug' => 'banya-i-sauna', 'sort_order' => 50],
    ];

    /**
     * @var array<string, string>
     */
    private array $postCategories = [
        'kak-vybrat-teplovoy-nasos' => 'teplovye-nasosy',
        'teplovye-nasosy-ge-r290-vysokotemperaturnye' => 'teplovye-nasosy',
        'pelletnyy-kotel-ili-gazovyy' => 'kotly-i-otoplenie',
        'dymohod-dlya-kamina' => 'dymohody',
        'dymohody-teplov-i-sukhov-v-belarusi' => 'dymohody',
    ];

    public function up(): void
    {
        $now = now();

        foreach ($this->categories as $category) {
            DB::table('blog_categories')->updateOrInsert(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'sort_order' => $category['sort_order'],
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        $categoryIds = DB::table('blog_categories')
            ->whereIn('slug', array_column($this->categories, 'slug'))
            ->pluck('id', 'slug');

        foreach ($this->postCategories as $postSlug => $categorySlug) {
            $categoryId = $categoryIds[$categorySlug] ?? null;

            if (! $categoryId) {
                continue;
            }

            DB::table('blog_posts')
                ->where('slug', $postSlug)
                ->update([
                    'category_id' => $categoryId,
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        DB::table('blog_posts')
            ->whereIn('slug', array_keys($this->postCategories))
            ->update([
                'category_id' => null,
                'updated_at' => now(),
            ]);

        DB::table('blog_categories')
            ->whereIn('slug', array_column($this->categories, 'slug'))
            ->delete();
    }
};
