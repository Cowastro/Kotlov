<?php

namespace Tests\Feature;

use App\Filament\Pages\RequestSelection;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\CommercialDocuments\RequestLineSelection;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RequestSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_line_gets_an_active_product_in_stock_first_then_cheaper(): void
    {
        $category = Category::query()->create(['name' => 'Трубы ПП', 'slug' => 'request-selection-pipes', 'is_active' => true]);
        $cheapOrder = $this->product($category, 'Труба полипропиленовая 40 мм PN20 под заказ', 5.0, false);
        $stock = $this->product($category, 'Труба полипропиленовая 40 мм PN20 склад', 7.0, true);
        $stockExpensive = $this->product($category, 'Труба полипропиленовая 40 мм PN25 склад', 9.0, true);
        $this->product($category, 'Труба полипропиленовая 40 мм архив', 1.0, true, ['is_archived' => true]);
        $radiator = $this->product($category, 'Радиатор биметаллический 10 секций', 300.0, true);

        $lines = app(RequestLineSelection::class)->select([
            ['name' => 'Труба полипропиленовая 40', 'quantity' => 36.0, 'unit' => 'м'],
            ['name' => 'Радиатор биметаллический', 'quantity' => 12.0, 'unit' => 'шт'],
            ['name' => 'Клипса нестандартная', 'quantity' => 20.0, 'unit' => 'шт'],
        ], useAi: false);

        $this->assertSame($stock->id, $lines[0]['product_id']);
        $this->assertSame([$stock->id, $stockExpensive->id, $cheapOrder->id], array_keys($lines[0]['candidates']));
        $this->assertSame($radiator->id, $lines[1]['product_id']);
        $this->assertNull($lines[2]['product_id']);
        $this->assertNotSame('', $lines[2]['note']);
        $this->assertSame(36 * 7.0 + 12 * 300.0, RequestLineSelection::total($lines));
    }

    public function test_manager_creates_a_quote_from_the_chosen_lines(): void
    {
        $category = Category::query()->create(['name' => 'Радиаторы', 'slug' => 'request-selection-radiators', 'is_active' => true]);
        $first = $this->product($category, 'Радиатор биметаллический 10 секций', 300.0, true);
        $second = $this->product($category, 'Радиатор биметаллический 10 секций люкс', 350.0, true);
        $manager = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($manager);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->app->instance(RequestLineSelection::class, new class(app(\App\Services\AiContentEnricher::class), app(\App\Services\B2bSpecificationOcrService::class)) extends RequestLineSelection
        {
            public function recognize(?string $path, ?string $extension, string $text = ''): array
            {
                return ['method' => 'test', 'lines' => [['name' => 'Радиатор биметаллический 10', 'quantity' => 12.0, 'unit' => 'шт']]];
            }
        });

        Livewire::test(RequestSelection::class)
            ->set('text', 'Радиатор биметаллический 10 секций — 12 шт')
            ->call('select')
            ->assertSet('choice', [0 => $first->id])
            ->set('choice', [0 => $second->id])
            ->set('quantity', [0 => 12])
            ->assertSee('Радиатор биметаллический 10 секций люкс')
            ->call('createOffer')
            ->assertRedirect();

        $this->assertSame([$second->id], session('commercial_document_product_ids'));
        $this->assertEquals([$second->id => 12], session('commercial_document_product_quantities'));
        $this->assertNotSame($first->id, $second->id);
    }

    private function product(Category $category, string $name, float $price, bool $inStock, array $extra = []): Product
    {
        return Product::query()->create([
            'category_id' => $category->id, 'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name).'-'.uniqid(),
            'sku' => 'RS-'.uniqid(), 'price' => $price, 'is_active' => true, 'is_archived' => false,
            'in_stock' => $inStock, 'manual_stock_management' => true,
            'availability_status' => $inStock ? Product::AVAILABILITY_IN_STOCK : Product::AVAILABILITY_CHECK,
            ...$extra,
        ]);
    }
}
