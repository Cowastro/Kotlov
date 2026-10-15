<?php

namespace Tests\Feature;

use App\Filament\Pages\TechnicalSelection;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\AiContentEnricher;
use App\Services\TechnicalProductSelection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class TechnicalProductSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_expands_a_task_and_only_returns_real_candidates_with_database_prices(): void
    {
        $product = $this->product('Насосная станция TEST 24', 'pump');
        $archived = $this->product('Насосная станция архив', 'archive', ['is_archived' => true]);
        $supplier = Supplier::query()->create(['name' => 'Тестовый поставщик', 'code' => 'technical-test', 'is_active' => true]);
        $offer = SupplierProduct::query()->create([
            'supplier_id' => $supplier->id, 'supplier_name' => 'Гидрофор TEST',
            'supplier_article' => 'HYDRO-1', 'price_byn' => 240, 'stock_quantity' => 2,
            'last_synced_at' => now()->subDays(3),
        ]);
        $ai = $this->mock(AiContentEnricher::class);
        $ai->shouldReceive('isAvailable')->andReturn(true);
        $ai->shouldReceive('complete')->once()->ordered()->andReturn(json_encode([
            'task' => 'Подбор насосной станции', 'queries' => ['насосная станция', 'гидрофор'],
            'requirements' => ['220 В', '24 л'], 'questions' => ['Уточнить рабочую точку'],
            'procurement_request' => 'Нужна станция 24 л, 220 В.',
        ]));
        $ai->shouldReceive('complete')->once()->ordered()->withArgs(function ($prompt, $tokens) use ($product, $archived, $offer) {
            $this->assertStringContainsString('product:'.$product->id, $prompt);
            $this->assertStringNotContainsString('product:'.$archived->id, $prompt);
            $this->assertStringContainsString('supplier:'.$offer->id, $prompt);

            return $tokens === 3000;
        })->andReturn(json_encode(['summary' => 'Нужна проверка кривой.', 'matches' => [
            ['key' => 'product:'.$product->id, 'price' => 1, 'confirmed' => ['Бак 24 л'], 'check' => ['Кривая Q/H']],
            ['key' => 'product:999999', 'name' => 'Вымышленный товар'],
            ['key' => 'product:'.$archived->id],
            ['key' => 'supplier:'.$offer->id, 'fit' => 'alternative'],
            ['key' => 'product:'.$product->id],
        ]]));

        $result = app(TechnicalProductSelection::class)->search('Станция для водоснабжения дома 24 л 220 В');
        $this->assertCount(2, $result['matches']);
        $this->assertSame(500.0, $result['matches'][0]['price']);
        $this->assertSame('Насосная станция TEST 24', $result['matches'][0]['name']);
        $this->assertSame(['Кривая Q/H'], $result['matches'][0]['check']);
        $this->assertNull($result['matches'][1]['product_id']);
        $this->assertSame(240.0, $result['matches'][1]['supplier_offers'][0]['purchase_byn']);
        $this->assertFalse($result['matches'][1]['supplier_offers'][0]['fresh_positive_stock']);
    }

    public function test_no_candidates_produces_a_procurement_request_without_inventing_products(): void
    {
        $ai = $this->mock(AiContentEnricher::class);
        $ai->shouldReceive('isAvailable')->andReturn(true);
        $ai->shouldReceive('complete')->once()->andReturn('{"task":"Насос","queries":["гидрофор"],"procurement_request":"Подберите гидрофор"}');
        $result = app(TechnicalProductSelection::class)->search('Нужен гидрофор');
        $this->assertSame([], $result['matches']);
        $this->assertSame(0, $result['candidate_count']);
        $this->assertSame('Подберите гидрофор', $result['procurement_request']);
    }

    public function test_malformed_api_response_is_an_explicit_error(): void
    {
        $ai = $this->mock(AiContentEnricher::class);
        $ai->shouldReceive('isAvailable')->andReturn(true);
        $ai->shouldReceive('complete')->once()->andReturn('not JSON');
        $this->expectException(RuntimeException::class);
        app(TechnicalProductSelection::class)->search('Нужен насос');
    }

    public function test_manager_can_search_and_only_selected_result_products_reach_the_offer(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'manager']));
        $product = $this->product('Станция', 'station');
        $other = $this->product('Другой товар', 'other');
        $result = [
            'request' => 'Нужна станция', 'task' => 'Станция', 'summary' => 'Предварительный подбор',
            'requirements' => [], 'questions' => [], 'queries' => ['станция'],
            'procurement_request' => 'Запрос', 'candidate_count' => 1, 'searched_at' => '15.10.2026',
            'matches' => [[
                'key' => 'product:'.$product->id, 'product_id' => $product->id, 'name' => $product->name,
                'sku' => $product->sku, 'availability' => 'Уточнить', 'price' => 500, 'currency' => 'BYN',
                'fit' => 'Кандидат', 'confirmed' => [], 'differences' => [], 'check' => [], 'supplier_offers' => [],
            ]],
        ];
        $this->mock(TechnicalProductSelection::class)->shouldReceive('search')->once()->andReturn($result);
        Livewire::test(TechnicalSelection::class)->set('prompt', 'Нужна станция')->call('search')
            ->assertSee('Предварительный подбор')->assertSee('Создать КП из выбранных')
            ->set('selected', [$product->id, $other->id])->call('createOffer')
            ->assertRedirect();
        $this->assertSame([$product->id], session('commercial_document_product_ids'));
        $this->assertSame([$product->id => 1], session('commercial_document_product_quantities'));
    }

    public function test_client_cannot_access_selection(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'client']));
        $this->get('/admin/technical-selection')->assertForbidden();
    }

    private function product(string $name, string $slug, array $extra = []): Product
    {
        $category = Category::query()->firstOrCreate(['slug' => 'selection-pumps'], ['name' => 'Насосы', 'is_active' => true]);

        return Product::query()->create([
            'category_id' => $category->id, 'name' => $name, 'slug' => $slug, 'sku' => 'TECH-'.$slug,
            'price' => 500, 'currency' => 'BYN', 'is_active' => true, 'is_archived' => false,
            'specs' => ['Бак' => '24 л', 'Питание' => '220 В'], ...$extra,
        ]);
    }
}
