<?php

namespace Tests\Feature;

use App\Models\B2bClient;
use App\Models\CustomerSelection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class B2bSelectionClientPricingTest extends TestCase
{
    use RefreshDatabase;

    private function draft(?User $user = null): CustomerSelection
    {
        $user ??= User::factory()->create(['role' => 'manager', 'is_crm_user' => true, 'is_active' => true]);
        $this->actingAs($user);

        return CustomerSelection::create(['user_id' => $user->id, 'name' => 'Цена для клиента', 'revision' => 1, 'meta' => ['staff_workspace' => true], 'items' => [
            101 => ['id' => 101, 'name' => 'Позиция со скидкой', 'sku' => 'CLIENT-101', 'quantity' => 2, 'price' => 100, 'pricing_type' => 'retail', 'selection_note' => 'Исходная заметка', 'workspace_purchase' => ['supplier' => 'Закрытый поставщик', 'purchase_price' => 60, 'stock' => '10 шт.', 'stock_quantity' => 10, 'is_fresh' => true]],
            102 => ['id' => 102, 'name' => 'Без изменения', 'sku' => 'CLIENT-102', 'quantity' => 1, 'price' => 20],
        ]]);
    }

    private function edit(CustomerSelection $selection, array $data)
    {
        return $this->patchJson(route('b2b.selections.update', $selection), array_replace(['action' => 'line', 'revision' => $selection->fresh()->revision, 'product_id' => 101, 'quantity' => 2], $data));
    }

    public function test_discount_does_not_compound_or_change_purchase_price_and_is_persistent(): void
    {
        $selection = $this->draft();
        $purchase = $selection->items[101]['workspace_purchase'];
        $this->edit($selection, ['pricing_mode' => 'discount', 'discount_percent' => 10, 'quantity' => 3, 'purchase_price' => 1])
            ->assertOk()->assertJsonPath('state.items.0.price', 90)->assertJsonPath('state.total', 290)->assertJsonPath('state.procurement.margin', 90);
        $this->edit($selection, ['pricing_mode' => 'discount', 'discount_percent' => 20, 'quantity' => 3])
            ->assertOk()->assertJsonPath('state.items.0.price', 80)->assertJsonPath('state.items.0.client_pricing.base_price', 100)->assertJsonPath('state.total', 260);
        $selection->refresh();
        $this->assertSame($purchase, $selection->items[101]['workspace_purchase']);
        $this->assertSame(20, $selection->items[102]['price']);
        $this->assertSame($selection->user_id, $selection->items[101]['client_pricing']['updated_by']);
        $this->get(route('b2b.selections.show', $selection))->assertOk()->assertSee('Цена для клиента')->assertSee('80,00');
        $this->edit($selection, ['quantity' => 4])->assertOk()->assertJsonPath('state.items.0.price', 80)->assertJsonPath('state.items.0.client_pricing.discount_percent', 20);
    }

    public function test_manual_price_can_show_a_loss_and_reset_restores_original_price_and_note(): void
    {
        $selection = $this->draft(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $this->edit($selection, ['pricing_mode' => 'price', 'client_price' => '55.55'])->assertOk()
            ->assertJsonPath('state.items.0.price', 55.55)->assertJsonPath('state.procurement.margin', -8.9)->assertJsonPath('state.total', 131.1);
        $this->edit($selection, ['pricing_mode' => 'reset'])->assertOk()->assertJsonPath('state.items.0.price', 100)
            ->assertJsonPath('state.items.0.selection_note', 'Исходная заметка')->assertJsonMissingPath('state.items.0.client_pricing');
        $this->assertSame(60, $selection->fresh()->items[101]['workspace_purchase']['purchase_price']);
    }

    public function test_bad_prices_and_stale_or_foreign_edits_do_not_change_the_selection(): void
    {
        $selection = $this->draft();
        foreach (['0', '-1', '1.234', '1000000000'] as $price) {
            $this->edit($selection, ['pricing_mode' => 'price', 'client_price' => $price])->assertUnprocessable();
        }
        foreach ([-1, 100, '10.123'] as $discount) {
            $this->edit($selection, ['pricing_mode' => 'discount', 'discount_percent' => $discount])->assertUnprocessable();
        }
        $this->assertSame(1, $selection->fresh()->revision);
        $this->edit($selection, ['pricing_mode' => 'price', 'client_price' => 90])->assertOk();
        $this->edit($selection, ['pricing_mode' => 'price', 'client_price' => 1, 'revision' => 1])->assertConflict();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $this->edit($selection, ['pricing_mode' => 'price', 'client_price' => 1])->assertNotFound();
        $this->assertSame(90, $selection->fresh()->items[101]['price']);
    }

    public function test_partner_cannot_set_a_client_price_but_can_edit_quantity(): void
    {
        $user = User::factory()->create(['role' => 'client', 'is_crm_user' => true, 'is_active' => true, 'client_type' => 'wholesale', 'b2b_approved' => true]);
        B2bClient::create(['user_id' => $user->id, 'status' => 'approved', 'partner_type' => 'wholesale', 'company_name' => 'Партнёр']);
        $selection = $this->draft($user);
        $this->get(route('b2b.selections.show', $selection))->assertOk()->assertDontSee('data-desk-client-pricing', false);
        $this->edit($selection, ['pricing_mode' => 'price', 'client_price' => 1])->assertForbidden();
        $this->edit($selection, ['client_price' => 1])->assertForbidden();
        $this->edit($selection, ['quantity' => 3])->assertOk()->assertJsonPath('state.items.0.price', 100)->assertJsonPath('state.procurement', null);
    }

    public function test_discount_rounds_unit_price_to_kopecks_and_rejects_zero_result_or_missing_base(): void
    {
        $selection = $this->draft();
        $items = $selection->items;
        $items[101]['price'] = 0.05;
        $selection->update(['items' => $items]);
        $this->edit($selection, ['pricing_mode' => 'discount', 'discount_percent' => 10])->assertOk()->assertJsonPath('state.items.0.price', 0.05);
        $this->edit($selection, ['pricing_mode' => 'discount', 'discount_percent' => 99.99])->assertUnprocessable();
        $items = $selection->fresh()->items;
        $items[101]['price'] = 0;
        unset($items[101]['client_pricing']);
        $selection->update(['items' => $items]);
        $this->edit($selection, ['pricing_mode' => 'discount', 'discount_percent' => 10])->assertUnprocessable();
        $this->edit($selection, ['pricing_mode' => 'price', 'client_price' => 25])->assertOk()->assertJsonPath('state.items.0.price', 25);
    }

    public function test_client_documents_use_the_agreed_price_without_procurement_and_copy_undo_keep_it(): void
    {
        $selection = $this->draft();
        $this->edit($selection, ['pricing_mode' => 'price', 'client_price' => '88.88'])->assertOk();
        $this->get(route('b2b.selections.export', [$selection, 'format' => 'print']))->assertOk()->assertSee('88,88')->assertDontSee('Закрытый поставщик');
        $response = $this->get(route('b2b.selections.export', $selection))->assertOk();
        $file = tempnam(sys_get_temp_dir(), 'kotlov-client-price-');
        try {
            file_put_contents($file, $response->streamedContent());
            $sheet = IOFactory::load($file)->getActiveSheet();
            $this->assertSame(88.88, $sheet->getCell('C4')->getValue());
            $this->assertSame('F', $sheet->getHighestDataColumn());
        } finally {
            @unlink($file);
        }
        $copied = $this->patchJson(route('b2b.selections.update', $selection), ['action' => 'copy', 'revision' => 2, 'ids' => [101], 'name' => 'Копия'])->assertOk()->json();
        $this->assertSame(88.88, CustomerSelection::find($copied['target']['id'])->items[101]['price']);
        $this->patchJson(route('b2b.selections.update', $selection), ['action' => 'remove', 'revision' => 3, 'ids' => [101]])->assertOk();
        $this->patchJson(route('b2b.selections.update', $selection), ['action' => 'undo', 'revision' => 4])->assertOk();
        $this->assertSame(88.88, $selection->fresh()->items[101]['price']);
    }
}
