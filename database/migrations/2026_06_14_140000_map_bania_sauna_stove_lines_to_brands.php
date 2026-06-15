<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $teplodarId = (int) DB::table('brands')->where('slug', 'teplodar')->value('id');
        $fakelId = $this->ensureBrand('Факел', 'fakel', $now);

        if ($teplodarId > 0) {
            DB::table('products')
                ->where('is_archived', false)
                ->where(function ($query) {
                    $query->where('name', 'like', '%Сиеста%')
                        ->orWhere('name', 'like', '%БЫЛИНА%')
                        ->orWhere('name', 'like', '%Былина%')
                        ->orWhere('name', 'like', '%Сибирский утес%')
                        ->orWhere('name', 'like', '%Сибирский утёс%');
                })
                ->update([
                    'brand_id' => $teplodarId,
                    'updated_at' => $now,
                ]);
        }

        DB::table('products')
            ->where('is_archived', false)
            ->where('name', 'like', '%Факел%')
            ->update([
                'brand_id' => $fakelId,
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        // Brand mapping is a forward-only data correction.
    }

    private function ensureBrand(string $name, string $slug, $now): int
    {
        $brand = DB::table('brands')->where('slug', $slug)->orWhere('name', $name)->first();
        if ($brand) {
            DB::table('brands')->where('id', $brand->id)->update([
                'name' => $name,
                'slug' => $slug,
                'h1' => $brand->h1 ?: $name,
                'is_active' => true,
                'updated_at' => $now,
            ]);

            return (int) $brand->id;
        }

        return (int) DB::table('brands')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'h1' => $name,
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
