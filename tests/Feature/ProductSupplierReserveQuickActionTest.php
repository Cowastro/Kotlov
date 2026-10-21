<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Category;
use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\CrmMailbox;
use App\Models\CrmMailThread;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductSupplierReserveQuickActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_email_opens_internal_reserve_form_from_quick_actions(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $counterparty = CrmCounterparty::query()->create([
            'name' => 'Тестовый поставщик',
            'classification' => 'supplier',
        ]);
        CrmContactPoint::query()->create([
            'counterparty_id' => $counterparty->id,
            'type' => 'email',
            'value' => 'reserve@supplier.test',
            'is_primary' => true,
            'is_active' => true,
        ]);
        $supplier = Supplier::query()->create([
            'code' => 'reserve-supplier',
            'name' => 'Поставщик резерва',
            'crm_counterparty_id' => $counterparty->id,
            'is_active' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Товары для резерва',
            'slug' => 'reserve-products',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Расширительный бак',
            'slug' => 'reserve-expansion-tank',
            'sku' => 'RESERVE-001',
            'price' => 1185,
            'currency' => 'BYN',
            'is_active' => true,
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_article' => 'SUP-001',
            'price_byn' => 913,
            'stock_quantity' => 1,
        ]);

        $this->actingAs($manager);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListProducts::class)
            ->assertTableActionVisible('request_supplier_reserve', $product)
            ->assertSeeHtml("mountTableAction('request_supplier_reserve', '{$product->id}')")
            ->assertDontSeeHtml('mailto:reserve@supplier.test')
            ->mountTableAction('request_supplier_reserve', $product)
            ->assertTableActionDataSet([
                'supplier_id' => $supplier->id,
                'recipient' => 'reserve@supplier.test',
                'quantity' => 1,
            ]);
    }

    public function test_supplier_reserve_uses_email_channel_when_crm_email_is_missing(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $supplier = Supplier::query()->create([
            'code' => 'reserve-channel-supplier',
            'name' => 'Термостудия',
            'communication_channels' => [[
                'type' => 'email',
                'name' => 'Резервная почта',
                'value' => 'info@teplo.by',
            ]],
            'is_active' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Товары с резервной почтой',
            'slug' => 'reserve-channel-products',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Товар Термостудии',
            'slug' => 'reserve-channel-product',
            'sku' => 'RESERVE-CHANNEL-001',
            'price' => 435,
            'currency' => 'BYN',
            'is_active' => true,
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_article' => 'SUP-CHANNEL-001',
            'price_byn' => 435,
            'stock_quantity' => 1,
        ]);

        $this->actingAs($manager);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListProducts::class)
            ->mountTableAction('request_supplier_reserve', $product)
            ->assertTableActionDataSet([
                'supplier_id' => $supplier->id,
                'recipient' => 'info@teplo.by',
                'quantity' => 1,
            ]);
    }

    public function test_product_table_summarizes_all_reserve_requests_and_links_to_history(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $supplier = Supplier::query()->create([
            'code' => 'multi-reserve-supplier',
            'name' => 'Поставщик нескольких резервов',
            'is_active' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Товары с несколькими резервами',
            'slug' => 'multi-reserve-products',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Бак с историей резервов',
            'slug' => 'tank-with-reserve-history',
            'sku' => 'RESERVE-MULTI-001',
            'price' => 1185,
            'currency' => 'BYN',
            'is_active' => true,
        ]);
        $mailbox = CrmMailbox::query()->create([
            'name' => 'Заказы KOTLOV',
            'email' => 'order@kotlov.by',
            'username' => 'order@kotlov.by',
            'is_enabled' => true,
        ]);

        foreach (['waiting_customer', 'waiting_manager', 'closed'] as $index => $status) {
            CrmMailThread::query()->create([
                'mailbox_id' => $mailbox->id,
                'assigned_user_id' => $manager->id,
                'product_id' => $product->id,
                'supplier_id' => $supplier->id,
                'thread_key' => hash('sha256', 'reserve-history-'.$index),
                'subject' => 'Запрос резерва '.($index + 1),
                'participant_name' => $supplier->name,
                'participant_email' => 'reserve-'.$index.'@supplier.test',
                'status' => $status,
                'last_message_at' => now()->subMinutes(3 - $index),
            ]);
        }

        $this->actingAs($manager);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListProducts::class)
            ->assertSee('Ответ: 1')
            ->assertSee('ждём: 1')
            ->assertSee('Поставщик нескольких резервов')
            ->assertSee('Запросов: 3');
    }

    public function test_reserve_request_tabs_filter_products_by_response_state(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $category = Category::query()->create([
            'name' => 'Фильтр резервов',
            'slug' => 'reserve-filter-products',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $mailbox = CrmMailbox::query()->updateOrCreate(['email' => 'order@kotlov.by'], [
            'name' => 'Заказы KOTLOV',
            'username' => 'order@kotlov.by',
            'is_enabled' => true,
        ]);
        $waitingProduct = $this->product($category, 'Товар — ждём резерв', 'reserve-waiting');
        $answeredProduct = $this->product($category, 'Товар — резерв подтверждён', 'reserve-answered');
        $withoutRequest = $this->product($category, 'Товар без запроса', 'reserve-none');

        foreach ([[$waitingProduct, 'waiting_customer'], [$answeredProduct, 'waiting_manager']] as [$product, $status]) {
            CrmMailThread::query()->create([
                'mailbox_id' => $mailbox->id,
                'product_id' => $product->id,
                'thread_key' => hash('sha256', $product->slug),
                'subject' => 'Запрос резерва '.$product->name,
                'participant_email' => 'supplier@example.com',
                'status' => $status,
                'last_message_at' => now(),
            ]);
        }

        $this->actingAs($manager);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $page = Livewire::test(ListProducts::class)
            ->assertSet('activeTab', 'all')
            ->set('activeTab', 'reserve_requests')
            ->assertCanSeeTableRecords([$waitingProduct, $answeredProduct])
            ->assertCanNotSeeTableRecords([$withoutRequest])
            ->set('activeTab', 'reserve_waiting')
            ->assertCanSeeTableRecords([$waitingProduct])
            ->assertCanNotSeeTableRecords([$answeredProduct, $withoutRequest])
            ->set('activeTab', 'reserve_answered')
            ->assertCanSeeTableRecords([$answeredProduct])
            ->assertCanNotSeeTableRecords([$waitingProduct, $withoutRequest]);

        $this->assertSame('2', $page->instance()->getTabs()['reserve_requests']->getBadge());
        $this->assertSame('1', $page->instance()->getTabs()['reserve_waiting']->getBadge());
        $this->assertSame('1', $page->instance()->getTabs()['reserve_answered']->getBadge());
    }

    private function product(Category $category, string $name, string $slug): Product
    {
        return Product::query()->create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug,
            'sku' => mb_strtoupper($slug),
            'price' => 100,
            'currency' => 'BYN',
            'is_active' => true,
        ]);
    }
}
