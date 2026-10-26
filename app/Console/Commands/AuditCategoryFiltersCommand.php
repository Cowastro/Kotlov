<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Read-only: the catalogue filters of a category — attribute, type, options and how many
 * cards carry each — so new cards can be put into the filters that already exist.
 *
 *   php artisan catalog:audit-category-filters elektricheskie
 */
class AuditCategoryFiltersCommand extends Command
{
    protected $signature = 'catalog:audit-category-filters {categories* : Category slugs}';

    protected $description = 'List the filter attributes of categories with their options and usage';

    public function handle(): int
    {
        foreach ($this->argument('categories') as $slug) {
            $category = DB::table('categories')->where('slug', $slug)->first(['id', 'parent_id']);
            if (! $category) {
                $this->line('audit.category_filters.missing='.Str::slug($slug));

                continue;
            }
            $products = DB::table('products')->where('category_id', $category->id)->where('is_archived', false);
            $this->line(sprintf('audit.category_filters.category=%s:%d:%d:%d', Str::slug($slug), $category->id, (int) $category->parent_id, (clone $products)->count()));

            $attributes = DB::table('attributes')->where('category_id', $category->id)->orderBy('sort_order')
                ->get(['id', 'name', 'type', 'suffix', 'in_filter', 'in_product']);
            foreach ($attributes as $attribute) {
                $used = DB::table('product_attribute_values')->where('attribute_id', $attribute->id)->count();
                if (! $attribute->in_filter && $used < 5) {
                    continue;
                }
                $this->line(sprintf('audit.category_filters.attribute=%d:%s:%s:%s:%d:%d:%d', $attribute->id, Str::slug((string) $attribute->name, '_') ?: 'none',
                    Str::slug((string) $attribute->type, '_') ?: 'none', Str::slug((string) $attribute->suffix, '_') ?: 'none', (int) $attribute->in_filter, (int) $attribute->in_product, $used));
                if (! $attribute->in_filter) {
                    continue;
                }
                DB::table('attribute_options as o')
                    ->leftJoin('product_attribute_values as v', 'v.option_id', '=', 'o.id')
                    ->where('o.attribute_id', $attribute->id)
                    ->groupBy('o.id', 'o.name')
                    ->selectRaw('o.id, o.name, COUNT(v.id) as used')
                    ->orderBy('o.id')
                    ->get()
                    ->each(fn ($option) => $this->line(sprintf('audit.category_filters.option=%d:%d:%s:%d', $attribute->id, $option->id,
                        Str::slug(str_replace(['.', ','], '-', (string) $option->name), '_') ?: 'none', $option->used)));
                $values = DB::table('product_attribute_values')->where('attribute_id', $attribute->id)->whereNull('option_id')
                    ->whereNotNull('value')->groupBy('value')->selectRaw('value, COUNT(*) as used')->orderByDesc('used')->limit(15)->get();
                foreach ($values as $value) {
                    $this->line(sprintf('audit.category_filters.value=%d:%s:%d', $attribute->id, Str::slug(str_replace(['.', ','], '-', (string) $value->value), '_') ?: 'none', $value->used));
                }
            }
        }

        return self::SUCCESS;
    }
}
