<?php

namespace App\Console\Commands;

use App\Services\Catalog\BoilerAutomationGroups as Groups;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the boiler automation, parts, sensors, gauges and meters sections searchable: strays leave for their own
 * sections, blowers go to «Вентиляторы наддува», and every card gets its «Назначение» (and for meters its «Диаметр»)
 * for the filter. Runs daily so new supplier cards are sorted too.
 */
class SortBoilerAutomationCommand extends Command
{
    protected $signature = 'catalog:sort-boiler-automation {--apply : Save the moves and groups}';

    protected $description = 'Move misplaced cards out of the boiler automation sections and fill their group filters';

    private int $grouped = 0;

    /** @var array<string, array<string, int>> */
    private array $counts = [];

    public function handle(): int
    {
        $sections = DB::table('categories')->pluck('id', 'slug');
        if (! isset($sections[Groups::SECTION], $sections[Groups::FANS])) {
            $this->error('Нет разделов '.Groups::SECTION.' / '.Groups::FANS.'.');

            return self::FAILURE;
        }
        $sources = collect([...array_keys(Groups::filters()), ...array_keys(Groups::extraFilters())])->unique()->map(fn (string $slug) => $sections[$slug] ?? null)->filter()->values()->all();
        $apply = (bool) $this->option('apply');
        $moved = $this->grouped = 0;
        $this->counts = [];

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
            if (isset($sections[$slug])) {
                $this->fill($products, $slug, (int) $sections[$slug], Groups::FILTER, $rules, $order, -10, true);
            }
        }
        foreach (Groups::extraFilters() as $slug => $filters) {
            $sort = -9;
            foreach (isset($sections[$slug]) ? $filters : [] as $name => [$rules, $order]) {
                $this->fill($products, $slug, (int) $sections[$slug], $name, $rules, $order, $sort++, false);
            }
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
        $this->line("metric.boiler_automation.grouped={$this->grouped}");
        foreach ($this->counts as $key => $groups) {
            foreach ($groups as $group => $count) {
                $this->line("group {$key} | {$group}: {$count}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * One select filter of a section: its options in $order, each card of the section on the first matching rule
     * (or «Прочее» when $fallback; otherwise a card no rule knows has no value).
     */
    private function fill(Collection $products, string $slug, int $sectionId, string $name, array $rules, array $order, int $sort, bool $fallback): void
    {
        $attributeId = $this->filterAttribute($sectionId, $name, $sort);
        $options = [];
        foreach (array_values(array_unique([...$order, ...array_values($rules)])) as $i => $option) {
            $options[$option] = DB::table('attribute_options')->where('attribute_id', $attributeId)->where('name', $option)->value('id')
                ?? DB::table('attribute_options')->insertGetId(['attribute_id' => $attributeId, 'name' => $option, 'sort_order' => $i, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('attribute_options')->where('id', $options[$option])->update(['sort_order' => $i]);
        }
        $valued = [];
        foreach ($products->where('category_id', $sectionId) as $product) {
            $group = $fallback ? Groups::group($product->name, $rules) : Groups::match($product->name, $rules);
            if ($group === null) {
                continue;
            }
            $valued[] = $product->id;
            $this->counts["{$slug} {$name}"][$group] = ($this->counts["{$slug} {$name}"][$group] ?? 0) + 1;
            $current = DB::table('product_attribute_values')->where('product_id', $product->id)->where('attribute_id', $attributeId)->first();
            if ($current && (int) $current->option_id === (int) $options[$group]) {
                continue;
            }
            DB::table('product_attribute_values')->updateOrInsert(
                ['product_id' => $product->id, 'attribute_id' => $attributeId],
                ['option_id' => $options[$group], 'value' => null, 'created_at' => $current->created_at ?? now(), 'updated_at' => now()],
            );
            $this->grouped++;
        }
        // A card that left the section (or that no rule knows any more) drops its value.
        DB::table('product_attribute_values')->where('attribute_id', $attributeId)->whereNotIn('product_id', $valued)->delete();
        // Options of an earlier grouping go: no empty group stays in the filter.
        DB::table('attribute_options')->where('attribute_id', $attributeId)->whereNotIn('id', array_values($options))->delete();
    }

    private function filterAttribute(int $categoryId, string $name, int $sort): int
    {
        $key = ['category_id' => $categoryId, 'name' => $name, 'type' => 'select'];
        $id = DB::table('attributes')->where($key)->value('id');
        if ($id) {
            DB::table('attributes')->where('id', $id)->update(['in_filter' => true, 'sort_order' => $sort]);

            return (int) $id;
        }

        return (int) DB::table('attributes')->insertGetId($key + ['in_filter' => true, 'in_product' => false, 'sort_order' => $sort, 'created_at' => now(), 'updated_at' => now()]);
    }
}
