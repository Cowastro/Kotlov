<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $brandIds = [];
        foreach (['Edisson', 'Garanterm', 'EuroElite'] as $name) {
            $slug = Str::slug($name);
            DB::table('brands')->insertOrIgnore([
                'name' => $name,
                'slug' => $slug,
                'h1' => $name,
                'is_active' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ]);
            DB::table('brands')->where('slug', $slug)->update([
                'name' => $name,
                'h1' => $name,
                'is_active' => true,
                'updated_at' => $now,
            ]);
            $brandIds[$name] = (int) DB::table('brands')->where('slug', $slug)->value('id');
        }

        DB::table('products')->whereIn('slug', [
            'vodonagrevatel-nakopitelnyiy-elektricheskiy-edisson-king-30-v',
            'vodonagrevatel-nakopitelnyiy-elektricheskiy-edisson-king-50-v',
            'vodonagrevatel-nakopitelnyiy-elektricheskiy-edisson-king-80-v',
            'vodonagrevatel-nakopitelnyiy-elektricheskiy-edisson-king-100-v',
        ])->update([
            'brand_id' => $brandIds['Edisson'],
            'updated_at' => $now,
        ]);
        DB::table('products')->where('slug', 'vodonagrevatel-nakopitelnyiy-elektricheskiy-garanterm-flat-30-v')->update([
            'brand_id' => $brandIds['Garanterm'],
            'updated_at' => $now,
        ]);
        DB::table('products')->where('slug', 'gazovyj-kotel-thermex-euroelite-f24')->update([
            'brand_id' => $brandIds['EuroElite'],
            'name' => 'Газовый котел EuroElite F24',
            'h1' => 'Газовый котел EuroElite F24',
            'updated_at' => $now,
        ]);

        $duplicates = [
            'nakopitelnyiy-vodonagrevatel-thermex-ic-10-u' => 'thermex-ic-10-u',
            'nakopitelnyiy-vodonagrevatel-thermex-ic-15-o' => 'thermex-ic-15-o',
            'nakopitelnyiy-vodonagrevatel-thermex-ic-15-u' => 'thermex-ic-15-u',
        ];

        foreach ($duplicates as $duplicateSlug => $canonicalSlug) {
            $duplicate = DB::table('products')->where('slug', $duplicateSlug)->first();
            $canonical = DB::table('products')->where('slug', $canonicalSlug)->first();
            if (
                ! $duplicate
                || ! $canonical
                || ! (bool) $canonical->is_active
                || (bool) $canonical->is_archived
            ) {
                continue;
            }

            if (Schema::hasTable('redirects')) {
                $duplicateCategory = DB::table('categories')->where('id', $duplicate->category_id)->value('slug');
                $canonicalCategory = DB::table('categories')->where('id', $canonical->category_id)->value('slug');
                if ($duplicateCategory && $canonicalCategory) {
                    DB::table('redirects')->updateOrInsert(
                        ['from_url' => '/' . $duplicateCategory . '/' . $duplicate->slug],
                        [
                            'to_url' => '/' . $canonicalCategory . '/' . $canonical->slug,
                            'status_code' => 301,
                            'is_active' => true,
                            'updated_at' => $now,
                            'created_at' => $now,
                        ]
                    );
                }
            }

            DB::table('products')->where('id', $duplicate->id)->update([
                'is_active' => false,
                'is_archived' => true,
                'in_stock' => false,
                'stock_qty' => null,
                'availability_status' => 'out_of_stock',
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $now = now();
        DB::table('products')->whereIn('slug', [
            'nakopitelnyiy-vodonagrevatel-thermex-ic-10-u',
            'nakopitelnyiy-vodonagrevatel-thermex-ic-15-o',
            'nakopitelnyiy-vodonagrevatel-thermex-ic-15-u',
        ])->update([
            'is_active' => true,
            'is_archived' => false,
            'updated_at' => $now,
        ]);

        foreach ([
            'nakopitelnyiy-vodonagrevatel-thermex-ic-10-u',
            'nakopitelnyiy-vodonagrevatel-thermex-ic-15-o',
            'nakopitelnyiy-vodonagrevatel-thermex-ic-15-u',
        ] as $productSlug) {
            $product = DB::table('products')->where('slug', $productSlug)->first();
            if (! $product || ! Schema::hasTable('redirects')) {
                continue;
            }
            $category = DB::table('categories')->where('id', $product->category_id)->value('slug');
            if ($category) {
                DB::table('redirects')->where('from_url', '/' . $category . '/' . $product->slug)->delete();
            }
        }

        $thermexId = (int) DB::table('brands')->where('slug', 'thermex')->value('id');
        if ($thermexId > 0) {
            DB::table('products')->whereIn('slug', [
                'vodonagrevatel-nakopitelnyiy-elektricheskiy-edisson-king-30-v',
                'vodonagrevatel-nakopitelnyiy-elektricheskiy-edisson-king-50-v',
                'vodonagrevatel-nakopitelnyiy-elektricheskiy-edisson-king-80-v',
                'vodonagrevatel-nakopitelnyiy-elektricheskiy-edisson-king-100-v',
                'vodonagrevatel-nakopitelnyiy-elektricheskiy-garanterm-flat-30-v',
                'gazovyj-kotel-thermex-euroelite-f24',
            ])->update([
                'brand_id' => $thermexId,
                'updated_at' => $now,
            ]);
            DB::table('products')->where('slug', 'gazovyj-kotel-thermex-euroelite-f24')->update([
                'name' => 'Газовый котел Thermex EuroElite F24',
                'h1' => 'Газовый котел Thermex EuroElite F24',
                'updated_at' => $now,
            ]);
        }
    }
};
