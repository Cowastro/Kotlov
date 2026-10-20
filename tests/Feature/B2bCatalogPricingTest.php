<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Brand;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\IntegrationWarehouse;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductWarehouseStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class B2bCatalogPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_wholesale_user_sees_wholesale_price_with_tax_retail_difference_and_stock(): void
    {
        [$product, $user] = $this->catalogFixture(approved: true);

        $this->actingAs($user)
            ->get('/'.$product->category->slug.'/'.$product->slug)
            ->assertOk()
            ->assertSeeText('Партнёрская цена от ООО «СанБизнесГруп»')
            ->assertSeeText('96.00 BYN')
            ->assertDontSeeText('Цена с НДС')
            ->assertSeeText('Розничная цена: 120.00 BYN')
            ->assertSeeText('Ваша скидка к рознице: 24.00 BYN (20.0%)')
            ->assertSeeText('Основной: 7 шт.')
            ->assertSee('В корзину', false)
            ->assertSeeText('— 96.00 BYN');
    }

    public function test_unapproved_wholesale_user_does_not_see_wholesale_price(): void
    {
        [$product, $user] = $this->catalogFixture(approved: false);

        $this->actingAs($user)
            ->get('/'.$product->category->slug.'/'.$product->slug)
            ->assertOk()
            ->assertDontSeeText('Ваша оптовая цена')
            ->assertDontSeeText('96.00 BYN')
            ->assertSeeText('120.00 BYN');
    }

    public function test_individually_blocked_offer_is_hidden_from_b2b_and_rejected_by_cart(): void
    {
        [$product, $user] = $this->catalogFixture(approved: true);
        IntegrationProduct::query()->where('product_id', $product->id)->update([
            'b2b_enabled' => false,
        ]);

        $this->actingAs($user)
            ->get('/'.$product->category->slug.'/'.$product->slug)
            ->assertOk()
            ->assertDontSeeText('Партнёрская цена от ООО «СанБизнесГруп»')
            ->assertSeeText('120.00 BYN');

        $this->actingAs($user)
            ->postJson('/cart/add', ['product_id' => $product->id, 'quantity' => 1])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Товар недоступен для B2B-заказа: требуется подтверждённая привязка, цена и свежий остаток 1С.');
    }

    public function test_purchase_or_unclassified_source_price_is_never_published_to_partner(): void
    {
        [$product, $user] = $this->catalogFixture(approved: true);
        $source = IntegrationSource::query()->where('code', 'onec')->firstOrFail();

        foreach ([IntegrationSource::B2B_PRICE_ROLE_PURCHASE, IntegrationSource::B2B_PRICE_ROLE_UNKNOWN] as $role) {
            $settings = $source->settings;
            $settings['b2b_price_role'] = $role;
            $source->update(['settings' => $settings]);

            $this->actingAs($user)
                ->get('/'.$product->category->slug.'/'.$product->slug)
                ->assertOk()
                ->assertDontSeeText('Партнёрская цена от ООО «СанБизнесГруп»')
                ->assertSeeText('120.00 BYN');

            $this->actingAs($user)
                ->postJson('/cart/add', ['product_id' => $product->id, 'quantity' => 1])
                ->assertStatus(422);
        }
    }

    public function test_explicitly_allowed_offer_still_requires_source_category_and_stock_rules(): void
    {
        [$product, $user] = $this->catalogFixture(approved: true);
        IntegrationProduct::query()->where('product_id', $product->id)->update([
            'b2b_enabled' => true,
            'stock_quantity' => 0,
            'stock_confirmed_at' => null,
        ]);

        $this->actingAs($user)
            ->get('/'.$product->category->slug.'/'.$product->slug)
            ->assertOk()
            ->assertDontSeeText('Партнёрская цена от ООО «СанБизнесГруп»');
    }

    public function test_approved_wholesale_user_can_order_a_procurement_offer_without_fake_stock(): void
    {
        [$product, $user] = $this->catalogFixture(approved: true);
        $source = IntegrationSource::query()->where('code', 'onec')->firstOrFail();
        $settings = $source->settings;
        $settings['procurement_mode'] = true;
        $settings['warehouse_label'] = 'Закупка БелОМО';
        $source->update(['settings' => $settings]);
        IntegrationProduct::query()->where('product_id', $product->id)->update([
            'price' => 153.65,
            'price_currency' => 'BYN',
            'price_currency_rate' => 1,
            'price_tax_mode' => 'exclusive',
            'price_vat_rate' => 20,
            'price_byn' => 184.38,
            'stock_quantity' => 0,
            'stock_confirmed_at' => null,
        ]);

        $this->actingAs($user)
            ->get('/'.$product->category->slug.'/'.$product->slug)
            ->assertOk()
            ->assertSeeText('184.38 BYN')
            ->assertSeeText('Закупка БелОМО')
            ->assertDontSeeText('Закупка БелОМО: 0 шт.');

        $this->actingAs($user)
            ->postJson('/cart/add', ['product_id' => $product->id, 'quantity' => 2])
            ->assertOk()
            ->assertJsonPath('subtotal', 368.76);
    }

    public function test_partner_can_choose_moscow_stock_when_minsk_offer_is_empty(): void
    {
        [$product, $user] = $this->catalogFixture(approved: true);
        $brand = Brand::query()->create(['name' => 'ЭкоКамин', 'slug' => 'ecokamin']);
        $product->update(['brand_id' => $brand->id]);
        $offer = IntegrationProduct::query()->where('product_id', $product->id)->firstOrFail();
        $offer->update(['stock_quantity' => 0, 'stock_confirmed_at' => null]);
        $moscow = ProductWarehouseStock::query()->create([
            'product_id' => $product->id,
            'supplier_id' => $offer->source->supplier_id,
            'warehouse_code' => 'ecokamin-moscow',
            'warehouse_name' => 'Склад Москва',
            'external_article' => $product->sku,
            'available_quantity' => 4,
            'delivery_days' => 7,
            'as_of_date' => '2026-10-20',
            'last_synced_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/'.$product->category->slug.'/'.$product->slug)
            ->assertOk()
            ->assertSeeText('Склад Москва')
            ->assertSeeText('доставка до 7 дней');

        $this->actingAs($user)
            ->postJson('/cart/add', [
                'product_id' => $product->id,
                'quantity' => 2,
                'warehouse_stock_id' => $moscow->id,
            ])
            ->assertOk();

        $this->assertSame($moscow->id, session('cart')[$product->id]['warehouse_stock_id']);
        $this->assertSame('Склад Москва', session('cart')[$product->id]['warehouse_name']);
        $this->assertSame(7, session('cart')[$product->id]['warehouse_delivery_days']);
    }

    public function test_public_product_page_shows_fresh_channel_quantity_instead_of_stale_summary(): void
    {
        [$product, $user] = $this->catalogFixture(approved: false);
        $product->update(['stock_qty' => 9]);

        $this->actingAs($user)
            ->get('/'.$product->category->slug.'/'.$product->slug)
            ->assertOk()
            ->assertSeeText('В наличии: 7 шт.')
            ->assertDontSeeText('В наличии: 9 шт.');
    }

    public function test_cart_uses_wholesale_price_for_approved_partner(): void
    {
        [$product, $user] = $this->catalogFixture(approved: true);

        $this->actingAs($user)
            ->postJson('/cart/add', ['product_id' => $product->id, 'quantity' => 2])
            ->assertOk()
            ->assertJsonPath('subtotal', 192);

        $item = session('cart')[$product->id];
        $this->assertSame(96.0, $item['price']);
        $this->assertSame('b2b', $item['pricing_type']);
        $this->assertSame('inclusive', $item['price_tax_mode']);
        $this->assertNotNull($item['integration_product_id']);
    }

    public function test_stale_one_c_stock_is_hidden_and_rejected_for_b2b_ordering(): void
    {
        [$product, $user] = $this->catalogFixture(approved: true);
        IntegrationProduct::query()->where('product_id', $product->id)->update([
            'stock_confirmed_at' => now()->subMinutes(IntegrationSource::ONE_C_STOCK_MIN_VALIDITY_MINUTES + 1),
        ]);

        $this->actingAs($user)
            ->get('/'.$product->category->slug.'/'.$product->slug)
            ->assertOk()
            ->assertDontSeeText('Партнёрская цена от ООО «СанБизнесГруп»');

        $this->actingAs($user)
            ->postJson('/cart/add', ['product_id' => $product->id, 'quantity' => 1])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Товар недоступен для B2B-заказа: требуется подтверждённая привязка, цена и свежий остаток 1С.');
    }

    public function test_b2b_cart_rejects_quantity_above_confirmed_one_c_stock(): void
    {
        [$product, $user] = $this->catalogFixture(approved: true);

        $this->actingAs($user)
            ->postJson('/cart/add', ['product_id' => $product->id, 'quantity' => 8])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Запрошенное количество превышает доступный остаток поставщика.');
    }

    public function test_checkout_revalidates_b2b_stock_after_item_was_added_to_cart(): void
    {
        [$product, $user] = $this->catalogFixture(approved: true);

        $this->actingAs($user)
            ->postJson('/cart/add', ['product_id' => $product->id, 'quantity' => 2])
            ->assertOk();

        IntegrationProduct::query()->where('product_id', $product->id)->update([
            'stock_confirmed_at' => now()->subMinutes(IntegrationSource::ONE_C_STOCK_MIN_VALIDITY_MINUTES + 1),
        ]);
        $this->app->forgetScopedInstances();

        $this->withHeader('User-Agent', 'Mozilla/5.0')
            ->post(route('checkout.store'), [
                'order_token' => 'stale-b2b-stock',
                'customer_name' => 'СБГ.BY',
                'customer_phone' => '+375291112233',
                'delivery_type' => 'pickup',
                'payment_type' => 'cash',
            ])
            ->assertRedirect(route('cart'))
            ->assertSessionHas('error', 'B2B-заказ не создан: одна из позиций потеряла привязку или больше недоступна в указанном количестве.');

        $this->assertDatabaseCount((new Order)->getTable(), 0);
    }

    public function test_checkout_stops_and_refreshes_a_changed_b2b_price(): void
    {
        [$product, $user] = $this->catalogFixture(approved: true);

        $this->actingAs($user)
            ->postJson('/cart/add', ['product_id' => $product->id, 'quantity' => 1])
            ->assertOk();

        IntegrationProduct::query()->where('product_id', $product->id)->update([
            'price' => 100,
            'price_byn' => 100,
        ]);
        $this->app->forgetScopedInstances();

        $this->withHeader('User-Agent', 'Mozilla/5.0')
            ->post(route('checkout.store'), [
                'order_token' => 'changed-b2b-price',
                'customer_name' => 'СБГ.BY',
                'customer_phone' => '+375291112233',
                'delivery_type' => 'pickup',
                'payment_type' => 'cash',
            ])
            ->assertRedirect(route('cart'))
            ->assertSessionHas(
                'error',
                'B2B-заказ не создан: цены 1С изменились и обновлены в корзине. Проверьте сумму повторно.'
            );

        $this->assertEquals(100.0, session('cart')[$product->id]['price']);
        $this->assertDatabaseCount((new Order)->getTable(), 0);
    }

    public function test_admin_can_preview_partner_card_without_changing_their_account(): void
    {
        [$product] = $this->catalogFixture(approved: false);
        $admin = User::factory()->create([
            'role' => 'admin',
            'client_type' => 'retail',
            'b2b_approved' => false,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get('/'.$product->category->slug.'/'.$product->slug.'?b2b-preview=1')
            ->assertOk()
            ->assertSeeText('Предпросмотр карточки для одобренного B2B-партнёра')
            ->assertSeeText('96.00 BYN');

        $this->actingAs($admin)
            ->get('/account?b2b-preview=1')
            ->assertOk()
            ->assertSeeText('Предпросмотр кабинета одобренного B2B-партнёра')
            ->assertSeeText('Группы товаров с партнёрскими ценами')
            ->assertSeeText('Дымоходы');
    }

    public function test_partner_account_groups_available_products_from_sanbusinessgroup(): void
    {
        [, $user] = $this->catalogFixture(approved: true);

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertSeeText('Партнёрские цены активны')
            ->assertSeeText('Поставщик: ООО «СанБизнесГруп»')
            ->assertSeeText('Группы товаров с партнёрскими ценами')
            ->assertSeeText('Дымоходы')
            ->assertSeeText('1 позиций в наличии');
    }

    public function test_imported_price_snapshot_does_not_change_when_source_rule_changes(): void
    {
        [$product, $user] = $this->catalogFixture(approved: true);
        $offer = IntegrationProduct::query()->where('product_id', $product->id)->firstOrFail();
        $offer->update([
            'price_currency' => 'BYN',
            'price_currency_rate' => 1,
            'price_tax_mode' => 'exclusive',
            'price_vat_rate' => 20,
            'price_byn' => 96,
        ]);

        $source = $offer->source;
        $settings = $source->settings;
        $settings['price_tax_mode'] = 'inclusive';
        $settings['vat_rate'] = 0;
        $source->update([
            'price_currency' => 'EUR',
            'price_currency_rate' => 4,
            'settings' => $settings,
        ]);

        $this->actingAs($user)
            ->get('/'.$product->category->slug.'/'.$product->slug)
            ->assertOk()
            ->assertSeeText('96.00 BYN')
            ->assertSeeText('Ваша скидка к рознице: 24.00 BYN (20.0%)');

        $this->assertSame(96.0, $offer->fresh()->normalizedPriceByn());
    }

    public function test_foreign_offer_without_rate_is_not_exposed_as_zero_partner_price(): void
    {
        [$product, $user] = $this->catalogFixture(approved: true);
        IntegrationProduct::query()->where('product_id', $product->id)->update([
            'price_currency' => 'EUR',
            'price_currency_rate' => null,
            'price_tax_mode' => 'inclusive',
            'price_vat_rate' => 20,
            'price_byn' => null,
        ]);

        $this->actingAs($user)
            ->get('/'.$product->category->slug.'/'.$product->slug)
            ->assertOk()
            ->assertDontSeeText('Партнёрская цена от ООО «СанБизнесГруп»')
            ->assertSeeText('120.00 BYN');

        $this->assertNull(
            IntegrationProduct::query()->where('product_id', $product->id)->firstOrFail()->normalizedPriceByn(),
        );
    }

    public function test_partner_catalog_is_deny_by_default_without_allowed_categories(): void
    {
        [$product, $user] = $this->catalogFixture(approved: true);
        $source = IntegrationProduct::query()->where('product_id', $product->id)->firstOrFail()->source;
        IntegrationWarehouse::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'main-warehouse-id',
            'name' => 'Основной',
            'last_seen_at' => now(),
        ]);
        $settings = $source->settings;
        $settings['b2b_category_ids'] = [];
        $source->updateQuietly(['settings' => $settings]);

        $this->actingAs($user)
            ->get('/'.$product->category->slug.'/'.$product->slug)
            ->assertOk()
            ->assertDontSeeText('Партнёрская цена от ООО «СанБизнесГруп»')
            ->assertSeeText('120.00 BYN');

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertDontSeeText('Дымоходы');
    }

    public function test_allowed_parent_category_includes_children_but_excludes_other_branches(): void
    {
        [$product, $user] = $this->catalogFixture(approved: true);
        $source = IntegrationProduct::query()->where('product_id', $product->id)->firstOrFail()->source;
        $root = Category::query()->create([
            'name' => 'Разрешённая ветка',
            'slug' => 'allowed-b2b-root',
            'is_active' => true,
        ]);
        $product->category->update(['parent_id' => $root->id]);
        $settings = $source->settings;
        $settings['b2b_category_ids'] = [$root->id];
        $source->updateQuietly(['settings' => $settings]);

        $blockedCategory = Category::query()->create([
            'name' => 'Котлы вне пилота',
            'slug' => 'blocked-b2b-category',
            'is_active' => true,
        ]);
        $blockedProduct = Product::query()->create([
            'category_id' => $blockedCategory->id,
            'name' => 'Товар вне разрешённой ветки',
            'slug' => 'blocked-b2b-product',
            'sku' => 'BLOCKED-B2B',
            'price' => 200,
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => true,
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $blockedProduct->id,
            'external_id' => 'onec-blocked-b2b',
            'name' => 'Товар вне разрешённой ветки',
            'price' => 100,
            'stock_quantity' => 5,
            'stock_confirmed_at' => now(),
            'match_status' => 'matched',
        ]);

        $this->actingAs($user)
            ->get('/'.$product->category->slug.'/'.$product->slug)
            ->assertOk()
            ->assertSeeText('Партнёрская цена от ООО «СанБизнесГруп»');

        $this->actingAs($user)
            ->get('/'.$blockedCategory->slug.'/'.$blockedProduct->slug)
            ->assertOk()
            ->assertDontSeeText('Партнёрская цена от ООО «СанБизнесГруп»')
            ->assertSeeText('200.00 BYN');

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertSeeText('Дымоходы')
            ->assertDontSeeText('Котлы вне пилота');
    }

    public function test_source_edit_explains_required_partner_catalog_scope(): void
    {
        [$product] = $this->catalogFixture(approved: false);
        $source = IntegrationProduct::query()->where('product_id', $product->id)->firstOrFail()->source;
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get('/admin/integration-sources/'.$source->id.'/edit')
            ->assertOk()
            ->assertSeeText('Разрешённые категории партнёрского каталога')
            ->assertSeeText('Пустой список ничего не публикует')
            ->assertSeeText('Остатки и склад')
            ->assertSeeText('Склад из CommerceML')
            ->assertSeeText('Склады, найденные в последних обменах')
            ->assertSeeText('При нескольких складах выберите точный ID');
    }

    public function test_pilot_migration_selects_chimney_branch_without_overwriting_manual_scope(): void
    {
        $root = Category::query()->firstOrCreate(
            ['slug' => 'dymohody'],
            ['name' => 'Дымоходы', 'is_active' => true],
        );
        $source = IntegrationSource::query()->where('code', 'onec')->firstOrFail();
        $settings = $source->settings ?? [];
        unset($settings['b2b_category_ids']);
        DB::table('integration_sources')->where('id', $source->id)->update([
            'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE),
        ]);

        $migration = require database_path('migrations/2026_10_10_201000_configure_onec_b2b_category_allowlist.php');
        $migration->up();

        $this->assertSame([$root->id], $source->fresh()->b2bCategoryIds());

        $settings = $source->fresh()->settings;
        $settings['b2b_category_ids'] = [999];
        DB::table('integration_sources')->where('id', $source->id)->update([
            'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE),
        ]);
        $migration->up();

        $this->assertSame([999], $source->fresh()->b2bCategoryIds());
    }

    public function test_price_role_migration_classifies_known_sources_without_overwriting_manual_decision(): void
    {
        $oneC = IntegrationSource::query()->updateOrCreate(
            ['code' => 'onec'],
            ['name' => '1С', 'settings' => ['b2b_enabled' => true]],
        );
        $belomo = IntegrationSource::query()->create([
            'code' => 'belomo_wholesale',
            'name' => 'БелОМО',
            'settings' => ['b2b_enabled' => true, 'b2b_price_role' => IntegrationSource::B2B_PRICE_ROLE_PURCHASE],
        ]);
        $unrelated = IntegrationSource::query()->create([
            'code' => 'other-price-source',
            'name' => 'Другой источник',
            'settings' => ['b2b_enabled' => true],
        ]);

        $migration = require database_path('migrations/2026_10_18_100000_classify_existing_b2b_source_prices.php');
        $migration->up();
        $migration->up();

        $this->assertSame(IntegrationSource::B2B_PRICE_ROLE_SALE, $oneC->fresh()->b2bPriceRole());
        $this->assertSame(IntegrationSource::B2B_PRICE_ROLE_PURCHASE, $belomo->fresh()->b2bPriceRole());
        $this->assertSame(IntegrationSource::B2B_PRICE_ROLE_UNKNOWN, $unrelated->fresh()->b2bPriceRole());
    }

    /** @return array{Product, User} */
    private function catalogFixture(bool $approved): array
    {
        $category = Category::query()->create([
            'name' => 'Дымоходы',
            'slug' => 'dymohody-test',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Труба дымохода D115',
            'slug' => 'truba-d115-test',
            'sku' => 'TEST-D115',
            'price' => 120,
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => true,
        ]);
        $source = IntegrationSource::query()->updateOrCreate(
            ['code' => 'onec'],
            [
                'name' => '1С',
                'is_active' => true,
                'settings' => [
                    'price_tax_mode' => 'exclusive',
                    'vat_rate' => 20,
                    'warehouse_label' => 'Основной',
                    'b2b_enabled' => true,
                    'b2b_price_role' => IntegrationSource::B2B_PRICE_ROLE_SALE,
                    'b2b_category_ids' => [$category->id],
                    'partner_name' => 'ООО «СанБизнесГруп»',
                ],
            ],
        );
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $product->id,
            'external_id' => 'onec-d115',
            'name' => 'Труба дымохода D115',
            'price' => 80,
            'stock_quantity' => 7,
            'stock_confirmed_at' => now(),
            'match_status' => 'matched',
        ]);
        $user = User::factory()->create([
            'client_type' => 'wholesale',
            'b2b_approved' => $approved,
            'is_active' => true,
        ]);

        return [$product->load('category'), $user];
    }
}
