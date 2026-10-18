<?php

namespace Tests\Feature;

use App\Filament\Resources\IntegrationExchangeRuns\IntegrationExchangeRunResource;
use App\Filament\Resources\SupplierSyncRuns\Pages\ListSupplierSyncRuns;
use App\Filament\Widgets\IntegrationHealthOverview;
use App\Filament\Widgets\RecentIntegrationRuns;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class UnifiedSynchronizationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_sync_journal_is_the_single_navigation_center_for_all_exchanges(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $component = Livewire::actingAs($admin)->test(ListSupplierSyncRuns::class);
        $method = new ReflectionMethod($component->instance(), 'getHeaderWidgets');

        $this->assertSame([
            IntegrationHealthOverview::class,
            RecentIntegrationRuns::class,
        ], $method->invoke($component->instance()));
        $this->assertFalse(IntegrationExchangeRunResource::shouldRegisterNavigation());

        $component
            ->assertSeeText('Центр синхронизаций')
            ->assertSeeText('Синхронизации поставщиков');
    }
}
