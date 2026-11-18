<?php

namespace Tests\Feature;

use App\Models\B2bClient;
use App\Models\CustomerSelection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class B2bSelectionDeskTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function selection(User $user, array $items = []): CustomerSelection
    {
        return CustomerSelection::create(['user_id' => $user->id, 'name' => 'Котельная, объект 1', 'revision' => 1, 'items' => $items ?: [
            101 => ['id' => 101, 'name' => '=НЕ ФОРМУЛА', 'sku' => '=SKU-101', 'quantity' => 2, 'price' => 50, 'workspace_purchase' => ['supplier' => 'Закрытый поставщик', 'purchase_price' => 13.37, 'stock' => '3 шт.', 'stock_quantity' => 3, 'is_fresh' => true]],
            102 => ['id' => 102, 'name' => 'Вторая позиция', 'sku' => 'SKU-102', 'quantity' => 1, 'price' => 20],
        ], 'meta' => ['staff_workspace' => true]]);
    }

    public function test_selected_rows_are_persistent_and_client_print_contains_only_selected_public_prices(): void
    {
        $user = $this->admin();
        $selection = $this->selection($user);
        $url = route('b2b.selections.update', $selection);
        $this->actingAs($user)->get(route('b2b.selections.show', $selection))->assertOk()->assertSee('data-order-desk', false);
        $this->patchJson($url, ['action' => 'select', 'revision' => 1, 'ids' => [102]])->assertOk()->assertJsonPath('state.excluded.0', 101);
        $this->assertCount(2, $selection->fresh()->items);
        $this->get(route('b2b.selections.export', [$selection, 'format' => 'print']))->assertOk()->assertSee('Вторая позиция')->assertDontSee('=НЕ ФОРМУЛА')->assertDontSee('Закрытый поставщик');
        $this->patchJson($url, ['action' => 'quantity', 'revision' => 1, 'product_id' => 102, 'quantity' => 2])->assertConflict();
        $this->patchJson($url, ['action' => 'quantity', 'revision' => 2, 'product_id' => 102, 'quantity' => 7])->assertOk()->assertJsonPath('state.revision', 3);
        $this->assertSame(7, $selection->fresh()->items[102]['quantity']);
    }

    public function test_move_is_atomic_and_never_silently_merges_duplicate_or_foreign_lines(): void
    {
        $user = $this->admin();
        $source = $this->selection($user);
        $target = $this->selection($user);
        $this->actingAs($user)->patchJson(route('b2b.selections.update', $source), ['action' => 'move', 'revision' => 1, 'ids' => [101], 'target_id' => $target->id, 'target_revision' => 1])->assertConflict();
        $this->assertCount(2, $source->fresh()->items);
        $foreign = $this->selection($this->admin());
        $this->patchJson(route('b2b.selections.update', $source), ['action' => 'move', 'revision' => 1, 'ids' => [101], 'target_id' => $foreign->id, 'target_revision' => 1])->assertNotFound();
        $moved = $this->patchJson(route('b2b.selections.update', $source), ['action' => 'move', 'revision' => 1, 'ids' => [101], 'name' => 'Новый объект'])->assertOk()->json();
        $this->assertCount(1, $source->fresh()->items);
        $this->assertSame(13.37, CustomerSelection::find($moved['target']['id'])->items[101]['workspace_purchase']['purchase_price']);
    }

    public function test_copy_remove_and_undo_preserve_all_other_lines_and_selection_comments(): void
    {
        $user = $this->admin();
        $source = $this->selection($user);
        $url = route('b2b.selections.update', $source);
        $this->actingAs($user)->patchJson($url, ['action' => 'copy', 'revision' => 1, 'ids' => [101], 'name' => 'Копия'])->assertOk();
        $this->assertCount(2, $source->fresh()->items);
        $this->patchJson($url, ['action' => 'remove', 'revision' => 2, 'ids' => [101]])->assertOk()->assertJsonPath('state.undo_available', true);
        $this->assertCount(1, $source->fresh()->items);
        $this->patchJson($url, ['action' => 'undo', 'revision' => 3])->assertOk()->assertJsonPath('state.undo_available', false);
        $this->assertCount(2, $source->fresh()->items);
        $this->patchJson($url, ['action' => 'details', 'revision' => 4, 'name' => 'Новая заявка', 'note' => 'Второй этаж'])->assertOk()->assertJsonPath('state.note', 'Второй этаж');
    }

    public function test_partner_has_no_internal_costs_and_cannot_export_an_internal_workbook(): void
    {
        $user = User::factory()->create(['is_active' => true, 'client_type' => 'wholesale', 'b2b_approved' => true]);
        B2bClient::create(['user_id' => $user->id, 'status' => 'approved', 'partner_type' => 'wholesale', 'company_name' => 'Партнёр']);
        $selection = $this->selection($user);
        $this->actingAs($user)->get(route('b2b.selections.show', $selection))->assertOk()->assertDontSee('Закрытый поставщик')->assertDontSee('13.37');
        $this->get(route('b2b.selections.export', [$selection, 'internal' => 1]))->assertForbidden();
        $this->get(route('b2b.selections.show', $this->selection($this->admin())))->assertNotFound();
    }

    public function test_excel_uses_literal_text_and_does_not_include_procurement_columns_by_default(): void
    {
        $user = $this->admin();
        $selection = $this->selection($user);
        $response = $this->actingAs($user)->get(route('b2b.selections.export', $selection))->assertOk();
        $file = tempnam(sys_get_temp_dir(), 'kotlov-xlsx-');
        try {
            file_put_contents($file, $response->streamedContent());
            $sheet = IOFactory::load($file)->getActiveSheet();
            $this->assertSame('=SKU-101', $sheet->getCell('A4')->getValue());
            $this->assertSame('s', $sheet->getCell('A4')->getDataType());
            $this->assertSame(100.0, $sheet->getCell('E4')->getValue());
            $this->assertSame('F', $sheet->getHighestDataColumn());
        } finally {
            @unlink($file);
        }
    }

    public function test_retail_accounts_do_not_get_business_actions(): void
    {
        $user = User::factory()->create(['role' => 'client', 'is_active' => true]);
        $selection = $this->selection($user);
        $this->actingAs($user)->get(route('b2b.selections.show', $selection))->assertForbidden();
        $this->patchJson(route('b2b.selections.update', $selection), ['action' => 'remove', 'revision' => 1, 'ids' => [101]])->assertForbidden();
    }

    public function test_crm_manager_internal_export_is_explicit_and_client_export_stays_public(): void
    {
        $user = User::factory()->create(['role' => 'manager', 'is_crm_user' => true, 'is_active' => true]);
        $selection = $this->selection($user);
        $this->actingAs($user);
        foreach ([false, true] as $internal) {
            $response = $this->get(route('b2b.selections.export', [$selection, 'internal' => $internal ? 1 : 0]))->assertOk();
            $file = tempnam(sys_get_temp_dir(), 'kotlov-crm-export-');
            try {
                file_put_contents($file, $response->streamedContent());
                $sheet = IOFactory::load($file)->getActiveSheet();
                $this->assertSame($internal ? 'I' : 'F', $sheet->getHighestDataColumn());
                $values = json_encode($sheet->toArray(), JSON_UNESCAPED_UNICODE);
                if ($internal) {
                    $this->assertStringContainsString('Закрытый поставщик', $values);
                } else {
                    $this->assertStringNotContainsString('Закрытый поставщик', $values);
                }
            } finally {
                @unlink($file);
            }
        }
    }
}
