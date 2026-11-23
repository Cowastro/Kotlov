<?php

namespace Tests\Feature;

use App\Console\Commands\RepairOneC2465LinkCommand;
use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\OneCItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RepairOneC2465LinkTest extends TestCase
{
    use RefreshDatabase;

    private function family(): array
    {
        Storage::fake('local');
        $source = IntegrationSource::updateOrCreate(['code' => 'onec'], ['name' => '1C']);
        $category = Category::create(['slug' => 'repair-test', 'name' => 'Test']);
        $key = RepairOneC2465LinkCommand::KEY;
        $item = OneCItem::create(['ref_key' => $key, 'code' => 'УТ-00002465', 'description' => 'Проходник', 'stock_on_hand' => 31, 'stock_reserved' => 7]);
        $variants = collect();
        foreach (['b6' => '030290', 'b7' => '030292', 'b8' => '030291', 'b9' => '030293', 'ba' => '030294', 'bb' => '030295'] as $suffix => $sku) {
            $product = Product::create(['category_id' => $category->id, 'sku' => 'KOTLOV-'.$sku, 'name' => $sku, 'slug' => $sku, 'price' => 50, 'is_archived' => false]);
            $variants->push(IntegrationProduct::create(['integration_source_id' => $source->id, 'external_id' => $key.'#3f5789'.$suffix.'-d537-11e9-a218-1c1b0d1de7c2',
                'name' => $sku, 'product_id' => $product->id, 'match_status' => 'matched', 'price' => 31.67, 'stock_quantity' => 2]));
        }
        $base = IntegrationProduct::create(['integration_source_id' => $source->id, 'external_id' => $key, 'name' => 'Общая запись',
            'product_id' => $variants->first()->product_id, 'match_status' => 'matched', 'price' => 99, 'stock_quantity' => 31]);

        return [$item->fresh(), $base->fresh(), $variants->map(fn ($row) => $row->fresh())];
    }

    public function test_dry_run_changes_nothing(): void
    {
        [, $base] = $this->family();
        $before = $base->getAttributes();
        $this->artisan('onec:repair-2465-link')->assertSuccessful();
        $this->assertSame($before, $base->fresh()->getAttributes());
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_apply_preserves_every_non_link_field_and_all_variants_and_is_idempotent(): void
    {
        [$item, $base, $variants] = $this->family();
        $allowed = ['product_id', 'match_status', 'match_method', 'match_confidence', 'matched_at', 'updated_at'];
        $before = Arr::except($base->getAttributes(), $allowed);
        $variantData = $variants->map->getAttributes()->all();
        $cards = Product::all()->map->getAttributes()->all();
        $this->artisan('onec:repair-2465-link --apply')->assertSuccessful();
        $this->assertSame($before, Arr::except($base->fresh()->getAttributes(), $allowed));
        $this->assertNull($base->fresh()->product_id);
        $this->assertSame('ignored', $base->fresh()->match_status);
        $this->assertSame($variantData, $variants->map(fn ($v) => $v->fresh()->getAttributes())->all());
        $this->assertSame($cards, Product::all()->map->getAttributes()->all());
        $this->assertSame(7, IntegrationProduct::count());
        $this->assertFalse($item->fresh()->link_conflict);
        $this->assertSame(6, $item->fresh()->site_link_count);
        $this->assertEquals(31, $item->fresh()->stock_on_hand);
        $this->assertEquals(7, $item->fresh()->stock_reserved);
        $auditCount = DB::table('onec_item_link_changes')->count();
        $this->artisan('onec:repair-2465-link --apply')->assertSuccessful();
        $this->assertSame($auditCount, DB::table('onec_item_link_changes')->count());
        $files = Storage::disk('local')->allFiles();
        $this->assertCount(1, $files);
        $backup = json_decode(Storage::disk('local')->get($files[0]), true);
        $this->assertCount(7, $backup['rows']);
    }

    public function test_changed_characteristic_refuses_repair_atomically(): void
    {
        [, $base, $variants] = $this->family();
        $variants->last()->update(['product_id' => $variants->first()->product_id]);
        $before = $base->getAttributes();
        $this->artisan('onec:repair-2465-link --apply')->assertFailed();
        $this->assertSame($before, $base->fresh()->getAttributes());
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }
}
