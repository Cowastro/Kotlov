<?php

namespace Tests\Feature;

use App\Models\{B2bClient, B2bPricingRule, Category, CustomerCart, CustomerSelection, Product, Supplier, SupplierProduct, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LargeB2bCartTest extends TestCase
{
    use RefreshDatabase;

    public function test_150_b2b_lines_survive_edits_login_saved_revision_restore_and_print(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        B2bClient::create(['user_id' => $user->id, 'status' => 'approved', 'partner_type' => 'wholesale',
            'company_name' => 'Большой подбор', 'allow_all_partner_suppliers' => true]);
        $category = Category::create(['name' => 'Фитинги', 'slug' => 'large-cart-test', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Тестовый склад', 'code' => 'large-cart-test', 'is_active' => true,
            'b2b_partner_enabled' => true, 'b2b_require_positive_stock' => true, 'b2b_stock_max_age_hours' => 24]);
        $supplier->b2bPricingRules()->create(['name' => 'Партнёрская цена', 'scope_type' => B2bPricingRule::SCOPE_SUPPLIER,
            'calculation_type' => B2bPricingRule::TYPE_TARGET_MARGIN, 'value' => 20, 'minimum_profit_byn' => 0,
            'minimum_margin_percent' => 10, 'rounding_step_byn' => 1, 'minimum_retail_discount_percent' => 0,
            'priority' => 100, 'status' => B2bPricingRule::STATUS_ACTIVE]);
        $items = Product::withoutEvents(function () use ($category, $supplier) {
            $items = [];
            for ($i = 1; $i <= 150; $i++) {
                $sku = 'KOTLOV-'.str_pad($i, 6, '0', STR_PAD_LEFT);
                $product = Product::create(['category_id' => $category->id, 'sku' => $sku, 'slug' => 'large-'.$i,
                    'name' => 'Тестовый фитинг '.$i, 'price' => 200, 'is_active' => true, 'is_archived' => false]);
                $offer = SupplierProduct::create(['supplier_id' => $supplier->id, 'product_id' => $product->id,
                    'supplier_article' => 'TD-'.$i, 'price_byn' => 100, 'in_stock' => true, 'stock_quantity' => 500,
                    'stock_status' => 'in_stock', 'match_status' => 'matched', 'last_stock_synced_at' => now(), 'last_synced_at' => now()]);
                $items[$product->id] = ['id' => $product->id, 'name' => $product->name, 'slug' => $product->slug,
                    'sku' => $sku, 'category_slug' => $category->slug, 'quantity' => 2, 'price' => 125,
                    'pricing_type' => 'b2b', 'supplier_product_id' => $offer->id, 'supply_option' => 'supplier:'.$offer->id];
            }
            return $items;
        });
        CustomerCart::create(['user_id' => $user->id, 'items' => $items]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $response = $this->get('/cart')->assertOk()->assertSee('cart-page--b2b', false);
        $this->assertSame(150, $this->countNodes($response->getContent(), '//article[@data-cart-row]'));
        $ids = array_keys($items);
        $loadedOffers = [];
        SupplierProduct::retrieved(function (SupplierProduct $offer) use (&$loadedOffers) { $loadedOffers[] = $offer->product_id; });
        foreach ([0 => 3, 74 => 17, 149 => 9] as $index => $quantity) {
            $this->postJson('/cart/update', ['product_id' => $ids[$index], 'quantity' => $quantity])
                ->assertOk()->assertJsonPath('item_quantity', $quantity)->assertJsonPath('selection.positions', 150);
        }
        $this->assertCount(3, $loadedOffers, 'Each quantity edit loads only the offer for that product, not all 150 offers.');
        $this->assertSame([$ids[0], $ids[74], $ids[149]], $loadedOffers);
        $this->postJson('/cart/line-note', ['product_id' => $ids[149], 'note' => 'Последняя позиция · этаж 2'])->assertOk();
        $this->post('/account/selections', ['name' => 'Объект на 150 позиций'])->assertRedirect();
        $selection = CustomerSelection::where('user_id', $user->id)->sole();
        $this->assertCount(150, $selection->items);
        $this->assertSame(40375.0, $selection->total());
        $this->post('/logout')->assertRedirect(); Auth::forgetGuards(); session()->flush();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $this->getJson('/cart/data')->assertOk()->assertJsonCount(150, 'items')->assertJsonPath('selection.total', 40375);
        $this->assertSame(17, session('cart')[$ids[74]]['quantity']);
        $this->assertSame('supplier:'.$items[$ids[149]]['supplier_product_id'], session('cart')[$ids[149]]['supply_option']);
        $this->assertSame('Последняя позиция · этаж 2', session('cart')[$ids[149]]['selection_note']);
        $this->postJson('/cart/update', ['product_id' => $ids[149], 'quantity' => 11])->assertOk();
        $this->post('/account/selections', ['name' => $selection->name, 'selection_id' => $selection->id, 'revision' => 1])->assertRedirect();
        $this->post('/account/selections', ['name' => 'Старая вкладка', 'selection_id' => $selection->id, 'revision' => 1])->assertSessionHas('error');
        $this->assertCount(150, $selection->fresh()->items);
        $this->assertSame(11, $selection->fresh()->items[$ids[149]]['quantity']);
        $this->postJson('/cart/clear')->assertOk();
        $this->post('/account/selections/'.$selection->id.'/restore')->assertRedirect('/cart');
        $this->getJson('/cart/data')->assertOk()->assertJsonCount(150, 'items');
        foreach (['/cart/print', '/account/selections/'.$selection->id.'?print=1'] as $url) {
            $print = $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
                ->assertSee('KOTLOV-000001')->assertSee('KOTLOV-000150')->assertSee('40 625,00')
                ->assertSee('Последняя позиция · этаж 2');
            $this->assertSame(150, $this->countNodes($print->getContent(), '//tbody/tr'));
        }
        $this->assertSame(11, CustomerCart::where('user_id', $user->id)->sole()->items[$ids[149]]['quantity']);
    }

    private function countNodes(string $html, string $expression): int
    {
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        return (new \DOMXPath($document))->query($expression)->length;
    }
}
