<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\SupplierProduct;
use App\Services\Suppliers\TdKomplektFeed;
use App\Services\Suppliers\TdKomplektMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TdKomplektSyncTest extends TestCase
{
    use RefreshDatabase;

    private function row(array $changes = []): array
    {
        return array_replace(['id' => '12345', 'okdp' => 'ЦБ-12345', 'name' => 'Тройник латунный AV Engineering 1/2', 'vendor_code' => 'AV-12345', 'brand' => 'AV Engineering', 'price' => '12,50', 'price_wholesale' => '8,10', 'warehouse_all' => '10 дней', 'warehouse_main' => '0', 'warehouse_add' => '10 дней', 'warehouse_rb' => '0', 'parentid_name3' => 'Резьбовые латунные фитинги и краны. AV Engineering', 'parentid_name4' => '', 'barcode' => '1234567890123', 'country' => 'Китай', 'description_short' => 'Латунный тройник', 'description' => "Размер: 1/2\nТест: <script>alert(1)</script>", 'media_img' => 'https://content.tools.by/images/products/test/photo.jpg', 'prop_unit' => 'шт.', 'prop_quantity_min' => '3', 'prop_multiplicity' => '1', 'prop_multiplicity_box' => '100', 'prop_manufacturer' => 'Завод', 'prop_importer' => 'ТД Комплект', 'vat' => '20'], $changes);
    }

    private function csv(array $rows): string
    {
        $file = Storage::disk('local')->path('td-test.csv');
        $h = fopen($file, 'wb');
        fwrite($h, "\xEF\xBB\xBF");
        fputcsv($h, array_keys($rows[0]), ';', '"', '');
        foreach ($rows as $row) {
            fputcsv($h, array_values($row), ';', '"', '');
        } fclose($h);

        return $file;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Category::firstOrCreate(['slug' => 'rezbovye-fitingi'], ['name' => 'Резьбовые фитинги', 'parent_id' => 0, 'is_active' => true]);
    }

    public function test_parser_preserves_multiline_csv_and_never_turns_days_into_stock(): void
    {
        $parser = new TdKomplektFeed;
        $rows = $parser->parse($this->csv([$this->row()]));
        $this->assertSame(12.5, $rows[12345]['_data']['retail']);
        $this->assertSame(8.1, $rows[12345]['_data']['wholesale']);
        $this->assertSame(10, $rows[12345]['_data']['delivery_days']);
        $this->assertNull($rows[12345]['_data']['quantity']);
        $this->assertFalse($rows[12345]['_data']['in_stock']);
        $this->assertSame('preorder', $rows[12345]['_data']['stock_status']);
        $this->assertSame(15, $parser->stock('15')['quantity']);
        $this->assertSame('unknown', $parser->stock('уточнить')['stock_status']);
        $this->assertSame('out_of_stock', $parser->stock('0')['stock_status']);
        $this->assertNull(TdKomplektFeed::image('https://evil.example/photo.jpg'));
        $this->assertSame('rezbovye-fitingi', $parser->category($this->row()));
        $this->assertSame('sharovye-krany', $parser->category($this->row(['name' => 'Кран шаровой 1/2'])));
    }

    public function test_create_is_idempotent_stores_wholesale_and_retail_separately_and_handles_missing_stock(): void
    {
        $file = $this->csv([$this->row(['warehouse_all' => '7'])]);
        $this->artisan('supplier:sync-td-komplekt', ['--file' => $file, '--dry-run' => true, '--create-new' => true])->assertSuccessful();
        $this->assertDatabaseMissing('suppliers', ['code' => 'td_komplekt']);
        $this->artisan('supplier:sync-td-komplekt', ['--file' => $file, '--apply' => true, '--create-new' => true])->assertSuccessful();
        $offer = SupplierProduct::whereHas('supplier', fn ($q) => $q->where('code', 'td_komplekt'))->where('source_wp_id', 12345)->firstOrFail();
        $id = $offer->product_id;
        $this->assertEquals(8.1, $offer->price_byn);
        $this->assertEquals(12.5, $offer->product->price);
        $this->assertSame(7, $offer->stock_quantity);
        $this->assertTrue($offer->product->in_stock);
        $this->assertSame('3', $offer->raw['prop_quantity_min']);
        $this->assertStringNotContainsString('<script>', $offer->product->content);
        $this->assertNotEmpty($offer->product->specs);
        $file = $this->csv([$this->row(['price' => '13,00', 'warehouse_all' => '10 дней'])]);
        $this->artisan('supplier:sync-td-komplekt', ['--file' => $file, '--apply' => true, '--create-new' => true])->assertSuccessful();
        $this->assertSame(1, SupplierProduct::whereHas('supplier', fn ($q) => $q->where('code', 'td_komplekt'))->count());
        $offer->refresh();
        $this->assertSame($id, $offer->product_id);
        $this->assertEquals(13, $offer->product->price);
        $this->assertFalse($offer->in_stock);
        $this->assertNull($offer->stock_quantity);
    }

    public function test_exact_match_does_not_overwrite_other_suppliers_retail_and_brand_collision_is_not_linked(): void
    {
        $brand = Brand::create(['name' => 'AV Engineering', 'slug' => 'av-test']);
        $p = Product::create(['name' => 'Другой заголовок', 'sku' => 'AV-12345', 'slug' => 'exact-av', 'brand_id' => $brand->id, 'category_id' => Category::where('slug', 'rezbovye-fitingi')->value('id'), 'price' => 30, 'is_active' => true, 'is_archived' => false]);
        $this->artisan('supplier:sync-td-komplekt', ['--file' => $this->csv([$this->row()]), '--apply' => true, '--create-new' => true])->assertSuccessful();
        $this->assertSame($p->id, SupplierProduct::whereHas('supplier', fn ($q) => $q->where('code', 'td_komplekt'))->first()->product_id);
        $this->assertEquals(30, $p->fresh()->price);
        $this->assertNull((new TdKomplektMatcher)->match($this->row(['brand' => 'OTHER']))['product_id']);
    }

    public function test_ambiguous_articles_and_duplicate_feed_barcodes_do_not_create_or_link_cards(): void
    {
        $b = Brand::create(['name' => 'AV Engineering', 'slug' => 'av-test']);
        foreach (['a', 'b'] as $slug) {
            Product::create(['name' => $slug, 'sku' => 'SKU-'.$slug, 'specs' => [['key' => 'Артикул производителя', 'value' => 'AV-12345']], 'slug' => $slug, 'brand_id' => $b->id, 'category_id' => Category::where('slug', 'rezbovye-fitingi')->value('id'), 'price' => 20, 'is_active' => true, 'is_archived' => false]);
        }
        $before = Product::count();
        $this->artisan('supplier:sync-td-komplekt', ['--file' => $this->csv([$this->row(), $this->row(['id' => '67890', 'vendor_code' => 'OTHER-456'])]), '--apply' => true, '--create-new' => true])->assertSuccessful();
        $this->assertSame($before, Product::count());
        $this->assertSame(0, SupplierProduct::whereHas('supplier', fn ($q) => $q->where('code', 'td_komplekt'))->whereNotNull('product_id')->count());
    }

    public function test_identifier_change_stays_in_review_and_large_price_change_is_not_silently_published(): void
    {
        $this->artisan('supplier:sync-td-komplekt', ['--file' => $this->csv([$this->row()]), '--apply' => true, '--create-new' => true])->assertSuccessful();
        $id = SupplierProduct::whereHas('supplier', fn ($q) => $q->where('code', 'td_komplekt'))->first()->product_id;
        $this->artisan('supplier:sync-td-komplekt', ['--file' => $this->csv([$this->row(['price' => '1000'])]), '--apply' => true])->assertSuccessful();
        $this->assertEquals(12.5, Product::find($id)->price);
        for ($i = 0; $i < 2; $i++) {
            $this->artisan('supplier:sync-td-komplekt', ['--file' => $this->csv([$this->row(['vendor_code' => 'CHANGED', 'warehouse_all' => '99'])]), '--apply' => true, '--create-new' => true])->assertSuccessful();
        }
        $offer = SupplierProduct::whereHas('supplier', fn ($q) => $q->where('code', 'td_komplekt'))->first();
        $this->assertSame($id, $offer->product_id);
        $this->assertFalse($offer->in_stock);
        $this->assertSame('review', $offer->match_status);
    }

    public function test_remote_fetch_is_cached_and_errors_do_not_expose_the_access_url(): void
    {
        $url = 'https://tools.by/api/eshop-export/AAAAAAAAAAAAAAAAAAAAAAAAAA?format=csv';
        Storage::disk('local')->put(TdKomplektFeed::DIRECTORY.'/config.json', json_encode(['url' => $url]));
        $rows = [];
        for ($i = 1; $i <= 100; $i++) {
            $rows[] = $this->row(['id' => (string) $i, 'okdp' => 'OK-'.$i]);
        }
        Http::fake([$url => Http::response(file_get_contents($this->csv($rows)), 200)]);
        $feed = new TdKomplektFeed;
        $this->assertCount(100, $feed->load());
        $this->assertCount(100, $feed->load());
        Http::assertSentCount(1);
        Storage::disk('local')->delete(TdKomplektFeed::DIRECTORY.'/latest.csv');
        try {
            $feed->load();
            $this->fail('Request spacing must be enforced');
        } catch (\RuntimeException $e) {
            $this->assertStringNotContainsString('AAAAAAAA',$e->getMessage());
        }
        Http::assertSentCount(1);
    }
}
