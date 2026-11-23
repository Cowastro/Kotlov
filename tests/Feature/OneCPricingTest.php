<?php

namespace Tests\Feature;

use App\Filament\Resources\OneCItems\Pages\ListOneCItems;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\OneCItem;
use App\Models\OneCPriceBatch;
use App\Models\Product;
use App\Models\User;
use App\Services\Integrations\OneCBridge\OneCItemCosts;
use App\Services\Integrations\OneCBridge\OneCPurchaseCosts;
use App\Services\Integrations\OneCPriceBatches;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class OneCPricingTest extends TestCase
{
    use RefreshDatabase;

    private const BOILER = 'aaaaaaaa-0000-0000-0000-000000000001';

    private const PIPE = 'aaaaaaaa-0000-0000-0000-000000000002';

    private const VALVE = 'aaaaaaaa-0000-0000-0000-000000000003';

    private function purchase(string $item, string $date, float $gross, float $qty = 1, string $doc = '00УТ-000001'): void
    {
        static $line = 0;
        DB::table('onec_purchase_lines')->insert(['doc_key' => 'dddddddd-0000-0000-0000-'.str_pad((string) ++$line, 12, '0', STR_PAD_LEFT),
            'line_number' => 1, 'doc_number' => $doc, 'doc_date' => $date.' 10:00:00', 'item_key' => $item, 'quantity' => $qty,
            'price_net' => round($gross / 1.2, 4), 'price_gross' => $gross, 'currency_key' => OneCPurchaseCosts::BYN]);
    }

    public function test_the_cost_is_the_last_purchase_or_the_3_month_average_and_prices_go_by_markup(): void
    {
        foreach ([self::BOILER => 'Котёл тестовый 24', self::PIPE => 'Труба тестовая 100', self::VALVE => 'Кран тестовый'] as $key => $name) {
            OneCItem::query()->create(['ref_key' => $key, 'code' => 'УТ-'.substr($key, -3), 'description' => $name]);
        }
        // the boiler: the last purchase counts
        $this->purchase(self::BOILER, '2026-08-01', 900);
        $this->purchase(self::BOILER, '2026-11-10', 1000, 1, '00УТ-000777');
        // the pipe: the last purchase has no price — the 3-month average (10×20 and 30×10 → 12.5)
        $this->purchase(self::PIPE, '2026-09-20', 20, 10);
        $this->purchase(self::PIPE, '2026-10-20', 10, 30);
        $this->purchase(self::PIPE, '2026-11-15', 0, 5);

        $this->assertSame(2, app(OneCItemCosts::class)->refresh());
        $boiler = OneCItem::query()->where('ref_key', self::BOILER)->first();
        $this->assertSame([1000.0, OneCItemCosts::LAST, '00УТ-000777'], [$boiler->cost_gross, $boiler->cost_kind, $boiler->cost_doc]);
        $pipe = OneCItem::query()->where('ref_key', self::PIPE)->first();
        $this->assertSame([12.5, OneCItemCosts::AVERAGE], [$pipe->cost_gross, $pipe->cost_kind]);

        // retail +35 %, wholesale +15 %, whole rubles; the valve was never bought — left for the manager
        $batch = app(OneCPriceBatches::class)->addFromItems(OneCItem::query()->orderBy('ref_key')->get(), 35, 15, null);
        $this->assertSame(OneCPriceBatch::DRAFT, $batch->status);
        $items = $batch->items()->get()->keyBy('item_key');
        $this->assertSame([1350.0, 1150.0, 'markup'], [(float) $items[self::BOILER]->retail, (float) $items[self::BOILER]->wholesale, $items[self::BOILER]->verdict]);
        $this->assertSame([17.0, 14.0], [(float) $items[self::PIPE]->retail, (float) $items[self::PIPE]->wholesale]);
        $this->assertSame([0.0, 'no_cost'], [(float) $items[self::VALVE]->retail, $items[self::VALVE]->verdict]);

        // the same items again with other markups: the same draft, new prices
        $again = app(OneCPriceBatches::class)->addFromItems(OneCItem::query()->where('ref_key', self::BOILER)->get(), 40, 20, null);
        $this->assertSame($batch->id, $again->id);
        $this->assertSame(1400.0, (float) $batch->items()->where('item_key', self::BOILER)->value('retail'));
        $this->assertSame(3, $batch->items()->count());
    }

    public function test_a_price_below_the_1c_cost_is_not_sent_without_a_confirmation(): void
    {
        IntegrationSource::query()->updateOrCreate(['code' => 'onec'], ['name' => '1C']);
        OneCItem::query()->create(['ref_key' => self::BOILER, 'code' => 'УТ-001', 'description' => 'Котёл тестовый 24', 'cost_gross' => 1000, 'cost_kind' => 'last']);
        $prices = app(OneCPriceBatches::class);
        $batch = $prices->addFromItems(OneCItem::query()->get(), 35, 15, null);
        $batch->items()->update(['wholesale' => 950]);

        $this->assertSame(1, $prices->belowCost($batch));
        try {
            $prices->approve($batch, null);
            $this->fail('below the cost without a confirmation');
        } catch (DomainException $e) {
            $this->assertStringContainsString('ниже себестоимости', $e->getMessage());
        }
        $prices->approve($batch, null, belowCostConfirmed: true);
        $this->assertSame(OneCPriceBatch::APPROVED, $batch->fresh()->status);
    }

    public function test_the_nomenclature_shows_the_cost_and_the_1c_price_and_sets_prices_by_markup(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $source = IntegrationSource::query()->updateOrCreate(['code' => 'onec'], ['name' => '1C']);
        $priced = OneCItem::query()->create(['ref_key' => self::BOILER, 'code' => 'УТ-001', 'description' => 'Котёл тестовый 24', 'cost_gross' => 1000, 'cost_kind' => 'last']);
        $bare = OneCItem::query()->create(['ref_key' => self::PIPE, 'code' => 'УТ-002', 'description' => 'Труба тестовая 100', 'cost_gross' => 10, 'cost_kind' => 'last']);
        IntegrationProduct::query()->create(['integration_source_id' => $source->id, 'external_id' => self::BOILER.'#00000000-0000-0000-0000-000000000000',
            'external_code' => 'УТ-001', 'name' => 'Котёл тестовый 24', 'price' => 1300, 'price_byn' => 1300]);
        $this->assertSame(1300.0, OneCItemCosts::retail1c($priced));
        $this->assertSame('+30 %', OneCItemCosts::markup(1300, $priced));

        Livewire::test(ListOneCItems::class)->assertOk()
            ->filterTable('no_price')->assertCanSeeTableRecords([$bare])->assertCanNotSeeTableRecords([$priced])
            ->callTableBulkAction('setPrices', [$bare], ['retail' => 35, 'wholesale' => 15]);
        $this->assertSame(14.0, (float) OneCPriceBatch::query()->firstOrFail()->items()->value('retail'));
    }

    public function test_the_products_list_shows_the_1c_link_and_filters_by_it(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $source = IntegrationSource::query()->updateOrCreate(['code' => 'onec'], ['name' => '1C']);
        $category = Category::query()->firstOrCreate(['slug' => 'kotly'], ['name' => 'Котлы', 'is_active' => true]);
        $linked = Product::query()->create(['category_id' => $category->id, 'name' => 'Котёл тестовый 24', 'slug' => 'kotel-test-24', 'price' => 1300]);
        $bare = Product::query()->create(['category_id' => $category->id, 'name' => 'Кран тестовый', 'slug' => 'kran-test', 'price' => 20]);
        OneCItem::query()->create(['ref_key' => self::BOILER, 'code' => 'УТ-001', 'description' => 'Котёл тестовый 24', 'product_id' => $linked->id,
            'cost_gross' => 1000, 'cost_kind' => 'last', 'stock_on_hand' => 3, 'stock_reserved' => 1, 'stock_synced_at' => now()]);
        IntegrationProduct::query()->create(['integration_source_id' => $source->id, 'external_id' => self::BOILER, 'external_code' => 'УТ-001',
            'name' => 'Котёл тестовый 24', 'price' => 1300, 'price_byn' => 1300, 'product_id' => $linked->id, 'match_status' => 'matched']);

        Livewire::test(ListProducts::class)->assertOk()
            ->assertSee('УТ-001')->assertSee('своб. 2 (в нал. 3, резерв 1) · себест. 1 000,00 · розн. 1С 1 300,00')
            ->filterTable('onec', 'yes')->assertCanSeeTableRecords([$linked])->assertCanNotSeeTableRecords([$bare])
            ->filterTable('onec', 'no')->assertCanSeeTableRecords([$bare])->assertCanNotSeeTableRecords([$linked]);
    }
}
