<?php

namespace App\Console\Commands;

use App\Services\Catalog\BoilerAutomationGroups as Groups;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Keeps «Регуляторы» searchable: thermostatic heads, valves, floor thermostats and the like leave for their own
 * sections, blowers go to «Вентиляторы наддува», and every regulator gets its «Назначение» (boiler, pumps, room
 * thermostats…) for the filter. Runs daily so new supplier cards are sorted too.
 */
class SortBoilerAutomationCommand extends Command
{
    protected $signature = 'catalog:sort-boiler-automation {--apply : Save the moves and groups}';

    protected $description = 'Move misplaced cards out of «Регуляторы» and fill its «Назначение» filter';

    public function handle(): int
    {
        $sections = DB::table('categories')->pluck('id', 'slug');
        if (! isset($sections[Groups::SECTION], $sections[Groups::FANS])) {
            $this->error('Нет разделов '.Groups::SECTION.' / '.Groups::FANS.'.');

            return self::FAILURE;
        }
        $regulators = (int) $sections[Groups::SECTION];
        $sources = array_filter([$regulators, $sections[Groups::PARTS] ?? null]);
        $apply = (bool) $this->option('apply');
        $moved = $grouped = 0;
        $counts = [];

        DB::beginTransaction();
        $products = DB::table('products')->whereIn('category_id', $sources)->orderBy('id')->get(['id', 'name', 'category_id']);
        foreach ($products as $product) {
            // KIPI ROTARY burners came from the price list named only «вместе с фланцем 15-70 kW».
            if (preg_match('/^вместе с фланцем (\d+-\d+) kw$/ui', trim($product->name), $m)) {
                $old = trim($product->name);
                $product->name = 'Пеллетная горелка KIPI ROTARY '.$m[1].' кВт с фланцем';
                $meta = DB::table('products')->where('id', $product->id)->first(['meta_title', 'meta_description']);
                DB::table('products')->where('id', $product->id)->update(['name' => $product->name, 'h1' => $product->name,
                    'meta_title' => $meta->meta_title === null ? null : str_replace($old, $product->name, $meta->meta_title),
                    'meta_description' => $meta->meta_description === null ? null : str_replace($old, $product->name, $meta->meta_description),
                    'updated_at' => now()]);
                $this->line("rename {$product->id} → {$product->name}");
            }
            $target = Groups::move($product->name);
            $targetId = $target !== null ? ($sections[$target] ?? null) : null;
            if ($targetId && (int) $targetId !== (int) $product->category_id) {
                DB::table('products')->where('id', $product->id)->update(['category_id' => $targetId, 'updated_at' => now()]);
                $product->category_id = $targetId;
                $moved++;
                $this->line("move {$product->id} → {$target} | {$product->name}");
            }
        }

        foreach (Groups::filters() as $slug => [$rules, $order]) {
            if (! isset($sections[$slug])) {
                continue;
            }
            $sectionId = (int) $sections[$slug];
            $attributeId = $this->filterAttribute($sectionId);
            $options = [];
            foreach (array_values(array_unique([...$order, ...array_values($rules)])) as $i => $name) {
                $options[$name] = DB::table('attribute_options')->where('attribute_id', $attributeId)->where('name', $name)->value('id')
                    ?? DB::table('attribute_options')->insertGetId(['attribute_id' => $attributeId, 'name' => $name, 'sort_order' => $i, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('attribute_options')->where('id', $options[$name])->update(['sort_order' => $i]);
            }
            foreach ($products->where('category_id', $sectionId) as $product) {
                $group = Groups::group($product->name, $rules);
                $counts[$slug][$group] = ($counts[$slug][$group] ?? 0) + 1;
                $current = DB::table('product_attribute_values')->where('product_id', $product->id)->where('attribute_id', $attributeId)->first();
                if ($current && (int) $current->option_id === (int) $options[$group]) {
                    continue;
                }
                DB::table('product_attribute_values')->updateOrInsert(
                    ['product_id' => $product->id, 'attribute_id' => $attributeId],
                    ['option_id' => $options[$group], 'value' => null, 'created_at' => $current->created_at ?? now(), 'updated_at' => now()],
                );
                $grouped++;
            }
            // A card that left the section drops its «Назначение».
            DB::table('product_attribute_values')->where('attribute_id', $attributeId)
                ->whereNotIn('product_id', DB::table('products')->where('category_id', $sectionId)->select('id'))->delete();
        }

        if ($apply) {
            DB::commit();
            Cache::forget('home:data');
            Cache::forget('home:data:v6');
        } else {
            DB::rollBack();
        }

        $this->line('metric.status='.($apply ? 'applied' : 'dry_run'));
        $this->line("metric.boiler_automation.moved={$moved}");
        $this->line("metric.boiler_automation.grouped={$grouped}");
        foreach ($counts as $slug => $groups) {
            foreach ($groups as $group => $count) {
                $this->line("group {$slug} | {$group}: {$count}");
            }
        }

        return self::SUCCESS;
    }

    private function filterAttribute(int $categoryId): int
    {
        $key = ['category_id' => $categoryId, 'name' => Groups::FILTER, 'type' => 'select'];
        $id = DB::table('attributes')->where($key)->value('id');
        if ($id) {
            DB::table('attributes')->where('id', $id)->update(['in_filter' => true, 'sort_order' => -10]);

            return (int) $id;
        }

        return (int) DB::table('attributes')->insertGetId($key + ['in_filter' => true, 'in_product' => false, 'sort_order' => -10, 'created_at' => now(), 'updated_at' => now()]);
    }
}
