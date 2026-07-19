<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $kotlovGeId = DB::table('brands')->where('slug', 'kotlov-ge')->value('id');

        if (! $kotlovGeId) {
            $kotlovGeId = DB::table('brands')->insertGetId([
                'name' => 'KOTLOV GE',
                'slug' => 'kotlov-ge',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $hottaId = DB::table('brands')->where('slug', 'hotta')->value('id');

        if (! $hottaId) {
            return;
        }

        DB::table('products')
            ->where('brand_id', $hottaId)
            ->update([
                'brand_id' => $kotlovGeId,
                'updated_at' => $now,
            ]);

        DB::table('products')
            ->where('name', 'like', '%HOTTA%')
            ->orWhere('name', 'like', '%Hotta%')
            ->update([
                'name' => DB::raw("REPLACE(REPLACE(name, 'HOTTA', 'KOTLOV GE'), 'Hotta', 'KOTLOV GE')"),
                'updated_at' => $now,
            ]);

        DB::table('products')
            ->where('h1', 'like', '%HOTTA%')
            ->orWhere('h1', 'like', '%Hotta%')
            ->update([
                'h1' => DB::raw("REPLACE(REPLACE(h1, 'HOTTA', 'KOTLOV GE'), 'Hotta', 'KOTLOV GE')"),
                'updated_at' => $now,
            ]);

        foreach (['content', 'short_description', 'meta_title', 'meta_description', 'meta_keywords'] as $field) {
            DB::table('products')
                ->where($field, 'like', '%HOTTA%')
                ->orWhere($field, 'like', '%Hotta%')
                ->update([
                    $field => DB::raw("REPLACE(REPLACE({$field}, 'HOTTA', 'KOTLOV GE'), 'Hotta', 'KOTLOV GE')"),
                    'updated_at' => $now,
                ]);
        }

        DB::table('brands')->where('id', $hottaId)->update([
            'is_active' => false,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        //
    }
};
