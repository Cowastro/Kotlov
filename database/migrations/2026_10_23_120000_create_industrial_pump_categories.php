<?php

use App\Support\Catalog\TskPumpClassifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «Промышленные насосы» next to the surface pumps, for the Wellmix range from
 * ТСК Насосы (multistage CV/CMI/CUC, monoblock NBW/NBS, end-suction NKW,
 * in-line TD/TG, dry-installed sewage WL). Cards move by TskPumpClassifier,
 * the same rules the ТСК sync uses for new cards.
 */
return new class extends Migration
{
    private const CHILDREN = [
        'mnogostupenchatye-nasosy' => ['Многоступенчатые насосы', 'Многоступенчатые центробежные насосы', 10,
            'Вертикальные и горизонтальные многоступенчатые насосы для повышения давления и водоснабжения зданий.'],
        'konsolnye-i-monoblochnye-nasosy' => ['Консольные и моноблочные насосы', 'Консольные и моноблочные центробежные насосы', 20,
            'Консольные и моноблочные насосы для систем водоснабжения, отопления и технологических линий.'],
        'in-lain-nasosy' => ['Ин-лайн насосы', 'Ин-лайн насосы для систем отопления и кондиционирования', 30,
            'Циркуляционные ин-лайн насосы большой производительности для отопления, ГВС и холодоснабжения.'],
        'kanalizatsionnye-nasosy-sukhoy-ustanovki' => ['Канализационные насосы сухой установки', 'Канализационные насосы сухой установки', 40,
            'Канализационные насосы сухой установки для насосных станций и очистных сооружений.'],
    ];

    /** Categories whose cards may move into the new ones. */
    private const SOURCES = ['poverhnostnyie', 'tsirkulyatsionnyie', 'drenajnyie', 'nasosy', 'vodosnabzhenie'];

    public function up(): void
    {
        $surface = DB::table('categories')->where('slug', 'poverhnostnyie')->first(['id', 'parent_id']);
        if (! $surface) {
            return;
        }
        DB::table('categories')->updateOrInsert(['slug' => 'promyshlennye-nasosy'], [
            'parent_id' => $surface->parent_id,
            'name' => 'Промышленные насосы',
            'h1' => 'Промышленные насосы',
            'type' => 'main-child',
            'sort_order' => 90,
            'is_active' => true,
            'meta_title' => 'Промышленные насосы — купить в %city% | KOTLOV',
            'meta_description' => 'Многоступенчатые, консольные, моноблочные и ин-лайн насосы, канализационные насосы сухой установки. Цены, наличие и доставка по Беларуси.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $parentId = DB::table('categories')->where('slug', 'promyshlennye-nasosy')->value('id');
        $ids = [];
        foreach (self::CHILDREN as $slug => [$name, $h1, $sort, $description]) {
            DB::table('categories')->updateOrInsert(['slug' => $slug], [
                'parent_id' => $parentId,
                'name' => $name,
                'h1' => $h1,
                'type' => 'child',
                'sort_order' => $sort,
                'is_active' => true,
                'meta_title' => $name.' — купить в %city% | KOTLOV',
                'meta_description' => $description.' Цены, наличие и доставка по Беларуси.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $ids[$slug] = DB::table('categories')->where('slug', $slug)->value('id');
        }

        $sources = DB::table('categories')->whereIn('slug', self::SOURCES)->pluck('id');
        DB::table('products')->whereIn('category_id', $sources)->where('is_archived', false)
            ->orderBy('id')->get(['id', 'name'])
            ->each(function (object $product) use ($ids): void {
                $slug = TskPumpClassifier::categorySlug((string) $product->name);
                if ($slug !== null && isset($ids[$slug])) {
                    DB::table('products')->where('id', $product->id)->update(['category_id' => $ids[$slug], 'updated_at' => now()]);
                }
            });
    }

    public function down(): void
    {
        $surface = DB::table('categories')->where('slug', 'poverhnostnyie')->value('id');
        $fallback = [
            'mnogostupenchatye-nasosy' => $surface,
            'konsolnye-i-monoblochnye-nasosy' => $surface,
            'in-lain-nasosy' => DB::table('categories')->where('slug', 'tsirkulyatsionnyie')->value('id'),
            'kanalizatsionnye-nasosy-sukhoy-ustanovki' => DB::table('categories')->where('slug', 'drenajnyie')->value('id'),
        ];
        foreach ($fallback as $slug => $target) {
            $id = DB::table('categories')->where('slug', $slug)->value('id');
            if ($id && $target) {
                DB::table('products')->where('category_id', $id)->update(['category_id' => $target, 'updated_at' => now()]);
                DB::table('categories')->where('id', $id)->delete();
            }
        }
        DB::table('categories')->where('slug', 'promyshlennye-nasosy')->delete();
    }
};
