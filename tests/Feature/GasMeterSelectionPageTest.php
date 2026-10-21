<?php

namespace Tests\Feature;

use App\Support\Catalog\GasMeterSelectionGuide;
use App\Support\KnowledgeBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GasMeterSelectionPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_selector_is_listed_in_knowledge_base_and_opens(): void
    {
        $this->get(KnowledgeBase::URL)
            ->assertOk()
            ->assertSeeText('Как подобрать газовый счётчик')
            ->assertSee(GasMeterSelectionGuide::PAGE_URL, false);

        $this->get(GasMeterSelectionGuide::PAGE_URL)
            ->assertOk()
            ->assertSeeText('Как подобрать газовый счётчик без сложных терминов')
            ->assertSeeText('восемь понятных типов счётчиков БелОМО')
            ->assertSeeText('Подобрать счётчик')
            ->assertSeeText('СГД-3Т-1-1-G4')
            ->assertSee('belomo-kata-skat-oboznacheniya#variants', false)
            ->assertSee('data-gm-picker', false)
            ->assertSee('/schetchiki-gaza', false);
    }

    public function test_gas_meter_category_promotes_fast_selector_above_products(): void
    {
        $template = file_get_contents(resource_path('views/pages/catalog.blade.php'));

        $this->assertStringContainsString('Не знаете, какой счётчик выбрать?', $template);
        $this->assertStringContainsString('Подобрать счётчик за 1 минуту', $template);
        $this->assertStringContainsString('GasMeterSelectionGuide::PAGE_URL', $template);
        $this->assertLessThan(
            strpos($template, 'id="catalog-products"'),
            strpos($template, 'gas-meter-selector-cta-title'),
        );
    }

    public function test_selector_allows_an_estimated_flow_range_and_fallback_models(): void
    {
        $template = file_get_contents(resource_path('views/pages/knowledge-base-gas-meter-selection.blade.php'));

        $this->assertStringContainsString('Примерный диапазон расхода', $template);
        $this->assertStringContainsString('До 6 м³/ч — котёл и плита', $template);
        $this->assertStringContainsString('Примерно подходит по расходу', $template);
        $this->assertStringContainsString('Показываем ближайшие модели по расходу', $template);
        $this->assertStringContainsString('СГМН-1-1-1-G4', $template);
        $this->assertStringContainsString('Популярный G4 · левый · L=250', $template);
    }

    public function test_selector_explains_thermal_compensation_and_contains_all_g4_executions(): void
    {
        $response = $this->get(GasMeterSelectionGuide::PAGE_URL);

        $response->assertOk()
            ->assertSeeText('Нужен термокомпенсатор?')
            ->assertSeeText('Да, с термокомпенсатором')
            ->assertSeeText('Нет, без термокомпенсатора')
            ->assertSee('8181.00.00.000-20', false)
            ->assertSee('8181.00.00.000-21', false)
            ->assertSee('8336.00.00.000-06', false)
            ->assertSee('8336.00.00.000-07', false);
    }
}
