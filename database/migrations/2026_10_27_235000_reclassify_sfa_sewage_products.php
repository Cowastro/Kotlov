<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SEWAGE_PUMP_SKUS = [
        'KOTLOV-007847',
        'KOTLOV-007921',
        'KOTLOV-007922',
        'KOTLOV-007924',
        'KOTLOV-007925',
        'KOTLOV-007926',
        'KOTLOV-007927',
        'KOTLOV-007935',
        'KOTLOV-007936',
        'KOTLOV-007937',
        'KOTLOV-007938',
        'KOTLOV-007939',
        'KOTLOV-007940',
        'KOTLOV-007941',
    ];

    private const PUMP_ACCESSORY_SKUS = [
        'KOTLOV-007928',
        'KOTLOV-007942',
        'KOTLOV-007943',
    ];

    public function up(): void
    {
        $safetyCategoryId = $this->categoryId('Группы безопасности');
        $sewageCategoryId = $this->categoryId('Канализационные насосы сухой установки');
        $accessoryCategoryId = $this->categoryId('Комплектующие для насосов');

        if (! $safetyCategoryId || ! $sewageCategoryId || ! $accessoryCategoryId) {
            return;
        }

        $this->move(self::SEWAGE_PUMP_SKUS, $safetyCategoryId, $sewageCategoryId);
        $this->move(self::PUMP_ACCESSORY_SKUS, $safetyCategoryId, $accessoryCategoryId);
    }

    public function down(): void
    {
        $safetyCategoryId = $this->categoryId('Группы безопасности');
        $sewageCategoryId = $this->categoryId('Канализационные насосы сухой установки');
        $accessoryCategoryId = $this->categoryId('Комплектующие для насосов');

        if (! $safetyCategoryId || ! $sewageCategoryId || ! $accessoryCategoryId) {
            return;
        }

        $this->move(self::SEWAGE_PUMP_SKUS, $sewageCategoryId, $safetyCategoryId);
        $this->move(self::PUMP_ACCESSORY_SKUS, $accessoryCategoryId, $safetyCategoryId);
    }

    private function categoryId(string $name): ?int
    {
        $id = DB::table('categories')->where('name', $name)->value('id');

        return $id ? (int) $id : null;
    }

    /** @param list<string> $skus */
    private function move(array $skus, int $fromCategoryId, int $toCategoryId): void
    {
        DB::table('products')
            ->where('category_id', $fromCategoryId)
            ->whereIn('sku', $skus)
            ->update([
                'category_id' => $toCategoryId,
                'updated_at' => now(),
            ]);
    }
};
