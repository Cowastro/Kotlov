<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normalizes power filter option names for stove categories (pechki branch).
 *
 * Attrs 832 (Печи дровяные) and 973 (Печи-камины) both represent "Мощность"
 * but had different option label formats, preventing CatalogController from
 * merging them via normalizeFilterName(). After this migration both attrs share
 * identical labels → 4 unified ranges in the filter.
 *
 * Also splits attr 973's "Более 15 кВт" (opt 868): products ≥20 kW move to a
 * new option "20 кВт и более", and JOTUL F500 (max 11 kW) moves to "10–15 кВт".
 */
return new class extends Migration
{
    public function up(): void
    {
        // Attr 832 (Печи дровяные): align labels to unified standard
        DB::table('attribute_options')->where('id', 629)->update(['name' => 'Менее 10 кВт']);
        DB::table('attribute_options')->where('id', 630)->update(['name' => '10–15 кВт']);
        DB::table('attribute_options')->where('id', 631)->update(['name' => '15–20 кВт']);
        // opt 643 "20 кВт и более" already correct

        // Attr 973 (Печи-камины): align labels to unified standard
        DB::table('attribute_options')->where('id', 867)->update(['name' => '10–15 кВт']);
        DB::table('attribute_options')->where('id', 868)->update(['name' => '15–20 кВт']);
        // opt 866 "Менее 10 кВт" already correct

        // Add missing "20 кВт и более" option to attr 973
        $newOptionId = DB::table('attribute_options')->insertGetId([
            'attribute_id' => 973,
            'name'         => '20 кВт и более',
            'sort_order'   => 4,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // Move products ≥20 kW (Plamen ×2, INVICTA, MBS ×2) to new option
        DB::table('product_attribute_values')
            ->where('attribute_id', 973)
            ->where('option_id', 868)
            ->whereIn('product_id', [7452, 7455, 11033, 11670, 11671])
            ->update(['option_id' => $newOptionId]);

        // JOTUL F500 ECO SE BBE: multi-mode 3.5/8.5/11 kW → max 11 kW → "10–15 кВт"
        DB::table('product_attribute_values')
            ->where('attribute_id', 973)
            ->where('product_id', 10959)
            ->update(['option_id' => 867]);
    }

    public function down(): void
    {
        DB::table('attribute_options')->where('id', 629)->update(['name' => '5 — 10 кВт']);
        DB::table('attribute_options')->where('id', 630)->update(['name' => '10 — 15 кВт']);
        DB::table('attribute_options')->where('id', 631)->update(['name' => '15 — 20 кВт']);
        DB::table('attribute_options')->where('id', 867)->update(['name' => '10 кВт - 15 кВт']);
        DB::table('attribute_options')->where('id', 868)->update(['name' => 'Более 15 кВт']);

        $newOption = DB::table('attribute_options')
            ->where('attribute_id', 973)
            ->where('name', '20 кВт и более')
            ->where('sort_order', 4)
            ->first();

        if ($newOption) {
            DB::table('product_attribute_values')
                ->where('attribute_id', 973)
                ->where('option_id', $newOption->id)
                ->whereIn('product_id', [7452, 7455, 11033, 11670, 11671])
                ->update(['option_id' => 868]);

            DB::table('product_attribute_values')
                ->where('attribute_id', 973)
                ->where('product_id', 10959)
                ->update(['option_id' => 868]);

            DB::table('attribute_options')->where('id', $newOption->id)->delete();
        }
    }
};
