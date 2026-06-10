<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CATEGORY_ID = 96;

    public function up(): void
    {
        $from = DB::table('attributes')
            ->where('category_id', self::CATEGORY_ID)
            ->where('name', 'При максимальном расходе')
            ->first();

        if (! $from) {
            return;
        }

        $to = DB::table('attributes')
            ->where('category_id', self::CATEGORY_ID)
            ->where('name', 'Потеря давления при максимальном расходе')
            ->first();

        if (! $to) {
            DB::table('attributes')->where('id', $from->id)->update([
                'name' => 'Потеря давления при максимальном расходе',
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('product_attribute_values')
            ->where('attribute_id', $from->id)
            ->orderBy('id')
            ->each(function ($value) use ($to) {
                $existing = DB::table('product_attribute_values')
                    ->where('product_id', $value->product_id)
                    ->where('attribute_id', $to->id)
                    ->first();

                if ($existing) {
                    DB::table('product_attribute_values')->where('id', $value->id)->delete();
                    return;
                }

                DB::table('product_attribute_values')->where('id', $value->id)->update([
                    'attribute_id' => $to->id,
                    'updated_at' => now(),
                ]);
            });

        DB::table('attributes')->where('id', $from->id)->delete();
    }

    public function down(): void
    {
        //
    }
};
