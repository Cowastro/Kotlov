<?php

namespace Tests\Feature;

use App\Console\Commands\CreateChistyBeregCardsCommand;
use App\Console\Commands\SyncChistyBeregCommand;
use App\Http\Middleware\ResolveMergedCartProducts;
use App\Models\B2bSavedCart;
use App\Models\Category;
use App\Models\CustomerCart;
use App\Models\CustomerSelection;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\MergeConfirmedBeregProducts;
use App\Services\MergedProductIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class ConfirmedBeregProductMergeTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $category = Category::create(['name' => 'Merge fixture', 'slug' => 'merge-fixture', 'is_active' => true]);
        $data = ['category_id' => $category->id, 'name' => 'Exact fixture valve', 'sku' => 'BEREG-TEST-EXACT', 'price' => 123.45, 'is_active' => true, 'is_archived' => false, 'specs' => [['key' => 'Код производителя', 'value' => 'MAKER-EXACT']]];
        $old = Product::create($data + ['slug' => 'merge-old']);
        $master = Product::create($data + ['slug' => 'merge-master']);
        $supplier = Supplier::where('code', 'chisty_bereg')->firstOrFail();
        $offer = SupplierProduct::create(['supplier_id' => $supplier->id, 'supplier_article' => 'TEST-EXACT', 'supplier_name' => 'Exact fixture valve', 'product_id' => $master->id, 'product_sku' => $master->sku, 'match_status' => 'matched', 'price_byn' => 80.12, 'stock_quantity' => 17, 'last_stock_synced_at' => now(), 'raw' => ['category' => 'Краны', 'maker_code' => 'MAKER-EXACT', 'retail' => 999]]);

        return [$old, $master, $offer, ['duplicate' => $old->id, 'canonical' => $master->id, 'sku' => $old->sku, 'name' => $old->name, 'maker' => 'MAKER-EXACT']];
    }

    public function test_merge_preserves_commercial_snapshots_references_and_old_urls(): void
    {
        Storage::fake('local');
        [$old,$master,$offer,$pair] = $this->fixture();
        $user = User::factory()->create();
        $line = ['id' => $old->id, 'name' => $old->name, 'sku' => $old->sku, 'price' => 111.11, 'quantity' => 7, 'client_pricing' => ['mode' => 'manual'], 'supplier_product_id' => $offer->id, 'selection_note' => 'Agreed conditions'];
        $cart = CustomerCart::create(['user_id' => $user->id, 'items' => [$old->id => $line], 'meta' => ['cart_excluded_ids' => [$old->id]]]);
        $selection = CustomerSelection::create(['user_id' => $user->id, 'name' => 'Saved request', 'items' => [$old->id => $line], 'meta' => ['desk_excluded_ids' => [$old->id]], 'revision' => 4]);
        $saved = B2bSavedCart::create(['user_id' => $user->id, 'name' => 'Legacy', 'items' => [$line], 'items_count' => 1, 'total' => 777.77]);
        $order = DB::table('orders')->insertGetId(['number' => 'MERGE-TEST', 'customer_name' => 'Fixture', 'customer_phone' => '123', 'total' => 777.77]);
        $orderItem = DB::table('order_items')->insertGetId(['order_id' => $order, 'product_id' => $old->id, 'product_name' => $old->name, 'product_sku' => $old->sku, 'price' => 111.11, 'quantity' => 7, 'total' => 777.77]);
        $warehouse = DB::table('product_warehouse_stocks')->insertGetId(['product_id' => $old->id, 'supplier_id' => $offer->supplier_id, 'warehouse_code' => 'TEST', 'warehouse_name' => 'Test stock', 'available_quantity' => 17, 'total_quantity' => 20, 'reserved_quantity' => 3]);
        $offerBefore = $offer->fresh()->getAttributes();
        $priceBefore = $master->fresh()->getAttributes();
        $orderBefore = (array) DB::table('order_items')->find($orderItem);
        $stockBefore = (array) DB::table('product_warehouse_stocks')->find($warehouse);
        $service = app(MergeConfirmedBeregProducts::class);
        $this->assertCount(1, $service->run([$pair]));
        $this->assertSame([], $service->run([$pair]));
        $this->assertSame($offerBefore, $offer->fresh()->getAttributes());
        foreach (['price', 'price_old', 'manual_purchase_price_byn', 'stock_qty', 'in_stock'] as $field) {
            $this->assertSame($priceBefore[$field], $master->fresh()->getAttributes()[$field]);
        }
        $orderBefore['product_id'] = $master->id;
        $this->assertEquals($orderBefore, (array) DB::table('order_items')->find($orderItem));
        $stockBefore['product_id'] = $master->id;
        $this->assertEquals($stockBefore, (array) DB::table('product_warehouse_stocks')->find($warehouse));
        $line['id'] = $master->id;
        $this->assertEquals([$master->id => $line], $cart->fresh()->items);
        $this->assertEquals([$master->id => $line], $selection->fresh()->items);
        $this->assertEquals([$line], $saved->fresh()->items);
        $this->assertSame(5, $selection->fresh()->revision);
        $this->assertEquals([$master->id], $selection->fresh()->meta['desk_excluded_ids']);
        $this->assertTrue($old->fresh()->is_archived);
        $this->assertFalse($old->fresh()->is_active);
        $this->assertCount(1, Storage::disk('local')->files('private/product-merges'));
        $this->get('/merge-fixture/merge-old')->assertStatus(301)->assertRedirect('/merge-fixture/merge-master');
        $this->get('/another-category/merge-old')->assertStatus(301)->assertRedirect('/merge-fixture/merge-master');
    }

    public function test_changed_identity_aborts_all_pairs_without_partial_updates(): void
    {
        Storage::fake('local');
        [$old,$master,$offer,$pair] = $this->fixture();
        $bad = $pair;
        $bad['maker'] = 'WRONG';
        try {
            app(MergeConfirmedBeregProducts::class)->run([$bad]);
            $this->fail('Must refuse changed evidence');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Идентификация', $e->getMessage());
        }
        $this->assertNull($old->fresh()->merged_into_product_id);
        $this->assertTrue($old->fresh()->is_active);
    }

    public function test_stale_import_snapshot_and_orphan_sku_reuse_existing_card_without_repricing(): void
    {
        [$old,$master,$offer] = $this->fixture();
        // Test uses one legitimate SKU; old fixture is a different article here.
        $old->update(['sku' => 'ANOTHER']);
        $snapshot = SupplierProduct::findOrFail($offer->id);
        $snapshot->product_id = null;
        $method = new \ReflectionMethod(CreateChistyBeregCardsCommand::class, 'createCard');
        $command = app(CreateChistyBeregCardsCommand::class);
        $count = Product::count();
        $this->assertSame($master->id, $method->invoke($command, $snapshot, $master->category_id, null)->id);
        $offer->update(['product_id' => null, 'match_status' => 'unmatched']);
        $this->assertSame($master->id, $method->invoke($command, $snapshot, $master->category_id, null)->id);
        $this->assertSame($count, Product::count());
        $this->assertEquals(123.45, $master->fresh()->price);
        $this->assertSame($master->id, $offer->fresh()->product_id);
    }

    public function test_import_refuses_ambiguous_sku_and_rolls_back_failed_binding(): void
    {
        [$old,$master,$offer] = $this->fixture();
        $offer->update(['product_id' => null, 'match_status' => 'unmatched']);
        $method = new \ReflectionMethod(CreateChistyBeregCardsCommand::class, 'createCard');
        $command = app(CreateChistyBeregCardsCommand::class);
        try {
            $method->invoke($command, $offer, $master->category_id, null);
            $this->fail('Ambiguous SKU');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Несколько карточек', $e->getMessage());
        }
        $this->assertNull($offer->fresh()->product_id);
        $old->update(['sku' => 'OTHER-OLD']);
        $master->update(['sku' => 'OTHER-MASTER']);
        $fail = true;
        Event::listen('eloquent.saving: '.SupplierProduct::class, function ($row) use (&$fail, $offer) {
            if ($fail && $row->id === $offer->id && $row->product_id) {
                throw new \RuntimeException('Binding failure');
            }
        });
        try {
            $method->invoke($command, $offer, $master->category_id, null);
            $this->fail('Binding must fail');
        } catch (\RuntimeException $e) {
            $this->assertSame('Binding failure', $e->getMessage());
        } finally {
            $fail = false;
        }
        $this->assertDatabaseMissing('products', ['sku' => 'BEREG-TEST-EXACT']);
        $this->assertNull($offer->fresh()->product_id);
        $created = $method->invoke($command, $offer, $master->category_id, null);
        $this->assertSame($created->id, $offer->fresh()->product_id);
        $this->assertSame($created->id, $method->invoke($command, $offer, $master->category_id, null)->id);
    }

    public function test_old_guest_session_keeps_quantity_price_supplier_and_exclusion(): void
    {
        [$old,$master,$offer,$pair] = $this->fixture();
        Storage::fake('local');
        app(MergeConfirmedBeregProducts::class)->run([$pair]);
        $line = ['id' => $old->id, 'price' => 111.11, 'quantity' => 150, 'supplier_product_id' => $offer->id];
        $request = Request::create('/cart', 'GET');
        $request->setLaravelSession(app('session')->driver());
        $request->session()->put(['cart' => [$old->id => $line], 'cart_excluded_ids' => [$old->id]]);
        (new ResolveMergedCartProducts)->handle($request, fn () => response('ok'));
        $line['id'] = $master->id;
        $this->assertSame([$master->id => $line], $request->session()->get('cart'));
        $this->assertSame([$master->id], $request->session()->get('cart_excluded_ids'));
    }

    public function test_conflicting_agreed_prices_are_not_silently_combined_or_lost(): void
    {
        $identity = app(MergedProductIdentity::class);
        $items = [11 => ['id' => 11, 'price' => 10, 'quantity' => 3], 12 => ['id' => 12, 'price' => 20, 'quantity' => 4]];
        try {
            $identity->items($items, [11 => 12]);
            $this->fail('Different conditions must remain intact');
        } catch (ConflictHttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->assertSame(10, $items[11]['price']);
        $this->assertSame(20, $items[12]['price']);
        $items[12]['price'] = 10;
        $this->assertSame([12 => ['id' => 12, 'price' => 10, 'quantity' => 7]], $identity->items($items, [11 => 12]));
    }

    public function test_sync_cannot_erase_a_binding_made_after_classification(): void
    {
        [$old, $master, $offer] = $this->fixture();
        $row = ['code' => $offer->supplier_article, 'product_id' => null, 'name' => $offer->supplier_name,
            'wholesale' => 80.12, 'free' => 17, 'unit' => 'шт', 'suggested_product_id' => null,
            'match_reason' => null, 'maker_code' => 'MAKER-EXACT', 'brand' => '', 'category' => 'Краны',
            'pack' => 1, 'stock' => 20, 'reserve' => 3, 'rrc' => 123.45];
        $method = new \ReflectionMethod(SyncChistyBeregCommand::class, 'apply');
        $stats = $method->invoke(app(SyncChistyBeregCommand::class), [$row], $offer->supplier);
        $this->assertSame(0, $stats['errors']);
        $this->assertSame($master->id, $offer->fresh()->product_id);
        $this->assertSame('matched', $offer->fresh()->match_status);
        $this->assertSame(17, $offer->fresh()->stock_quantity);
    }

    public function test_failed_later_pair_rolls_back_earlier_merge_and_references(): void
    {
        Storage::fake('local');
        [$old, $master, $offer, $pair] = $this->fixture();
        $other = Product::create(['name' => 'Another', 'slug' => 'another-merge-test', 'sku' => 'OTHER', 'category_id' => $old->category_id]);
        $bad = $pair;
        $bad['duplicate'] = $other->id;
        try {
            app(MergeConfirmedBeregProducts::class)->run([$pair, $bad]);
            $this->fail('Second pair must fail');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Идентификация', $e->getMessage());
        }
        $this->assertNull($old->fresh()->merged_into_product_id);
        $this->assertTrue($old->fresh()->is_active);
        $this->assertDatabaseMissing('redirects', ['from_url' => '/merge-fixture/merge-old']);
        $this->assertSame($master->id, $offer->fresh()->product_id);
    }
}
