<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $supplierId = DB::table('suppliers')->where('code', 'azbuka-tepla')->value('id');
        $wrongCategoryId = DB::table('categories')->where('slug', 'ventilyacionnye-klapana')->value('id');
        $valveCategoryId = DB::table('categories')->where('slug', 'smesitelnye-klapany-i-uzly')->value('id');
        $partsCategoryId = DB::table('categories')->where('slug', 'komplektuyushhie-dlya-otopleniya')->value('id');

        if (! $supplierId || ! $wrongCategoryId || ! $valveCategoryId || ! $partsCategoryId) {
            return;
        }

        DB::table('supplier_products')
            ->where('supplier_id', $supplierId)
            ->whereNotNull('product_id')
            ->orderBy('id')
            ->select(['id', 'product_id', 'raw'])
            ->chunkById(100, function ($offers) use ($wrongCategoryId, $valveCategoryId, $partsCategoryId): void {
                foreach ($offers as $offer) {
                    $raw = json_decode((string) $offer->raw, true);
                    if (! is_array($raw)) {
                        continue;
                    }

                    $description = mb_strtolower((string) ($raw['description'] ?? ''));
                    if (! str_contains($description, 'трехход') && ! str_contains($description, 'трёхход')) {
                        continue;
                    }

                    $text = mb_strtolower(implode(' ', [
                        (string) ($raw['partner_name'] ?? ''),
                        (string) ($raw['partner_description'] ?? ''),
                    ]));
                    $isThermostaticElement = str_contains($text, 'термостатический элемент')
                        || str_contains($text, 'термоэлемент');

                    $product = DB::table('products')
                        ->where('id', $offer->product_id)
                        ->where('category_id', $wrongCategoryId);

                    if (! $product->exists()) {
                        continue;
                    }

                    $updates = [
                        'category_id' => $isThermostaticElement ? $partsCategoryId : $valveCategoryId,
                        'updated_at' => now(),
                    ];

                    if ($isThermostaticElement) {
                        $model = trim((string) ($raw['model'] ?? ''));
                        $brand = trim((string) ($raw['brand'] ?? 'Barberi'));
                        $updates['name'] = trim("Термостатический элемент {$brand} {$model}");
                    }

                    $product->update($updates);
                }
            }, 'id');
    }

    public function down(): void
    {
        // Категории каталога после ручной проверки не откатываем обратно в заведомо неверную вентиляцию.
    }
};
