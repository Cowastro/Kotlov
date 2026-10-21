<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishZubrHeatTransferFluidsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function category(): Category
    {
        $category = Category::query()->create([
            'name' => 'Теплоносители (Антифриз)',
            'slug' => 'teplonositeli',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        foreach (['Вид жидкости' => ['Концентрат', 'Раствор'], 'Основа жидкости' => ['Пропиленгликоль', 'Этиленгликоль']] as $name => $options) {
            $attribute = Attribute::query()->create([
                'category_id' => $category->id, 'type' => 'select', 'name' => $name,
                'in_filter' => true, 'in_product' => true,
            ]);
            foreach ($options as $option) {
                AttributeOption::query()->create(['attribute_id' => $attribute->id, 'name' => $option]);
            }
        }

        return $category;
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->category();
        $products = Product::query()->count();

        $this->artisan('catalog:publish-zubr-teplonositeli')
            ->expectsOutputToContain('metric.zubr.planned_products=12')
            ->assertSuccessful();

        $this->assertSame($products, Product::query()->count());
        $this->assertFalse(Supplier::query()->where('code', 'ecosmartservice')->exists());
    }

    public function test_apply_publishes_products_supplier_offers_and_filters_idempotently(): void
    {
        $this->category();

        $this->artisan('catalog:publish-zubr-teplonositeli --apply')->assertSuccessful();
        $this->artisan('catalog:publish-zubr-teplonositeli --apply')->assertSuccessful();

        $this->assertSame(12, Product::query()->where('slug', 'like', 'teplonositel-zubr-%')->count());
        $supplier = Supplier::query()->where('code', 'ecosmartservice')->sole();
        $brand = Brand::query()->where('slug', 'zubr')->sole();
        $this->assertTrue($supplier->brands()->whereKey($brand->id)->exists());
        $this->assertSame(12, SupplierProduct::query()->where('supplier_id', $supplier->id)->count());
        $this->assertSame(24, ProductAttributeValue::query()->whereIn('product_id', Product::query()->where('brand_id', $brand->id)->select('id'))->count());

        $product = Product::query()->where('slug', 'teplonositel-zubr-30-etilenglikol-20-kg')->sole();
        $this->assertSame('103.00', (string) $product->price);
        $this->assertCount(2, $product->images);
        $offer = SupplierProduct::query()->where('product_id', $product->id)->sole();
        $this->assertSame('89.00', (string) $offer->price_byn);
        $this->assertSame('preorder', $offer->stock_status);
        $this->assertSame(7, $offer->delivery_days);
        $this->assertTrue($product->fresh()->canBeAddedToCart());
        $this->assertSame('Поставка до 7 рабочих дней', $product->fresh()->publicAvailabilityLabel());

        $concentrate = Product::query()->where('slug', 'teplonositel-zubr-eko-100-propilenglikol-10-kg')->sole();
        $this->assertCount(3, $concentrate->images);
        $this->assertSame('Концентрат', ProductAttributeValue::query()->where('product_id', $concentrate->id)
            ->whereHas('attribute', fn ($q) => $q->where('name', 'Вид жидкости'))->sole()->option->name);
    }

    public function test_markup_pricing_uses_purchase_plus_fifteen_percent(): void
    {
        $this->category();

        $this->artisan('catalog:publish-zubr-teplonositeli --apply --pricing=markup15')->assertSuccessful();

        $this->assertSame('134.00', (string) Product::query()->where('slug', 'teplonositel-zubr-eko-30-propilenglikol-20-kg')->value('price'));
    }

    public function test_product_page_offers_package_selector(): void
    {
        $this->category();
        $this->artisan('catalog:publish-zubr-teplonositeli --apply')->assertSuccessful();

        $this->get('/teplonositeli/teplonositel-zubr-65-etilenglikol-10-kg')
            ->assertOk()
            ->assertSeeText('Выберите фасовку:')
            ->assertSeeText('10 кг')
            ->assertSeeText('20 кг')
            ->assertSee('/teplonositeli/teplonositel-zubr-65-etilenglikol-20-kg', false)
            ->assertSeeText('Как разбавить ZUBR -65');
    }

    public function test_category_page_shows_selection_guide_and_faq_schema(): void
    {
        $this->category();
        $this->artisan('catalog:publish-zubr-teplonositeli --apply')->assertSuccessful();

        $this->get('/teplonositeli')
            ->assertOk()
            ->assertSeeText('Как выбрать теплоноситель для отопления')
            ->assertSeeText('Сколько теплоносителя покупать')
            ->assertSeeText('Вопросы о выборе теплоносителя')
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('<title>Теплоноситель для отопления: цены, купить', false);

        $this->get('/teplonositeli?page=2')->assertDontSeeText('Как выбрать теплоноситель для отопления');
    }
}
