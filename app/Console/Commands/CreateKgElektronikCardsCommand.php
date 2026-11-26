<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * KG Elektronik (Poland) comes through ООО «СанБизнесГруп»: the brand, its cards from the manufacturer's site
 * (texts in Russian, specs, photos in public/img/products/kg-elektronik, manuals) and a preorder offer of СанБизнесГруп
 * with the purchase cost (catalogue 2027 −30 %). A card already there (same SKU) keeps its price and content
 * unless --refresh; a card without photos stays hidden.
 */
class CreateKgElektronikCardsCommand extends Command
{
    protected $signature = 'catalog:create-kg-elektronik
        {file=database/data/kg_elektronik_2027.json}
        {--refresh : Rewrite the content of cards that already exist}
        {--apply : Save; otherwise only show the plan}';

    protected $description = 'Create the KG Elektronik brand and cards with the ООО «СанБизнесГруп» preorder offer.';

    public function handle(): int
    {
        $path = base_path((string) $this->argument('file'));
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (! is_array($data) || empty($data['products'])) {
            $this->error('Нет данных: '.$path);

            return self::FAILURE;
        }
        $supplier = Supplier::query()->where('code', $data['supplier_code'])->first();
        if (! $supplier) {
            $this->error('Поставщик '.$data['supplier_code'].' не найден.');

            return self::FAILURE;
        }
        $categories = Category::query()->whereIn('slug', collect($data['products'])->pluck('category')->unique())->pluck('id', 'slug');
        $missing = collect($data['products'])->pluck('category')->unique()->diff($categories->keys());
        if ($missing->isNotEmpty()) {
            $this->error('Нет разделов: '.$missing->implode(', '));

            return self::FAILURE;
        }

        $metrics = ['created' => 0, 'refreshed' => 0, 'kept' => 0, 'hidden' => 0, 'offers' => 0];
        $apply = (bool) $this->option('apply');
        DB::beginTransaction();
        try {
            $brand = $this->brand($data['brand'], $supplier);
            foreach ($data['products'] as $row) {
                $product = Product::query()->where('sku', $row['sku'])->first();
                $state = $product ? ($this->option('refresh') ? 'refreshed' : 'kept') : 'created';
                if (! $product || $this->option('refresh')) {
                    $product = $this->saveCard($product, $row, $brand->id, (int) $categories[$row['category']]);
                }
                $this->offer($supplier, $product, $row, (int) $data['delivery_days']);
                $metrics[$state]++;
                $metrics['offers']++;
                $metrics['hidden'] += $product->is_active ? 0 : 1;
                $this->line(sprintf('%s %s | %s BYN | себест. %s | %s', $state, $row['sku'], $row['price'], $row['cost_byn'], $product->is_active ? 'на сайте' : 'скрыта'));
            }
            if (! $apply) {
                throw new RuntimeException('dry-run');
            }
            DB::commit();
            Cache::forget('home:data');
            Cache::forget('home:data:v6');
        } catch (RuntimeException $e) {
            DB::rollBack();
            if ($e->getMessage() !== 'dry-run') {
                throw $e;
            }
        }

        $this->line('metric.status='.($apply ? 'applied' : 'dry_run'));
        foreach ($metrics as $name => $value) {
            $this->line("metric.kg_elektronik.{$name}={$value}");
        }

        return self::SUCCESS;
    }

    private function brand(array $data, Supplier $supplier): Brand
    {
        $brand = Brand::query()->where('slug', $data['slug'])->first() ?? Brand::query()->where('name', $data['name'])->first();
        $fields = ['name' => $data['name'], 'slug' => $data['slug'], 'h1' => $data['h1'], 'country' => $data['country'],
            'producer' => $data['producer'], 'content' => $data['content'], 'meta_title' => $data['meta_title'],
            'meta_description' => $data['meta_description'], 'is_active' => true];
        $brand ? $brand->fill($brand->content ? array_diff_key($fields, ['content' => 1]) : $fields)->save() : $brand = Brand::query()->create($fields);
        DB::table('brand_supplier')->updateOrInsert(['brand_id' => $brand->id, 'supplier_id' => $supplier->id], ['created_at' => now(), 'updated_at' => now()]);

        return $brand;
    }

    private function saveCard(?Product $product, array $row, int $brandId, int $categoryId): Product
    {
        $visible = ! $row['hidden'] && ! empty($row['images']);
        $specs = array_merge($row['specs'], [
            ['key' => 'Модель', 'value' => $row['model']],
            ['key' => 'Производитель', 'value' => 'KG Elektronik'],
            ['key' => 'Страна производства', 'value' => 'Польша'],
        ]);
        $fields = [
            'category_id' => $categoryId, 'brand_id' => $brandId, 'name' => $row['name'], 'h1' => $row['name'],
            'short_description' => $row['short_description'], 'content' => $row['content'], 'specs' => $specs,
            'images' => $row['images'], 'documents' => $row['documents'], 'content_source_url' => $row['source_url'],
            'meta_title' => $row['meta_title'], 'meta_description' => $row['meta_description'],
        ];
        if ($product) {
            $product->fill($fields)->save();

            return $product;
        }

        return Product::query()->create($fields + [
            'slug' => $this->uniqueSlug($row['name']), 'sku' => $row['sku'], 'price' => $row['price'], 'currency' => 'BYN',
            'unit' => 'шт', 'is_active' => $visible, 'is_archived' => false, 'is_new' => true,
            'created_via' => $visible ? null : Product::CREATED_VIA_AWAITING_PHOTOS,
            'requires_price_confirmation' => false, 'availability_status' => Product::AVAILABILITY_CHECK,
        ]);
    }

    private function offer(Supplier $supplier, Product $product, array $row, int $days): void
    {
        $article = $row['sku'];
        $offer = SupplierProduct::query()->firstOrNew(['supplier_id' => $supplier->id, 'supplier_article' => $article]);
        $offer->fill([
            'product_id' => $product->id, 'product_sku' => $product->sku, 'supplier_article_normalized' => $article,
            'supplier_name' => 'KG Elektronik '.$row['model'], 'source_url' => $row['source_url'],
            'price' => $row['cost_byn'], 'currency' => 'BYN', 'currency_rate' => 1, 'price_byn' => $row['cost_byn'],
            'in_stock' => false, 'stock_quantity' => null, 'stock_status' => 'preorder', 'delivery_days' => $days,
            'stock_text' => 'Под заказ', 'warehouse_name' => 'ООО «СанБизнесГруп»',
            'match_status' => 'matched', 'match_confidence' => 'manual_price_list',
            'raw' => ['brand' => 'KG Elektronik', 'catalogue' => 'CENNIK 2027', 'price_pln' => $row['price_pln'], 'discount' => 0.3, 'retail_byn' => $row['price']],
            'last_synced_at' => now(),
        ])->save();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name), 180, '');
        $slug = $base;
        for ($i = 2; Product::query()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
