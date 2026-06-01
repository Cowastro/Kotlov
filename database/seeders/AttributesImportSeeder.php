<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AttributesImportSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('product_attribute_values')->truncate();
        DB::table('attribute_options')->truncate();
        DB::table('attributes')->truncate();
        Schema::enableForeignKeyConstraints();

        $this->command->info('Импорт атрибутов...');
        $this->importAttributes();

        $this->command->info('Импорт вариантов атрибутов...');
        $this->importAttributeOptions();

        $this->command->info('Импорт значений атрибутов товаров...');
        $this->importAttributeValues();

        $this->command->info('✅ Атрибуты импортированы!');
    }

    private function importAttributes(): void
    {
        $categoryIds = DB::table('categories')->pluck('id')->toArray();
        $attrs = DB::connection('old')->table('kot_attrs')->get();
        $bar = $this->command->getOutput()->createProgressBar($attrs->count());
        $imported = 0;

        foreach ($attrs as $attr) {
            if (!in_array($attr->cat_id, $categoryIds)) {
                $bar->advance();
                continue;
            }
            DB::table('attributes')->insert([
                'id'            => $attr->id,
                'category_id'   => $attr->cat_id,
                'group_id'      => $attr->group_id ?? 0,
                'sort_order'    => $attr->order ?? 0,
                'type'          => in_array($attr->type, ['select', 'value', 'check']) ? $attr->type : 'value',
                'name'          => $attr->name,
                'suffix'        => $attr->suffix ?: null,
                'in_filter'     => $attr->in_filter ? 1 : 0,
                'in_sort'       => $attr->in_sort ? 1 : 0,
                'in_product'    => $attr->in_product ? 1 : 0,
                'in_brief'      => $attr->in_brief ? 1 : 0,
                'is_comparable' => $attr->in_filter ? 1 : 0,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
            $imported++;
            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();
        $this->command->info("Атрибутов импортировано: {$imported}");
    }

    private function importAttributeOptions(): void
    {
        $attributeIds = DB::table('attributes')->pluck('id')->toArray();
        $opts = DB::connection('old')->table('kot_attrs_opts')->get();
        $bar = $this->command->getOutput()->createProgressBar($opts->count());
        $imported = 0;

        foreach ($opts as $opt) {
            if (!in_array($opt->attribute_id, $attributeIds)) {
                $bar->advance();
                continue;
            }
            DB::table('attribute_options')->insert([
                'id'           => $opt->id,
                'attribute_id' => $opt->attribute_id,
                'name'         => $opt->name,
                'sort_order'   => $opt->position ?? 0,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
            $imported++;
            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();
        $this->command->info("Вариантов атрибутов: {$imported}");
    }

    private function importAttributeValues(): void
    {
        $productIds   = DB::table('products')->pluck('id')->toArray();
        $attributeIds = DB::table('attributes')->pluck('id')->toArray();
        $optionIds    = DB::table('attribute_options')->pluck('id')->toArray();

        $total = DB::connection('old')->table('kot_attrs_value')->count();
        $bar = $this->command->getOutput()->createProgressBar($total);
        $imported = 0;
        $skipped = 0;

        // Глобальный seen — живёт между чанками!
        $seen = [];

        DB::connection('old')
            ->table('kot_attrs_value')
            ->orderBy('product_id')
            ->orderBy('attribute_id')
            ->chunk(1000, function ($rows) use (
                $productIds, $attributeIds, $optionIds,
                $bar, &$imported, &$skipped, &$seen
            ) {
                $insert = [];

                foreach ($rows as $row) {
                    if (!in_array($row->product_id, $productIds) ||
                        !in_array($row->attribute_id, $attributeIds)) {
                        $skipped++;
                        $bar->advance();
                        continue;
                    }

                    // Глобальная проверка уникальности
                    $key = $row->product_id . '_' . $row->attribute_id;
                    if (isset($seen[$key])) {
                        $bar->advance();
                        continue;
                    }
                    $seen[$key] = true;

                    $optionId = ($row->option_id && in_array($row->option_id, $optionIds))
                        ? $row->option_id
                        : null;

                    $insert[] = [
                        'product_id'   => $row->product_id,
                        'attribute_id' => $row->attribute_id,
                        'option_id'    => $optionId,
                        'is_checked'   => $row->is_checked ?? 0,
                        'value'        => $row->value ?: null,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ];

                    $imported++;
                    $bar->advance();
                }

                if (!empty($insert)) {
                    DB::table('product_attribute_values')->insert($insert);
                }
            });

        $bar->finish();
        $this->command->newLine();
        $this->command->info("Значений атрибутов: {$imported}");
        $this->command->warn("Пропущено: {$skipped}");
    }
}