<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectroluxEftRrDuplicateMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_merges_the_duplicate_into_the_original_card_and_redirects_both_old_paths(): void
    {
        $oldCategory = Category::query()->create([
            'name' => 'Обогреватели',
            'slug' => 'obogrevateli',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $canonicalCategory = Category::query()->create([
            'name' => 'Электрические конвекторы',
            'slug' => 'elektricheskie-konvektoryi',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $brand = Brand::query()->create(['name' => 'Electrolux', 'slug' => 'electrolux']);
        $canonical = Product::query()->create([
            'category_id' => $oldCategory->id,
            'brand_id' => $brand->id,
            'sku' => 'KOTLOV-000726',
            'name' => 'Electrolux EFT/RR',
            'slug' => 'electrolux-eftrr',
            'price' => 33,
            'content' => '<p>Ошибочно описан как обогреватель.</p>',
            'is_active' => true,
            'is_archived' => false,
        ]);
        $duplicate = Product::query()->create([
            'category_id' => $canonicalCategory->id,
            'brand_id' => $brand->id,
            'sku' => 'PS-000.18762',
            'name' => 'Комплект шасси Electrolux Rapid Transformer System EFT/RR',
            'slug' => 'komplekt-sassi-electrolux-rapid-transformer-system-eftrr',
            'price' => 33,
            'content' => '<p>Правильное описание комплекта шасси.</p>',
            'short_description' => 'Комплект шасси.',
            'images' => ['img/products/electrolux/electrolux-eft-rr-chassis.jpg'],
            'specs' => [['key' => 'Модель', 'value' => 'EFT/RR']],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $source = IntegrationSource::query()->create([
            'code' => 'eft-rr-duplicate-test',
            'name' => '1С СанБизнесГруп',
            'is_active' => true,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $duplicate->id,
            'external_id' => 'eft-rr',
            'name' => 'Комплект шасси EFT/RR',
            'stock_quantity' => 0,
            'match_status' => 'matched',
            'match_method' => 'manual_created',
        ]);

        $migration = require database_path('migrations/2026_10_13_220000_merge_electrolux_eft_rr_duplicate.php');
        $migration->up();

        $canonical->refresh();
        $duplicate->refresh();
        $item->refresh();

        $this->assertSame($canonicalCategory->id, $canonical->category_id);
        $this->assertSame('Комплект шасси Electrolux Rapid Transformer System EFT/RR', $canonical->name);
        $this->assertSame('<p>Правильное описание комплекта шасси.</p>', $canonical->content);
        $this->assertSame('check', $canonical->availability_status);
        $this->assertFalse($canonical->in_stock);
        $this->assertFalse($duplicate->is_active);
        $this->assertTrue($duplicate->is_archived);
        $this->assertSame($canonical->id, $item->product_id);
        $this->assertSame('manual_search', $item->match_method);
        $this->assertDatabaseHas('redirects', [
            'from_url' => '/obogrevateli/electrolux-eftrr',
            'to_url' => '/elektricheskie-konvektoryi/electrolux-eftrr',
            'status_code' => 301,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('redirects', [
            'from_url' => '/elektricheskie-konvektoryi/komplekt-sassi-electrolux-rapid-transformer-system-eftrr',
            'to_url' => '/elektricheskie-konvektoryi/electrolux-eftrr',
            'status_code' => 301,
            'is_active' => true,
        ]);
    }
}
