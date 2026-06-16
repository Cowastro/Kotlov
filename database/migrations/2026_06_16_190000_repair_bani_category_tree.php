<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $baniId = DB::table('categories')->where('slug', 'bani-i-sauny')->value('id');

        if (! $baniId) {
            return;
        }

        $childrenBySlug = [
            'pechi-dlya-bani'           => 10,
            'drovyanye-pechi-dlya-bani' => 20,
            'elektrokamenki'            => 30,
            'aksessuary-dlya-bani'      => 40,
            'baki-dlya-bani'            => 50,
            'dveri-dlya-bani-i-sauny'   => 60,
            'mangaly'                   => 70,
        ];

        foreach ($childrenBySlug as $slug => $sortOrder) {
            DB::table('categories')
                ->where('slug', $slug)
                ->update([
                    'parent_id'  => $baniId,
                    'sort_order' => $sortOrder,
                    'is_active'  => 1,
                    'updated_at' => now(),
                ]);
        }

        $childrenById = [
            74  => 50, // bath water tanks
            72  => 60, // bath and sauna doors
            295 => 70, // grills
        ];

        foreach ($childrenById as $id => $sortOrder) {
            DB::table('categories')
                ->where('id', $id)
                ->update([
                    'parent_id'  => $baniId,
                    'sort_order' => $sortOrder,
                    'is_active'  => 1,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        $pechiBaniId = DB::table('categories')->where('slug', 'pechi-dlya-bani')->value('id');
        $aksessBaniId = DB::table('categories')->where('slug', 'aksessuary-dlya-bani')->value('id');

        if ($pechiBaniId) {
            DB::table('categories')
                ->whereIn('slug', ['drovyanye-pechi-dlya-bani', 'elektrokamenki'])
                ->update([
                    'parent_id'  => $pechiBaniId,
                    'updated_at' => now(),
                ]);
        }

        if ($aksessBaniId) {
            DB::table('categories')
                ->whereIn('slug', ['baki-dlya-bani', 'dveri-dlya-bani-i-sauny', 'mangaly'])
                ->update([
                    'parent_id'  => $aksessBaniId,
                    'updated_at' => now(),
                ]);
        }
    }
};
