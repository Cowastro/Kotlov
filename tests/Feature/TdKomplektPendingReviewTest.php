<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Suppliers\TdKomplektFeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TdKomplektPendingReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_links_the_exact_ld_article_without_merging_other_supplier_cards(): void
    {
        Storage::fake('local');
        $brand = Brand::create(['name' => 'LD', 'slug' => 'ld-review-test']);
        $category = Category::firstOrCreate(['slug' => 'rezbovye-fitingi'], ['name' => 'Фитинги', 'parent_id' => 0]);
        foreach ([35777, 38337] as $id) {
            Product::forceCreate(['id' => $id, 'brand_id' => $brand->id, 'category_id' => $category->id,
                'name' => 'Ниппель LD 1/2', 'sku' => 'LD.65.501.15', 'slug' => 'ld-review-'.$id, 'price' => 12, 'is_active' => true]);
        }
        $supplier = Supplier::create(['code' => 'td_komplekt', 'name' => 'ТД Комплект', 'is_active' => true]);
        $offer = SupplierProduct::create(['supplier_id' => $supplier->id, 'source_wp_id' => 1637569,
            'supplier_article' => 'LD.65.501.15', 'supplier_name' => 'Ниппель LD 1/2',
            'raw' => ['brand' => 'LD', 'vendor_code' => 'LD.65.501.15', 'name' => 'Ниппель LD 1/2']]);
        $this->artisan('supplier:review-td-komplekt-remaining', ['--apply' => true])->assertSuccessful();
        $this->assertSame(35777, $offer->fresh()->product_id);
        $this->assertFalse(Product::findOrFail(38337)->is_archived);
        $this->assertDatabaseHas('supplier_review_decisions', ['supplier_product_id' => $offer->id, 'status' => 'applied']);
    }

    public function test_clearance_goods_are_drafts_and_display_equipment_stays_outside_the_catalogue(): void
    {
        Storage::fake('local');
        $supplier = Supplier::create(['code' => 'td_komplekt', 'name' => 'ТД Комплект', 'is_active' => true]);
        $manifest = json_decode(file_get_contents(resource_path('data/td-komplekt-review-2026-11-17.json')), true);
        $records = collect($manifest['clearance'])->whereIn('id', ['1700366', '1700705']);
        Category::firstOrCreate(['slug' => 'instrument-dlya-prochistki-trub'], ['name' => 'Прочистка', 'parent_id' => 0]);
        foreach ($records as $record) {
            SupplierProduct::create(['supplier_id' => $supplier->id, 'source_wp_id' => $record['id'],
                'supplier_article' => $record['article'], 'supplier_name' => $record['name'], 'price_byn' => 5,
                'raw' => ['brand' => $record['brand'], 'vendor_code' => $record['article'], 'name' => $record['name'], 'retail' => 10],
            ]);
        }
        $this->artisan('supplier:review-td-komplekt-remaining')->assertSuccessful();
        $this->assertNull(SupplierProduct::where('supplier_id', $supplier->id)->where('source_wp_id', 1700366)->value('product_id'));
        $this->artisan('supplier:review-td-komplekt-remaining', ['--apply' => true])->assertSuccessful();
        $offer = SupplierProduct::where('supplier_id', $supplier->id)->where('source_wp_id', 1700366)->firstOrFail();
        $this->assertFalse($offer->product->is_active);
        $this->assertSame('supplier_review', $offer->product->created_via);
        $this->assertNull(SupplierProduct::where('supplier_id', $supplier->id)->where('source_wp_id', 1700705)->value('product_id'));
        $this->artisan('supplier:review-td-komplekt-remaining', ['--apply' => true])->assertSuccessful();
        $this->assertSame($offer->product_id, $offer->fresh()->product_id);
        $report = json_decode(Storage::disk('local')->get(TdKomplektFeed::DIRECTORY.'/remaining-review.json'), true);
        $this->assertSame(1, $report['counts']['draft']);
        $this->assertSame(1, $report['counts']['display_equipment']);
    }

    public function test_plumbing_sections_use_grouped_searchable_navigation_and_hide_empty_categories(): void
    {
        $root = Category::where('slug', 'santehnika')->firstOrFail();
        $showers = Category::firstOrCreate(['slug' => 'dushevye-sistemy'], ['parent_id' => $root->id, 'name' => 'Душевые системы и стойки', 'is_active' => true]);
        Product::create(['category_id' => $showers->id, 'name' => 'Душевая система для навигации', 'slug' => 'navigation-shower',
            'price' => 100, 'is_active' => true, 'is_archived' => false, 'in_stock' => true]);
        $this->get('/santehnika')->assertOk()->assertSee('data-accessories-nav', false)
            ->assertSee('Смесители и душ')->assertSee('Душевые системы и стойки')->assertSee('Найти подраздел');
        $this->get('/aksessuary-dlya-vannoy')->assertOk()->assertSee('content="noindex, follow"', false);
    }
}
