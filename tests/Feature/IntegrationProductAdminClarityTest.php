<?php

namespace Tests\Feature;

use App\Filament\Resources\IntegrationProducts\IntegrationProductResource;
use App\Filament\Widgets\IntegrationCatalogIntegrityOverview;
use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IntegrationProductAdminClarityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_page_explains_full_staging_catalog_and_lists_only_positive_stock(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $source = IntegrationSource::query()->create([
            'code' => 'clarity-test-source',
            'name' => 'СанБизнесГруп',
        ]);

        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'available-product',
            'name' => 'Товар с положительным остатком',
            'stock_quantity' => 1,
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'unavailable-product',
            'name' => 'Товар без остатка',
            'stock_quantity' => 0,
        ]);

        $this->actingAs($admin)
            ->get(IntegrationProductResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Ниже показаны только товары с положительным остатком')
            ->assertSeeText('Повторный обмен обновляет их по ID 1С и не создаёт копии')
            ->assertSeeText('В наличии')
            ->assertSeeText('Товар с положительным остатком')
            ->assertSeeText('1 шт.')
            ->assertDontSeeText('Товар без остатка');

        Livewire::actingAs($admin)
            ->test(IntegrationCatalogIntegrityOverview::class)
            ->assertSee('Вся база интеграции')
            ->assertSee('Контроль дублей')
            ->assertSee('1 показано ниже')
            ->assertSee('1 без остатка скрыто');
    }

    public function test_ambiguous_match_shows_a_direct_candidate_link_and_review_action(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $source = IntegrationSource::query()->create([
            'code' => 'candidate-ui-source',
            'name' => 'Источник с вариантами',
        ]);
        $category = Category::query()->create([
            'name' => 'Категория кандидатов',
            'slug' => 'candidate-ui-category',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'sku' => 'CANDIDATE-UI',
            'name' => 'Карточка-кандидат D150',
            'slug' => 'candidate-ui-card',
            'category_id' => $category->id,
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'candidate-ui-item',
            'name' => 'Внешний товар с вариантами D150',
            'stock_quantity' => 1,
            'match_status' => 'ambiguous',
            'match_method' => 'fuzzy_name',
            'match_confidence' => 0.82,
            'candidates' => [[
                'product_id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'score' => 0.82,
            ]],
        ]);

        $this->actingAs($admin)
            ->get(IntegrationProductResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Карточка-кандидат D150')
            ->assertSeeText('кандидат · CANDIDATE-UI')
            ->assertSeeText('Сравнить варианты');
    }
}
