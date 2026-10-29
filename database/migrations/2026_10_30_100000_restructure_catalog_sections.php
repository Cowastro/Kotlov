<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The catalogue in 16 sections instead of a dumping ground «Комплектующие» and pipes listed 26 in a row:
 * categories move to the section a buyer looks in, sections get plain names and an order, «Арматура»
 * becomes a section of its own and plain duplicates are folded with a redirect. Category addresses
 * stay; a product moved to another category answers its old address with a 301 (ProductController).
 * Categories a supplier sync writes into are never switched off. The menu groups live in
 * config('navigation.groups').
 */
return new class extends Migration
{
    /** Root sections: slug => [name, menu order]. */
    private const ROOTS = [
        'kotly' => ['Котлы и горелки', 10],
        'teplovyie-nasosyi' => ['Тепловые насосы', 20],
        'radiatory' => ['Радиаторы и конвекторы', 30],
        'teplyj-pol' => ['Тёплый пол', 40],
        'vodonagrevateli' => ['Водонагреватели', 50],
        'pechki' => ['Печи', 60],
        'kaminy' => ['Камины', 70],
        'bani-i-sauny' => ['Бани и сауны', 80],
        'dymohody' => ['Дымоходы', 90],
        'truby-i-fitingi' => ['Трубы и фитинги', 100],
        'krany-i-zapornaya-armatura' => ['Краны и арматура', 110],
        'komplektuyushhie-dlya-otopleniya' => ['Обвязка котельной', 120],
        'vodosnabzhenie' => ['Насосы и водоснабжение', 130],
        'santehnika' => ['Сантехника', 140],
        'klimat' => ['Климат', 150],
        'montazhnye-materialy' => ['Монтаж и инструмент', 160],
    ];

    /** New parent => categories moved under it. */
    private const MOVES = [
        'kotly' => ['pelletnye-gorelki', 'regulyatory', 'datchiki', 'stabilizatory-napryazheniya', 'komplektuyushchie-stabilizatorov',
            'istochniki-besperebojnogo-pitaniya', 'avtotransformatory-latr', 'signalizatory-zagazovannosti', 'gazovye-shlangi',
            'regulyatoryi-davleniya-gaza', 'schetchiki-gaza'],
        'teplovyie-nasosyi' => ['solnechnye-kollektory'],
        'radiatory' => ['radiatornaya-armatura'],
        'teplyj-pol' => ['vodyanoy-teplyy-pol'],
        'kaminy' => ['drovnitsyi', 'kaminnye-nabory'],
        'aksessuary-kaminy' => ['stekla-dlya-pechey-i-kaminov'],
        'krany-i-zapornaya-armatura' => ['sharovye-krany', 'filtry', 'predokhranitelnaya-i-reguliruyushchaya-armatura', 'smesitelnye-klapany-i-uzly',
            'smesitelnaya-armatura', 'manometry-i-termometry', 'schetchiki-vody-i-tepla'],
        'santehnika' => ['gibkaya-podvodka-dlya-vody', 'zhestkaya-podvodka-dlya-vody'],
        'montazhnye-materialy' => ['instrumenty-dlya-montazha'],
        'klimat' => ['gibkie-vozduhovody'],
        'sistemy-zashchity-ot-protechek' => ['datchiki-protechki-vody', 'moduli-upravleniya-zashchitoj-ot-protechek', 'krany-s-elektroprivodom'],
    ];

    /** Categories to add: slug => [parent, name, sort]. */
    private const NEW = [
        'kondicionery' => ['klimat', 'Кондиционеры', 5],
        'naruzhnyy-obogrev' => ['klimat', 'Наружный обогрев и антиобледенение', 60],
    ];

    /** Outdoor heating parts gather under one category. */
    private const OUTDOOR_HEATING = ['kabeli-naruzhnogo-obogreva', 'komplektuyushchie-naruzhnogo-obogreva', 'upravlenie-naruzhnym-obogrevom', 'datchiki-naruzhnogo-obogreva'];

    /** Hidden categories with cards a buyer should find. */
    private const SWITCH_ON = ['chugunnye-radiatory', 'termoregulyatory-dlya-teplogo-pola', 'mobilnye-kondicionery', 'uvlazhniteli-vozdukha',
        'mojki-vozdukha', 'ventilyatory', 'teplovye-pushki', 'teploventilyatory', 'teplovye-zavesy', 'maslyanye-obrevateli', 'infrakrasnye-obrevateli'];

    /** Plain duplicates: folded => kept (the kept one is where the supplier sync writes). */
    private const FOLD = [
        'nagrevatelnye-maty' => 'nagrevatelynye-maty',
        'nagrevatelnye-kabeli' => 'nagrevatelynye-kabeli',
        'kupeli-2' => 'kupeli',
    ];

    /** Empty categories off the menu. */
    private const SWITCH_OFF = ['dlya-dachi'];

    public function up(): void
    {
        $now = now();
        $id = fn (string $slug): ?int => ($found = DB::table('categories')->where('slug', $slug)->value('id')) ? (int) $found : null;
        $rename = function (int $categoryId, string $name) use ($now): void {
            $category = DB::table('categories')->where('id', $categoryId)->first(['name', 'h1']);
            DB::table('categories')->where('id', $categoryId)->update(array_filter([
                'name' => $name,
                // A hand-written H1 stays; one equal to the old name follows the new one.
                'h1' => ($category->h1 === null || $category->h1 === '' || $category->h1 === $category->name) ? $name : null,
                'updated_at' => $now,
            ]));
        };

        DB::transaction(function () use ($id, $rename, $now): void {
            foreach (self::ROOTS as $slug => [$name, $sort]) {
                if ($rootId = $id($slug)) {
                    DB::table('categories')->where('id', $rootId)->update(['parent_id' => 0, 'type' => 'main', 'sort_order' => $sort, 'is_active' => true, 'updated_at' => $now]);
                    $rename($rootId, $name);
                }
            }
            foreach (self::NEW as $slug => [$parent, $name, $sort]) {
                $parentId = $id($parent);
                if ($parentId && ! $id($slug)) {
                    DB::table('categories')->insert([
                        'parent_id' => $parentId, 'name' => $name, 'slug' => $slug, 'h1' => $name, 'type' => 'child', 'sort_order' => $sort, 'is_active' => true,
                        'meta_title' => $name.' купить в %city% — цены | KOTLOV',
                        'meta_description' => $name.' в наличии и под заказ: цены в BYN, характеристики, доставка по Беларуси.',
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
            $moves = self::MOVES + ['naruzhnyy-obogrev' => self::OUTDOOR_HEATING];
            foreach ($moves as $parent => $slugs) {
                $parentId = $id($parent);
                if (! $parentId) {
                    continue;
                }
                DB::table('categories')->whereIn('slug', $slugs)->where('id', '!=', $parentId)
                    ->update(['parent_id' => $parentId, 'type' => 'child', 'updated_at' => $now]);
            }
            if ($id('pelletnye-gorelki')) {
                $rename($id('pelletnye-gorelki'), 'Пеллетные горелки');
            }
            DB::table('categories')->whereIn('slug', self::SWITCH_ON)->update(['is_active' => true, 'updated_at' => $now]);
            DB::table('categories')->whereIn('slug', self::SWITCH_OFF)
                ->whereNotExists(fn ($q) => $q->from('products')->whereColumn('products.category_id', 'categories.id'))
                ->update(['is_active' => false, 'updated_at' => $now]);

            foreach (self::FOLD as $from => $to) {
                $fromId = $id($from);
                $toId = $id($to);
                if (! $fromId || ! $toId) {
                    continue;
                }
                DB::table('products')->where('category_id', $fromId)->update(['category_id' => $toId, 'updated_at' => $now]);
                DB::table('categories')->where('parent_id', $fromId)->update(['parent_id' => $toId, 'updated_at' => $now]);
                DB::table('categories')->where('id', $fromId)->update(['is_active' => false, 'updated_at' => $now]);
                DB::table('redirects')->updateOrInsert(['from_url' => '/'.$from],
                    ['to_url' => '/'.$to, 'status_code' => 301, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            }
        });
    }

    public function down(): void
    {
        // The new structure stays: the old one had cards nobody could reach.
    }
};
