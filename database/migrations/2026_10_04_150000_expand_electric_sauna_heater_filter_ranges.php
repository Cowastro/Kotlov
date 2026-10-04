<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $categoryId = DB::table('categories')->where('slug', 'elektrokamenki')->value('id');
        if (! $categoryId) {
            return;
        }

        $powerId = DB::table('attributes')
            ->where('category_id', $categoryId)
            ->where('name', 'Мощность (кВт)')
            ->value('id');
        $volumeId = DB::table('attributes')
            ->where('category_id', $categoryId)
            ->where('name', 'Максимальный объем парилки (m3)')
            ->value('id');

        if ($powerId) {
            DB::table('attribute_options')
                ->where('attribute_id', $powerId)
                ->where('name', '3 — 5')
                ->update(['name' => 'до 5', 'updated_at' => now()]);
        }

        if ($volumeId) {
            $lastOption = DB::table('attribute_options')
                ->where('attribute_id', $volumeId)
                ->where('name', '15 и более')
                ->first();

            if ($lastOption) {
                DB::table('attribute_options')->where('id', $lastOption->id)->update([
                    'name' => '15 — 20',
                    'sort_order' => 4,
                    'updated_at' => now(),
                ]);
            }

            foreach ([5 => '20 — 30', 6 => '30 и более'] as $sortOrder => $name) {
                DB::table('attribute_options')->updateOrInsert(
                    ['attribute_id' => $volumeId, 'name' => $name],
                    ['sort_order' => $sortOrder, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }
    }

    public function down(): void
    {
        // Product values are deliberately kept; rolling back range labels could make them misleading.
    }
};
