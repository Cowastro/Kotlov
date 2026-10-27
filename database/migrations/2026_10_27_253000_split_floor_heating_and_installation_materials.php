<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const FLOOR_HEATING_GROUPS = [
        'nagrevatelnye-maty' => [
            'name' => 'Нагревательные маты',
            'sort_order' => 10,
            'skus' => [
                'KOTLOV-001803', 'KOTLOV-001808', 'KOTLOV-001764',
                'KOTLOV-001833', 'KOTLOV-001838', 'KOTLOV-001839',
                'KOTLOV-001744', 'KOTLOV-001746', 'KOTLOV-001756',
                'PS-000.18655', 'PS-000.18981', 'PS-000.18982', 'PS-000.18749',
            ],
        ],
        'nagrevatelnye-kabeli' => [
            'name' => 'Нагревательные кабели',
            'sort_order' => 20,
            'skus' => [
                'KOTLOV-001863', 'KOTLOV-001866', 'KOTLOV-001779', 'KOTLOV-001780',
                'PS-000.18746', 'PS-000.18745', 'PS-000.18747', 'PS-000.18748',
            ],
        ],
        'ik-plenochnyj-pol' => [
            'name' => 'Инфракрасный плёночный пол',
            'sort_order' => 30,
            'skus' => [
                'PS-000.18730', 'PS-000.18731', 'PS-000.18728', 'PS-000.18729',
            ],
        ],
    ];

    private const INSTALLATION_MATERIAL_GROUPS = [
        'pechnye-i-kaminnye-germetiki' => [
            'name' => 'Печные и каминные герметики',
            'sort_order' => 10,
            'skus' => ['KOTLOV-030272', 'PS-009.746', 'PS-009.747'],
        ],
        'krovelnye-germetiki' => [
            'name' => 'Кровельные герметики',
            'sort_order' => 20,
            'skus' => ['PS-009.744', 'PS-009.745'],
        ],
        'termostoykie-kraski' => [
            'name' => 'Термостойкие краски',
            'sort_order' => 30,
            'skus' => ['PS-007.697'],
        ],
        'uplotnitelnye-shnury' => [
            'name' => 'Уплотнительные шнуры',
            'sort_order' => 40,
            'skus' => ['KOTLOV-030273'],
        ],
    ];

    public function up(): void
    {
        $floorHeatingId = $this->categoryId('teplyj-pol');
        $installationMaterialsId = $this->categoryId('germetiki-i-montazhnye-materialy');
        $chimneyInstallationId = $this->categoryId('krepleniya-dymohod');

        if (! $floorHeatingId || ! $installationMaterialsId || ! $chimneyInstallationId) {
            return;
        }

        DB::table('categories')->where('id', $floorHeatingId)->update([
            'name' => 'Электрический тёплый пол',
            'h1' => 'Электрический тёплый пол',
            'updated_at' => now(),
        ]);

        DB::table('categories')->where('id', $installationMaterialsId)->update([
            'parent_id' => $chimneyInstallationId,
            'type' => 'child',
            'is_active' => true,
            'updated_at' => now(),
        ]);

        $this->splitProducts($floorHeatingId, self::FLOOR_HEATING_GROUPS);
        $this->splitProducts($installationMaterialsId, self::INSTALLATION_MATERIAL_GROUPS);
    }

    public function down(): void
    {
        $floorHeatingId = $this->categoryId('teplyj-pol');
        $installationMaterialsId = $this->categoryId('germetiki-i-montazhnye-materialy');

        if (! $floorHeatingId || ! $installationMaterialsId) {
            return;
        }

        $this->restoreProducts($floorHeatingId, self::FLOOR_HEATING_GROUPS);
        $this->restoreProducts($installationMaterialsId, self::INSTALLATION_MATERIAL_GROUPS);

        DB::table('categories')->where('id', $floorHeatingId)->update([
            'name' => 'Теплый пол',
            'h1' => 'Теплый пол',
            'updated_at' => now(),
        ]);
        DB::table('categories')->where('id', $installationMaterialsId)->update([
            'parent_id' => 0,
            'type' => 'main',
            'updated_at' => now(),
        ]);

        $this->deactivateEmptyCategories(array_keys(self::FLOOR_HEATING_GROUPS), $floorHeatingId);
        $this->deactivateEmptyCategories(array_keys(self::INSTALLATION_MATERIAL_GROUPS), $installationMaterialsId);
    }

    /** @param array<string, array{name: string, sort_order: int, skus: list<string>}> $groups */
    private function splitProducts(int $parentId, array $groups): void
    {
        foreach ($groups as $slug => $group) {
            $categoryId = $this->upsertChildCategory($parentId, $slug, $group['name'], $group['sort_order']);

            DB::table('products')
                ->where('category_id', $parentId)
                ->whereIn('sku', $group['skus'])
                ->update([
                    'category_id' => $categoryId,
                    'updated_at' => now(),
                ]);
        }
    }

    /** @param array<string, array{name: string, sort_order: int, skus: list<string>}> $groups */
    private function restoreProducts(int $parentId, array $groups): void
    {
        foreach ($groups as $slug => $group) {
            $categoryId = $this->categoryId($slug);

            if (! $categoryId) {
                continue;
            }

            DB::table('products')
                ->where('category_id', $categoryId)
                ->whereIn('sku', $group['skus'])
                ->update([
                    'category_id' => $parentId,
                    'updated_at' => now(),
                ]);
        }
    }

    private function upsertChildCategory(int $parentId, string $slug, string $name, int $sortOrder): int
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
            DB::table('categories')->insert($values + [
                'slug' => $slug,
                'created_at' => now(),
            ]);
        }

        return (int) DB::table('categories')->where('slug', $slug)->value('id');
    }

    /** @param list<string> $slugs */
    private function deactivateEmptyCategories(array $slugs, int $parentId): void
    {
        $categoryIds = DB::table('categories')
            ->where('parent_id', $parentId)
            ->whereIn('slug', $slugs)
            ->pluck('id');

        foreach ($categoryIds as $categoryId) {
            if (! DB::table('products')->where('category_id', $categoryId)->exists()) {
                DB::table('categories')->where('id', $categoryId)->update([
                    'is_active' => false,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function categoryId(string $slug): ?int
    {
        $id = DB::table('categories')->where('slug', $slug)->value('id');

        return $id ? (int) $id : null;
    }
};
