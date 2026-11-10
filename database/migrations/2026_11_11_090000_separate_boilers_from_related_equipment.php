<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const EQUIPMENT_SECTIONS = [
        'gazovoe-oborudovanie' => [
            'Газовое оборудование', 122,
            ['signalizatory-zagazovannosti', 'gazovye-shlangi', 'regulyatoryi-davleniya-gaza', 'schetchiki-gaza'],
        ],
        'elektropitanie-i-zashchita' => [
            'Электропитание и защита', 124,
            ['stabilizatory-napryazheniya', 'komplektuyushchie-stabilizatorov', 'istochniki-besperebojnogo-pitaniya', 'avtotransformatory-latr'],
        ],
    ];

    private const PRODUCT_MOVES = [
        'regulyatory' => [
            'sten-termoreguliator-dlia-elektriceskix-kotlov-sten-i-evan',
            'sten-peu-103',
            'sten-pue-15',
            'auraton-tucana-set-elektronnyi-reguliator-temperatury',
        ],
        'datchiki' => ['bosch-datcik-boilera'],
        'koaxial-dymoxod' => ['viessmann-koaksialnyi-dymoxod-60100-originalnyi'],
        'vodogreynaya-kolonka' => ['ferroli-verona-11d'],
        'dymohody-mono' => ['dyimohod-025m-ferrum-43008-mm-f180-7903'],
    ];

    public function up(): void
    {
        $boilers = DB::table('categories')->where('slug', 'kotly')->value('id');
        if (! $boilers) {
            return;
        }

        DB::transaction(function () use ($boilers): void {
            $now = now();
            $children = DB::table('categories')->where('parent_id', $boilers);
            DB::table('categories')->where('id', $boilers)
                ->update(['name' => 'Котлы', 'h1' => 'Котлы отопления', 'updated_at' => $now]);
            (clone $children)->where('slug', 'pelletnye-gorelki')
                ->update(['parent_id' => 0, 'type' => 'main', 'sort_order' => 15, 'updated_at' => $now]);

            $boilerRoom = DB::table('categories')->where('slug', 'komplektuyushhie-dlya-otopleniya')->value('id');
            if ($boilerRoom) {
                (clone $children)->whereIn('slug', ['regulyatory', 'datchiki', 'zapchasti-dlya-kotlov'])
                    ->update(['parent_id' => $boilerRoom, 'type' => 'child', 'updated_at' => $now]);
            }

            foreach (self::EQUIPMENT_SECTIONS as $slug => [$name, $order, $slugs]) {
                $equipment = (clone $children)->whereIn('slug', $slugs);
                if (! (clone $equipment)->exists()) {
                    continue;
                }

                $parent = DB::table('categories')->where('slug', $slug)->value('id')
                    ?? DB::table('categories')->insertGetId([
                        'parent_id' => 0, 'type' => 'main', 'is_active' => true, 'sort_order' => $order,
                        'name' => $name, 'h1' => $name, 'slug' => $slug,
                        'meta_title' => $name.' в %city% — каталог и цены | KOTLOV',
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                $equipment->update(['parent_id' => $parent, 'type' => 'child', 'updated_at' => $now]);
            }

            $boilerCategoryIds = DB::table('categories')
                ->whereIn('slug', ['kotly', 'gazovye', 'elektricheskie', 'tverdotoplivnye', 'kotly-na-pelletah'])
                ->pluck('id');
            $destinations = DB::table('categories')->whereIn('slug', array_keys(self::PRODUCT_MOVES))->pluck('id', 'slug');
            foreach (self::PRODUCT_MOVES as $destination => $slugs) {
                if (isset($destinations[$destination])) {
                    DB::table('products')->whereIn('category_id', $boilerCategoryIds)->whereIn('slug', $slugs)
                        ->update(['category_id' => $destinations[$destination], 'updated_at' => $now]);
                }
            }

            if (isset($destinations['dymohody-mono'])) {
                DB::table('products')->where('category_id', $destinations['dymohody-mono'])
                    ->where('slug', 'dyimohod-025m-ferrum-43008-mm-f180-7903')->where('name', 'test')
                    ->update(['name' => 'Дымоход Ferrum 0,25 м Ø180 мм (430/0,8 мм)', 'updated_at' => $now]);
            }

            DB::table('products')->whereIn('category_id', $boilerCategoryIds)
                ->where('slug', 'bannaya-pech-grill`d-leo-ii-130-short-black')->where('name', 'опс')
                ->update(['is_active' => false, 'is_archived' => true, 'updated_at' => $now]);
        });
    }

    public function down(): void
    {
        // Keep the corrected catalog assignments on rollback.
    }
};
