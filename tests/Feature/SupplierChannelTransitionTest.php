<?php

namespace Tests\Feature;

use App\Filament\Resources\IntegrationSources\IntegrationSourceResource;
use App\Filament\Resources\IntegrationSources\Pages\EditIntegrationSource;
use App\Models\Category;
use App\Models\IntegrationExchangeRun;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierChannelTransition;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\Integrations\SupplierChannelTransitionPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierChannelTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_reports_coverage_and_blockers_without_changing_product_links(): void
    {
        [$source, $legacyCovered, $legacyMissing] = $this->transitionFixture();
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $legacyCovered->id,
            'external_id' => 'covered',
            'match_status' => 'matched',
            'price' => 20,
            'stock_quantity' => 2,
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'unmatched-stock',
            'match_status' => 'unmatched',
            'price' => 10,
            'stock_quantity' => 3,
        ]);

        $legacyBefore = SupplierProduct::query()->pluck('product_id')->all();
        $integrationBefore = IntegrationProduct::query()->pluck('product_id')->all();
        $preview = app(SupplierChannelTransitionPlanner::class)->preview($source);

        $this->assertSame(2, $preview['legacy_products']);
        $this->assertSame(1, $preview['matched_products']);
        $this->assertSame(1, $preview['shared_products']);
        $this->assertSame(1, $preview['legacy_only_products']);
        $this->assertSame(1, $preview['unmatched_in_stock']);
        $this->assertFalse($preview['can_start_control_exchange']);
        $this->assertCount(2, $preview['blockers']);
        $this->assertSame($legacyBefore, SupplierProduct::query()->pluck('product_id')->all());
        $this->assertSame($integrationBefore, IntegrationProduct::query()->pluck('product_id')->all());
        $this->assertTrue(Product::query()->findOrFail($legacyMissing->id)->is($legacyMissing));
    }

    public function test_recording_preview_creates_audit_entry_but_keeps_legacy_channel_untouched(): void
    {
        [$source, $product] = $this->transitionFixture(oneLegacyProduct: true);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $product->id,
            'external_id' => 'ready-product',
            'match_status' => 'matched',
            'price' => 50,
            'stock_quantity' => 4,
        ]);

        $transition = app(SupplierChannelTransitionPlanner::class)->recordPreview($source, $admin->id);

        $this->assertSame(SupplierChannelTransition::STATUS_PREVIEWED, $transition->status);
        $this->assertSame($admin->id, $transition->previewed_by);
        $this->assertTrue($transition->snapshot['can_start_control_exchange']);
        $this->assertSame(1, SupplierProduct::query()
            ->where('supplier_id', $source->supplier_id)
            ->count());
        $this->assertSame(1, IntegrationProduct::query()
            ->where('integration_source_id', $source->id)
            ->count());
        $this->assertTrue($source->fresh()->is_active);
        $this->assertNull($transition->legacy_disabled_at);
    }

    public function test_edit_screen_explains_safe_transition_and_renders_preview_action(): void
    {
        [$source] = $this->transitionFixture(oneLegacyProduct: true);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(IntegrationSourceResource::getUrl('edit', ['record' => $source], panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Переход со старого канала на 1С')
            ->assertSeeText('Проверить переход на 1С')
            ->assertSeeText('Старый канал отключить нельзя');

        Livewire::actingAs($admin)
            ->test(EditIntegrationSource::class, ['record' => $source->getRouteKey()])
            ->callAction('previewTransition')
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('supplier_channel_transitions', [
            'supplier_id' => $source->supplier_id,
            'integration_source_id' => $source->id,
            'previewed_by' => $admin->id,
            'status' => SupplierChannelTransition::STATUS_PREVIEWED,
        ]);
        $this->assertSame(1, SupplierProduct::query()
            ->where('supplier_id', $source->supplier_id)
            ->count());
    }

    public function test_only_a_new_completed_control_exchange_marks_a_clean_preview_ready(): void
    {
        [$source, $product] = $this->transitionFixture(oneLegacyProduct: true);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $product->id,
            'external_id' => 'control-ready',
            'match_status' => 'matched',
            'price' => 80,
            'stock_quantity' => 2,
        ]);
        $planner = app(SupplierChannelTransitionPlanner::class);
        $transition = $planner->recordPreview($source, null);

        $this->assertSame(SupplierChannelTransition::STATUS_PREVIEWED, $planner
            ->refreshLatestReadiness($source)?->status);

        $run = IntegrationExchangeRun::query()->create([
            'integration_source_id' => $source->id,
            'direction' => 'inbound',
            'operation' => 'catalog',
            'status' => 'success',
            'started_at' => $transition->created_at->addSecond(),
            'finished_at' => $transition->created_at->addSeconds(2),
            'summary' => [
                'stock_snapshot' => ['completed_at' => $transition->created_at->addSeconds(2)->toIso8601String()],
            ],
        ]);

        $ready = $planner->refreshLatestReadiness($source);

        $this->assertSame(SupplierChannelTransition::STATUS_READY, $ready?->status);
        $this->assertSame($run->id, $ready?->control_exchange_run_id);
        $this->assertNotNull($ready?->ready_at);
        $this->assertSame(1, SupplierProduct::query()
            ->where('supplier_id', $source->supplier_id)
            ->count());
    }

    /** @return array{IntegrationSource, Product, Product|null} */
    private function transitionFixture(bool $oneLegacyProduct = false): array
    {
        $supplier = Supplier::query()->create([
            'code' => 'transition-supplier',
            'name' => 'Поставщик перехода',
        ]);
        $source = IntegrationSource::query()->create([
            'supplier_id' => $supplier->id,
            'code' => 'transition-onec',
            'name' => '1С поставщика перехода',
            'driver' => 'commerceml',
            'is_active' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Категория перехода',
            'slug' => 'transition-category',
            'parent_id' => 0,
        ]);
        $first = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'TRANSITION-1',
            'name' => 'Первый товар перехода',
            'slug' => 'transition-product-1',
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $first->id,
            'supplier_article' => 'OLD-1',
        ]);

        if ($oneLegacyProduct) {
            return [$source, $first, null];
        }

        $second = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'TRANSITION-2',
            'name' => 'Второй товар перехода',
            'slug' => 'transition-product-2',
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $second->id,
            'supplier_article' => 'OLD-2',
        ]);

        return [$source, $first, $second];
    }
}
