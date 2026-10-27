<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const GROUPS = [
        'teplovye-nasosy-vozduh-voda' => ['name' => 'Тепловые насосы воздух–вода', 'sort_order' => 10],
        'geotermalnye-teplovye-nasosy' => ['name' => 'Геотермальные тепловые насосы', 'sort_order' => 20],
        'teplovye-nasosy-dlya-basseynov' => ['name' => 'Тепловые насосы для бассейнов', 'sort_order' => 30],
        'komplektuyushchie-dlya-teplovykh-nasosov' => ['name' => 'Комплектующие для тепловых насосов', 'sort_order' => 40],
    ];

    public function up(): void
    {
        $rootId = $this->categoryId('teplovyie-nasosyi');

        if (! $rootId) {
            return;
        }

        DB::table('categories')->where('id', $rootId)->update([
            'name' => 'Тепловые насосы',
            'h1' => 'Тепловые насосы',
            'updated_at' => now(),
        ]);

        $groupIds = [];
        foreach (self::GROUPS as $slug => $group) {
            $groupIds[$slug] = $this->upsertChild($rootId, $slug, $group['name'], $group['sort_order']);
        }

        $products = DB::table('products')
            ->where('category_id', $rootId)
            ->select('id', 'name')
            ->get();

        foreach ($products as $product) {
            $name = mb_strtolower((string) $product->name);
            $slug = match (true) {
                str_contains($name, 'для бассейн') => 'teplovye-nasosy-dlya-basseynov',
                str_contains($name, 'ecoheat'),
                str_contains($name, 'земля-вода'),
                str_contains($name, 'земля–вода'),
                str_contains($name, 'земля — вода') => 'geotermalnye-teplovye-nasosy',
                str_contains($name, 'regulusbox'),
                str_contains($name, 'насосная группа'),
                ! str_contains($name, 'тепловой насос') => 'komplektuyushchie-dlya-teplovykh-nasosov',
                default => 'teplovye-nasosy-vozduh-voda',
            };

            DB::table('products')->where('id', $product->id)->update([
                'category_id' => $groupIds[$slug],
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $rootId = $this->categoryId('teplovyie-nasosyi');

        if (! $rootId) {
            return;
        }

        $groupIds = DB::table('categories')
            ->where('parent_id', $rootId)
            ->whereIn('slug', array_keys(self::GROUPS))
            ->pluck('id');

        DB::table('products')->whereIn('category_id', $groupIds)->update([
            'category_id' => $rootId,
            'updated_at' => now(),
        ]);

        DB::table('categories')->whereIn('id', $groupIds)->update([
            'is_active' => false,
            'updated_at' => now(),
        ]);
    }

    private function upsertChild(int $parentId, string $slug, string $name, int $sortOrder): int
    {
        $values = [
            'parent_id' => $parentId,
            'name' => $name,
            'h1' => $name,
            'type' => 'child',
            'sort_order' => $sortOrder,
            'is_active' => true,
            'updated_at' => now(),
        ];

        if (DB::table('categories')->where('slug', $slug)->exists()) {
            DB::table('categories')->where('slug', $slug)->update($values);
        } else {
            DB::table('categories')->insert($values + ['slug' => $slug, 'created_at' => now()]);
        }

        return (int) DB::table('categories')->where('slug', $slug)->value('id');
    }

    private function categoryId(string $slug): ?int
    {
        $id = DB::table('categories')->where('slug', $slug)->value('id');

        return $id ? (int) $id : null;
    }
};
