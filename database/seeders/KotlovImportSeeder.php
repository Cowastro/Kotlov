<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class KotlovImportSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        DB::table('products')->truncate();
        DB::table('brands')->truncate();
        DB::table('categories')->truncate();

        Schema::enableForeignKeyConstraints();

        $this->command->info('Импорт категорий...');
        $this->importCategories();

        $this->command->info('Импорт брендов...');
        $this->importBrands();

        $this->command->info('Импорт товаров...');
        $this->importProducts();

        $this->command->info('✅ Импорт завершён!');
    }

    private function importCategories(): void
    {
        $rows = DB::connection('old')->table('kot_cat')->get();

        $bar = $this->command->getOutput()->createProgressBar($rows->count());

        foreach ($rows as $row) {
            DB::table('categories')->insert([
                'id'               => $row->id,
                'parent_id'        => $row->parent_id ?? 0,
                'name'             => $row->name ?: 'Категория ' . $row->id,
                'slug' => $this->makeCleanSlug($row->alias ?? null, $row->name ?? 'category', $row->id, 'categories'),
                'h1'               => $row->h1 ?? null,
                'type'             => $row->type ?? 'main',
                'sort_order'       => $row->order_cat ?? 0,
                'is_active'        => !empty($row->active),
                'content'          => $row->content ?? null,
                'meta_title'       => $row->title ?? null,
                'meta_keywords'    => $row->keywords ?? null,
                'meta_description' => $row->description ?? null,
                'image'            => null,
                'icon'             => null,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();
        $this->command->info("Категорий импортировано: {$rows->count()}");
    }

    private function importBrands(): void
    {
        $rows = DB::connection('old')->table('kot_brands')->get();

        $bar = $this->command->getOutput()->createProgressBar($rows->count());

        foreach ($rows as $row) {
            DB::table('brands')->insert([
                'id'               => $row->id,
                'name'             => $row->name ?: 'Бренд ' . $row->id,
                'slug'             => $this->makeCleanSlug($row->alias ?? null, $row->name ?? 'brand', $row->id, 'brands'),
                'h1'               => $row->h1 ?? null,
                'logo'             => $row->photo ?? null,
                'country'          => null,
                'producer'         => $row->producer ?? null,
                'content'          => $row->content ?? null,
                'meta_title'       => $row->title ?? null,
                'meta_keywords'    => $row->keywords ?? null,
                'meta_description' => $row->description ?? null,
                'is_active'        => !empty($row->active),
                'is_featured'      => false,
                'sort_order'       => 0,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();
        $this->command->info("Брендов импортировано: {$rows->count()}");
    }

    private function importProducts(): void
    {
        $categoryIds = DB::table('categories')->pluck('id')->toArray();
        $brandIds = DB::table('brands')->pluck('id')->toArray();

        $total = DB::connection('old')->table('kot_products')->count();

        $bar = $this->command->getOutput()->createProgressBar($total);

        $imported = 0;
        $skipped = 0;
        $usedProductSlugs = [];

        DB::connection('old')
            ->table('kot_products')
            ->orderBy('id')
            
           ->chunk(500, function ($rows) use ($categoryIds, $brandIds, $bar, &$imported, &$skipped, &$usedProductSlugs) {
                $insert = [];

                foreach ($rows as $row) {
                    if (empty($row->category_id) || !in_array($row->category_id, $categoryIds)) {
                        $skipped++;
                        $bar->advance();
                        continue;
                    }

                    $insert[] = [
                        'id'                => $row->id,
                        'category_id'       => $row->category_id,
                        'brand_id'          => !empty($row->brand_id) && in_array($row->brand_id, $brandIds) ? $row->brand_id : null,
                        'supplier_id'       => null,

                        'name'              => $row->name ?: 'Товар ' . $row->id,
                        'slug' => $this->makeProductSlug(
    $row->alias ?? null,
    $row->name ?? 'product',
    $row->id,
    $usedProductSlugs
),
                        'h1'                => $row->h1 ?? null,
                        'sku'               => $row->articul ?? null,

                        'price'             => $this->normalizePrice($row->price_final ?? $row->price ?? 0),
                        'price_old'         => !empty($row->price_old) ? $this->normalizePrice($row->price_old) : null,
                        'currency'          => 'BYN',

                        'content'           => $row->content ?? null,
                        'short_description' => null,
                        'images'            => json_encode($this->parseImages($row->photo ?? null), JSON_UNESCAPED_UNICODE),
                        'specs'             => null,
                        'video_url'         => null,

                        'weight'            => null,
                        'unit'              => 'шт',
                        'warranty'          => null,

                        'is_active'         => !empty($row->active),
                        'in_stock'          => true,
                        'stock_qty'         => null,
                        'is_featured'       => !empty($row->popular),
                        'is_new'            => !empty($row->new_galery),
                        'is_sale'           => !empty($row->hot_price),
                        'sort_order'        => 0,

                        'meta_title'        => $row->title ?? null,
                        'meta_keywords'     => $row->keywords ?? null,
                        'meta_description'  => $row->description ?? null,

                        'rating'            => (float)($row->rating ?? 0),
                        'reviews_count'     => (int)($row->count_rev ?? 0),
                        'views_count'       => 0,

                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ];

                    $imported++;
                    $bar->advance();
                }

                if (!empty($insert)) {
                    DB::table('products')->insert($insert);
                }
            });

        $bar->finish();
        $this->command->newLine();
        $this->command->info("Товаров импортировано: {$imported}");
        $this->command->warn("Пропущено без категории: {$skipped}");
    }

    private function makeCleanSlug(?string $alias, string $name, int $id, string $table): string
    {
        $slug = trim((string) $alias);

        if ($slug === '') {
            $slug = Str::slug($name);
        }

        if ($slug === '') {
            $slug = 'item-' . $id;
        }

        $baseSlug = $slug;
        $counter = 2;

        while (
            DB::table($table)
                ->where('slug', $slug)
                ->where('id', '!=', $id)
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function normalizePrice(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $value = str_replace(',', '.', (string) $value);

        return round((float) $value, 2);
    }

    private function parseImages(?string $photo): array
    {
        if (empty($photo)) {
            return [];
        }

        $decoded = json_decode($photo, true);

        if (is_array($decoded)) {
            return array_values(array_filter($decoded));
        }

        return [$photo];
    }
private function makeProductSlug(?string $alias, string $name, int $id, array &$usedSlugs): string
{
    $slug = trim((string) $alias);

    if ($slug === '') {
        $slug = Str::slug($name);
    }

    if ($slug === '') {
        $slug = 'product-' . $id;
    }

    $baseSlug = $slug;

    if (in_array($slug, $usedSlugs, true) || DB::table('products')->where('slug', $slug)->exists()) {
        $slug = $baseSlug . '-' . $id;
    }

    $usedSlugs[] = $slug;

    return $slug;
}
}