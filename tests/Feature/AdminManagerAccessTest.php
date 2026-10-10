<?php

namespace Tests\Feature;

use App\Filament\Pages\AiAssistant;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\OneCSetup;
use App\Filament\Resources\ContactRequests\ContactRequestResource;
use App\Filament\Resources\IntegrationSources\IntegrationSourceResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Order;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertFalse(OrderResource::canCreate());
        $this->assertTrue(OrderResource::canEdit(new Order));
        $this->assertFalse(OrderResource::canDelete(new Order));

        $this->assertFalse(UserResource::canViewAny());
        $this->assertFalse(ProductResource::canViewAny());
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
