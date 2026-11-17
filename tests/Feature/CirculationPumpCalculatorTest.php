<?php

namespace Tests\Feature;

use App\Models\B2bClient;
use App\Models\Category;
use App\Models\CustomerSelection;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\CirculationPumpCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CirculationPumpCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function parameters(array $overrides = []): array
    {
        return array_replace(['run' => 1, 'scenario' => 'new', 'load_method' => 'power', 'power' => 10, 'supply' => 70, 'return' => 50, 'head_method' => 'estimate', 'pipe_length' => 60, 'resistance' => 100, 'factor' => '2.2', 'fluid' => 'water'], $overrides);
    }

    private function pump(string $sku = 'PUMP-180', array $overrides = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'tsirkulyatsionnyie'], ['name' => 'Циркуляционные насосы', 'is_active' => true]);

        return Product::create(array_replace(['category_id' => $category->id, 'name' => 'Циркуляционный насос отопления '.$sku, 'slug' => strtolower($sku), 'sku' => $sku, 'price' => 200, 'is_active' => true, 'is_archived' => false,
            'specs' => [['key' => 'Максимальный напор', 'value' => '6', 'unit' => 'м'], ['key' => 'Максимальный расход', 'value' => '3', 'unit' => 'м³/ч'], ['key' => 'Монтажная длина', 'value' => '180', 'unit' => 'мм'], ['key' => 'Напряжение', 'value' => '220', 'unit' => 'В']]], $overrides));
    }

    public function test_water_flow_and_rough_head_follow_documented_formulas_and_state_assumptions(): void
    {
        $service = app(CirculationPumpCalculator::class);
        $result = $service->calculate($service->validate($this->parameters()));
        $this->assertEqualsWithDelta(0.43, $result['flow'], 0.000001);
        $this->assertEqualsWithDelta(1.32, $result['head'], 0.000001);
        $this->assertTrue($result['approximate']);
        $floor = $service->calculate($service->validate($this->parameters(['supply' => 40, 'return' => 35, 'head_method' => 'known', 'head' => 3])));
        $this->assertEqualsWithDelta(1.72, $floor['flow'], 0.000001);
        $this->assertSame(3.0, $floor['head']);
        $area = $service->calculate($service->validate($this->parameters(['load_method' => 'area', 'area' => 100, 'heat_loss' => 100])));
        $this->assertEqualsWithDelta($result['flow'], $area['flow'], 0.000001);
        $this->get(route('pump-calculator', $this->parameters()))->assertOk()->assertSee('0,430')->assertSee('1,32')->assertSee('Рабочая кривая насоса и совместимость не подтверждены.');
    }

    public function test_bad_inputs_and_glycol_estimation_never_produce_an_unsupported_calculation(): void
    {
        foreach ([['return' => 70], ['pipe_length' => 0], ['heat_loss' => null, 'load_method' => 'area', 'area' => 100], ['fluid' => 'glycol'], ['power' => ['bad']], ['scenario' => ['bad']]] as $overrides) {
            $this->get(route('pump-calculator', $this->parameters($overrides)))->assertOk()->assertViewHas('result', null)->assertSee('Проверьте параметры');
        }
        $this->get(route('pump-calculator', $this->parameters(['load_method' => 'direct', 'flow' => 0.7, 'head' => 3, 'fluid' => 'glycol'])))->assertOk()
            ->assertViewHas('result', fn ($result) => $result['flow'] === 0.7 && $result['head'] === 3.0)->assertSee('По вашим расчётным данным');
        $this->get(route('pump-calculator'))->assertOk()->assertDontSee('Кандидаты для проверки');
    }

    public function test_candidates_exclude_boosters_dhw_accessories_unknown_and_insufficient_parameters(): void
    {
        $pump = $this->pump();
        $this->pump('BOOST', ['name' => 'Насос для повышения давления']);
        $this->pump('DHW', ['name' => 'Насос для ГВС']);
        $this->pump('PART', ['name' => 'Прокладка для циркуляционного насоса']);
        $this->pump('EMPTY', ['specs' => []]);
        $this->pump('LOW', ['specs' => [['key' => 'Максимальный напор', 'value' => '0.5', 'unit' => 'м'], ['key' => 'Максимальный расход', 'value' => '3', 'unit' => 'м³/ч']]]);
        $this->get(route('pump-calculator', $this->parameters(['length' => '180', 'voltage' => '220'])))->assertOk()
            ->assertViewHas('products', fn ($items) => $items->pluck('id')->all() === [$pump->id])->assertSee('Нужна проверка кривой');
        $this->get(route('pump-calculator', $this->parameters(['length' => '130'])))->assertOk()->assertSee('По этим данным кандидатов нет');
    }

    public function test_replacement_first_finds_source_then_uses_explicit_dimensions_without_claiming_compatibility(): void
    {
        $source = $this->pump('OLD-180');
        $candidate = $this->pump('OTHER-180');
        $this->get(route('pump-calculator', ['run' => 1, 'scenario' => 'replacement', 'model' => 'OLD-180']))->assertOk()
            ->assertViewHas('products', fn ($items) => $items->pluck('id')->all() === [$source->id]);
        $this->get(route('pump-calculator', ['run' => 1, 'scenario' => 'replacement', 'reference' => $source->id]))->assertOk()
            ->assertViewHas('products', fn ($items) => $items->pluck('id')->all() === [$candidate->id])->assertSee('Совпадение размеров ещё не подтверждает замену.');
    }

    public function test_saving_recomputes_result_and_prices_and_prints_inputs_without_touching_cart(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $pump = $this->pump();
        $cart = [999 => ['id' => 999, 'name' => 'Не менять корзину', 'price' => 8, 'quantity' => 2]];
        $this->actingAs($user)->withSession(['cart' => $cart, 'cart_owner_id' => $user->id])->post(route('pump-calculator.save'), $this->parameters(['name' => 'Котельная', 'product_id' => $pump->id, 'quantity' => 2, 'price' => 0.01, 'result' => ['flow' => 900]]))->assertRedirect()->assertSessionHas('cart', $cart);
        $selection = CustomerSelection::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(200, $selection->items[$pump->id]['price']);
        $record = array_values($selection->meta['pump_calculations'])[0];
        $this->assertEqualsWithDelta(0.43, $record['result']['flow'], 0.000001);
        $this->assertArrayNotHasKey('result', $record['input']);
        $this->get(route('selections.show', [$selection, 'print' => 1]))->assertOk()->assertSee('Расчёты циркуляционных насосов')->assertSee('0,430');
        $this->get(route('b2b.selections.export', [$selection, 'format' => 'print']))->assertOk()->assertSee('Длина контура, м: 60');
        $this->post(route('pump-calculator.save'), $this->parameters(['selection_id' => $selection->id, 'revision' => $selection->revision, 'product_id' => $pump->id, 'quantity' => 4]))->assertConflict();
        $this->assertSame(2, $selection->fresh()->items[$pump->id]['quantity']);
    }

    public function test_foreign_and_stale_selections_are_protected_and_calculation_survives_copy_and_cart_update(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $other = User::factory()->create();
        $foreign = CustomerSelection::create(['user_id' => $other->id, 'name' => 'Чужой', 'items' => [], 'revision' => 1]);
        $this->actingAs($user)->post(route('pump-calculator.save'), $this->parameters(['selection_id' => $foreign->id, 'revision' => 1]))->assertNotFound();
        $pump = $this->pump();
        $this->post(route('pump-calculator.save'), $this->parameters(['name' => 'Свой', 'product_id' => $pump->id, 'quantity' => 1]))->assertRedirect();
        $selection = CustomerSelection::where('user_id', $user->id)->firstOrFail();
        $this->post(route('pump-calculator.save'), $this->parameters(['selection_id' => $selection->id, 'revision' => 99]))->assertConflict();
        $copy = $this->patchJson(route('b2b.selections.update', $selection), ['action' => 'copy', 'revision' => 1, 'ids' => [$pump->id], 'name' => 'Копия расчёта'])->assertOk()->json('target.id');
        $this->get(route('selections.show', [$copy, 'print' => 1]))->assertOk()->assertSee('0,430');
        $selection->refresh();
        $this->withSession(['cart' => $selection->items, 'cart_owner_id' => $user->id, 'cart_selection_id' => $selection->id])->post(route('selections.store'), ['selection_id' => $selection->id, 'revision' => $selection->revision, 'name' => 'Обновлённый'])->assertRedirect();
        $this->assertNotEmpty($selection->fresh()->meta['pump_calculations']);
    }

    public function test_calculation_can_be_saved_without_a_product_and_needs_authentication(): void
    {
        $this->post(route('pump-calculator.save'), $this->parameters(['name' => 'Расчёт']))->assertRedirect(route('login'));
        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user)->post(route('pump-calculator.save'), $this->parameters(['name' => 'Только расчёт']))->assertRedirect();
        $selection = CustomerSelection::where('user_id', $user->id)->firstOrFail();
        $this->assertSame([], $selection->items);
        $this->assertCount(1, $selection->meta['pump_calculations']);
    }

    public function test_partner_uses_existing_b2b_price_engine_and_never_receives_cost_fields(): void
    {
        $pump = $this->pump();
        $supplier = Supplier::create(['code' => 'calc-test', 'name' => 'Тестовый поставщик', 'is_active' => true, 'b2b_partner_enabled' => true, 'b2b_require_positive_stock' => true, 'b2b_stock_max_age_hours' => 24]);
        SupplierProduct::create(['supplier_id' => $supplier->id, 'product_id' => $pump->id, 'supplier_article' => 'CALC-1', 'price_byn' => 100, 'price' => 100, 'currency' => 'BYN', 'match_status' => 'matched', 'in_stock' => true, 'stock_quantity' => 7, 'last_stock_synced_at' => now(), 'last_synced_at' => now()]);
        $supplier->b2bPricingRules()->create(['name' => 'Расчётная цена', 'scope_type' => 'supplier', 'calculation_type' => 'target_margin', 'value' => 20, 'minimum_profit_byn' => 0, 'minimum_margin_percent' => 10, 'rounding_step_byn' => 1, 'minimum_retail_discount_percent' => 0, 'priority' => 100, 'status' => 'active']);
        $user = User::factory()->create(['client_type' => 'wholesale', 'b2b_approved' => true, 'is_active' => true]);
        B2bClient::create(['user_id' => $user->id, 'status' => 'approved', 'partner_type' => 'wholesale', 'company_name' => 'Клиент калькулятора', 'allowed_supplier_ids' => [$supplier->id], 'allowed_integration_source_ids' => []]);
        $this->actingAs($user)->get(route('pump-calculator', $this->parameters()))->assertOk()->assertSee('125,00 BYN')->assertDontSee('purchase_price', false);
        $this->post(route('pump-calculator.save'), $this->parameters(['name' => 'B2B', 'product_id' => $pump->id, 'quantity' => 2, 'price' => 1]))->assertRedirect();
        $line = CustomerSelection::where('user_id', $user->id)->firstOrFail()->items[$pump->id];
        $this->assertEquals(125, $line['price']);
        $this->assertSame('b2b', $line['pricing_type']);
        $this->assertArrayNotHasKey('workspace_purchase', $line);
    }

    public function test_connection_filter_only_uses_explicit_catalogue_values_and_login_keeps_parameters(): void
    {
        $known = $this->pump('CONNECTION');
        $known->update(['specs' => array_merge($known->specs, [['key' => 'Присоединение', 'value' => 'G 1½']])]);
        $this->pump('UNKNOWN');
        $this->get(route('pump-calculator', $this->parameters(['connection' => 'G 1½'])))->assertOk()
            ->assertViewHas('products', fn ($items) => $items->pluck('id')->all() === [$known->id]);
        $this->get(route('pump-calculator.login', $this->parameters(['redirect' => 'https://example.com'])))->assertRedirect(route('login'))
            ->assertSessionHas('url.intended', fn ($url) => str_starts_with($url, route('pump-calculator').'?') && str_contains($url, 'power=10') && ! str_contains($url, 'example.com'));
    }
}
