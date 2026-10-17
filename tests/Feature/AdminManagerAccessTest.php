<?php

namespace Tests\Feature;

use App\Filament\Pages\AiAssistant;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\OneCSetup;
use App\Filament\Resources\ContactRequests\ContactRequestResource;
use App\Filament\Resources\IntegrationSources\IntegrationSourceResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\PublicAiSelectionRuns\PublicAiSelectionRunResource;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Filament\Resources\SupplierSyncRuns\SupplierSyncRunResource;
use App\Filament\Resources\SupportConversations\SupportConversationResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\PublicAiSelectionRun;
use App\Models\Supplier;
use App\Models\SupplierSync;
use App\Models\SupplierSyncRun;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminManagerAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_manager_can_enter_admin_panel_but_only_operational_resources(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
            'is_active' => true,
        ]);

        $this->actingAs($manager);

        $this->assertTrue($manager->canAccessPanel(Filament::getPanel('admin')));
        $this->assertTrue(OrderResource::canViewAny());
        $this->assertTrue(ContactRequestResource::canViewAny());
        $this->assertTrue(SupplierResource::canViewAny());
        $this->assertTrue(SupplierSyncRunResource::canViewAny());
        $this->assertFalse(OrderResource::canCreate());
        $this->assertTrue(OrderResource::canEdit(new Order));
        $this->assertFalse(OrderResource::canDelete(new Order));

        $this->assertFalse(UserResource::canViewAny());
        $this->assertTrue(ProductResource::canViewAny());
        $this->assertTrue(PublicAiSelectionRunResource::canViewAny());
        $this->assertTrue(SupportConversationResource::canViewAny());
        $this->assertTrue(ProductResource::canView(new Product));
        $this->assertFalse(ProductResource::canCreate());
        $this->assertFalse(ProductResource::canEdit(new Product));
        $this->assertFalse(ProductResource::canDelete(new Product));
        $this->assertFalse(IntegrationSourceResource::canViewAny());
        $this->assertFalse(AiAssistant::canAccess());
        $this->assertFalse(OneCSetup::canAccess());
    }

    public function test_manager_direct_urls_are_server_side_protected(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->get(OrderResource::getUrl('index', panel: 'admin'))
            ->assertOk();

        $category = Category::query()->create([
            'name' => 'Каталог менеджера',
            'slug' => 'manager-catalog',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Товар в режиме просмотра',
            'slug' => 'manager-read-only-product',
            'sku' => 'MANAGER-VIEW-001',
        ]);

        $this->actingAs($manager)
            ->get(ProductResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSeeText($product->name);

        $search = PublicAiSelectionRun::query()->create([
            'session_hash' => hash('sha256', 'manager-ai-search'),
            'intent' => 'Нужен котёл для дома 160 м²',
            'category' => 'boiler',
            'answers' => ['area' => '160'],
            'result_product_ids' => [],
            'result_count' => 0,
            'status' => 'no_results',
            'current_step' => 'results',
        ]);
        $this->actingAs($manager)
            ->get(PublicAiSelectionRunResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSeeText($search->intent);
        $this->actingAs($manager)
            ->get(PublicAiSelectionRunResource::getUrl('view', ['record' => $search], panel: 'admin'))
            ->assertOk()
            ->assertSeeText($search->intent);

        $this->actingAs($manager)
            ->get(ProductResource::getUrl('view', ['record' => $product], panel: 'admin'))
            ->assertOk()
            ->assertSeeText($product->name);

        $this->actingAs($manager)
            ->get(ProductResource::getUrl('edit', ['record' => $product], panel: 'admin'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(UserResource::getUrl('index', panel: 'admin'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(UserResource::getUrl('create', panel: 'admin'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(OrderResource::getUrl('create', panel: 'admin'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(IntegrationSourceResource::getUrl('index', panel: 'admin'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(Dashboard::getUrl(panel: 'admin'))
            ->assertOk();
    }

    public function test_product_row_opens_read_only_view_by_default_for_manager_and_admin(): void
    {
        $category = Category::query()->create([
            'name' => 'Просмотр каталога',
            'slug' => 'catalog-view-default',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Товар для просмотра',
            'slug' => 'default-product-view',
            'sku' => 'DEFAULT-VIEW-001',
        ]);
        $viewUrl = ProductResource::getUrl('view', ['record' => $product], panel: 'admin');

        foreach (['manager', 'admin'] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);
            $this->actingAs($user);
            Filament::setCurrentPanel(Filament::getPanel('admin'));

            $component = Livewire::test(ListProducts::class);
            $nameColumn = $component->instance()->getTable()->getColumn('name');
            $nameColumn->record($product);

            $this->assertSame($viewUrl, $component->instance()->getTable()->getRecordUrl($product));
            $this->assertSame($viewUrl, $nameColumn->getUrl());
        }
    }

    public function test_manager_can_start_market_research_from_read_only_product_preview(): void
    {
        $category = Category::query()->create([
            'name' => 'Исследование рынка менеджером',
            'slug' => 'manager-market-research',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Товар для исследования рынка',
            'slug' => 'manager-market-research-product',
            'sku' => 'MANAGER-RESEARCH-001',
            'price' => 100,
        ]);
        $manager = User::factory()->create([
            'role' => 'manager',
            'is_active' => true,
        ]);

        $this->actingAs($manager);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ViewProduct::class, ['record' => $product->getRouteKey()])
            ->assertActionVisible('research_market')
            ->assertActionHidden('refresh_market_research');
    }

    public function test_manager_can_follow_supplier_from_sync_journal_but_cannot_edit_it(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
            'is_active' => true,
        ]);
        $supplier = Supplier::query()->create([
            'code' => 'manager-visible-supplier',
            'name' => 'Поставщик для менеджера',
            'is_active' => true,
        ]);
        SupplierSync::query()->create([
            'supplier_id' => $supplier->id,
            'key' => 'manager_visible_sync',
            'name' => $supplier->name,
            'code' => $supplier->code,
            'title' => 'Тестовая синхронизация',
            'command' => 'supplier:sync-manager-visible',
            'is_active' => true,
        ]);
        SupplierSyncRun::query()->create([
            'command' => 'supplier:sync-manager-visible',
            'status' => 'success',
            'supplier_names' => $supplier->name,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $supplierUrl = SupplierResource::getUrl('view', ['record' => $supplier], panel: 'admin');

        $this->actingAs($manager)
            ->get(SupplierSyncRunResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSeeText($supplier->name)
            ->assertSee($supplierUrl, false);

        $this->actingAs($manager)
            ->get($supplierUrl)
            ->assertOk();

        $this->assertFalse(SupplierResource::canEdit($supplier));
        $this->actingAs($manager)
            ->get(SupplierResource::getUrl('edit', ['record' => $supplier], panel: 'admin'))
            ->assertForbidden();
    }

    public function test_administrator_keeps_full_access_and_manager_role_is_assignable(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        $this->assertSame('Менеджер', User::ROLES['manager']);
        $this->assertTrue(UserResource::canViewAny());
        $this->assertTrue(UserResource::canCreate());
        $this->assertTrue(IntegrationSourceResource::canViewAny());
        $this->assertTrue(AiAssistant::canAccess());
        $this->assertTrue(OneCSetup::canAccess());
    }

    public function test_inactive_manager_cannot_enter_admin_panel(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
            'is_active' => false,
        ]);

        $this->assertFalse($manager->canAccessPanel(Filament::getPanel('admin')));
    }
}
