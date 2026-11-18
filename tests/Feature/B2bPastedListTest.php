<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\B2bPastedList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class B2bPastedListTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_quantity_is_taken_only_where_it_is_stated(): void
    {
        $list = app(B2bPastedList::class);
        $cases = [
            "KOTLOV-007972\t4" => ['KOTLOV-007972', 4, true],
            'Отвод Моно ОМ-Р 45° 430-0,8 Ø 150 — 6 шт' => ['Отвод Моно ОМ-Р 45° 430-0,8 Ø 150', 6, true],
            'Труба ТМ-Р L1000 Ø 150 × 3' => ['Труба ТМ-Р L1000 Ø 150', 3, true],
            'Насос Grundfos UPS 25-60; 2 шт.' => ['Насос Grundfos UPS 25-60', 2, true],
            // A diameter at the end is not a quantity.
            'Отвод 45° Ø 150' => ['Отвод 45° Ø 150', 1, false],
            '3. Зонт ЗМ-Р 430-0,5 Ø 120 2шт' => ['Зонт ЗМ-Р 430-0,5 Ø 120', 2, true],
        ];
        foreach ($cases as $line => $expected) {
            $this->assertSame($expected, $list->split($line), $line);
        }
    }

    public function test_lines_are_found_by_our_article_the_supplier_article_and_the_name(): void
    {
        $category = Category::create(['name' => 'Отводы', 'slug' => 'paste-elbows', 'is_active' => true]);
        $card = fn (array $values) => Product::create($values + ['category_id' => $category->id, 'price' => 30, 'is_active' => true, 'is_archived' => false]);
        $elbow = $card(['name' => 'Отвод Пастовик ПМ-Р 45°, 430-0,5, Ø 150', 'slug' => 'paste-elbow', 'sku' => 'KOTLOV-007972']);
        $apron = $card(['name' => 'Фартук Теплов и Сухов, Ø 260', 'slug' => 'paste-apron', 'sku' => 'KOTLOV-022204']);
        $card(['name' => 'Старый фартук', 'slug' => 'paste-archived', 'sku' => 'KOTLOV-009060', 'is_archived' => true, 'is_active' => false]);
        $supplier = Supplier::create(['code' => 'paste-supplier', 'name' => 'Поставщик', 'is_active' => true]);
        SupplierProduct::create(['supplier_id' => $supplier->id, 'product_id' => $apron->id, 'supplier_article' => 'TS.KMP.FRK.0260.79671', 'match_status' => 'matched']);
        $manager = User::factory()->create(['role' => 'manager', 'is_crm_user' => true, 'is_active' => true]);

        $rows = $this->actingAs($manager)->postJson(route('b2b.workspace.paste'), ['text' => implode("\n", [
            "KOTLOV-007972\t4", 'TS.KMP.FRK.0260.79671 — 2 шт', '', 'Отвод Пастовик ПМ-Р 45 430-0,5 Ø 150 × 3', 'KOTLOV-009060', 'Ыкспериментальная штуковина ЖЖ-999',
        ])])->assertOk()->json('rows');

        $this->assertCount(5, $rows);
        $this->assertSame(['found', 'article', $elbow->id, 4], [$rows[0]['status'], $rows[0]['by'], $rows[0]['candidates'][0]['id'], $rows[0]['quantity']]);
        $this->assertSame(['found', 'article', $apron->id, 2], [$rows[1]['status'], $rows[1]['by'], $rows[1]['candidates'][0]['id'], $rows[1]['quantity']]);
        $this->assertSame(['found', 'name', $elbow->id, 3], [$rows[2]['status'], $rows[2]['by'], $rows[2]['candidates'][0]['id'], $rows[2]['quantity']]);
        // An archived card is never offered; a line with nothing like it is left for the manager.
        $this->assertNotContains('KOTLOV-009060', array_column($rows[3]['candidates'], 'sku'));
        $this->assertSame(['none', []], [$rows[4]['status'], $rows[4]['candidates']]);
    }

    public function test_only_the_staff_workspace_can_recognise_a_list(): void
    {
        $this->postJson(route('b2b.workspace.paste'), ['text' => 'KOTLOV-1'])->assertUnauthorized();
        $client = User::factory()->create(['role' => 'client', 'is_active' => true]);
        $this->actingAs($client)->postJson(route('b2b.workspace.paste'), ['text' => 'KOTLOV-1'])->assertForbidden();
        $manager = User::factory()->create(['role' => 'manager', 'is_crm_user' => true, 'is_active' => true]);
        $this->actingAs($manager)->postJson(route('b2b.workspace.paste'), ['text' => ''])->assertUnprocessable();
        $this->get(route('b2b.catalog', ['workspace' => 1]))->assertOk()->assertSee('data-workspace-paste-open', false)->assertSee('b2b-paste-list.js', false);
    }
}
