<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MarketResearchReport;
use App\Models\Product;
use App\Models\User;
use App\Services\Market\MarketResearchReportPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketResearchReportPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_archive_report_has_readable_view_and_manager_pdf(): void
    {
        $category = Category::query()->create([
            'name' => 'Котлы для отчёта',
            'slug' => 'market-report-pdf-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Котёл KOTLOV PRO КСТБ 95-1Т',
            'slug' => 'market-report-pdf-product',
            'sku' => 'PDF-REPORT-001',
            'price' => 8490,
            'currency' => 'BYN',
            'is_active' => true,
        ]);
        $report = MarketResearchReport::query()->create([
            'product_id' => $product->id,
            'batch_code' => 'market-report-pdf-001',
            'review_reason' => 'Проверка цены перед коммерческим предложением.',
            'summary' => 'Цена находится в верхней части рынка.',
            'verdict' => 'Сохранить цену и подтвердить комплектацию.',
            'confidence' => 'medium',
            'decision' => 'consultation_hold',
            'decision_reason' => 'Нужна проверка комплектации менеджером.',
            'price_before' => 8490,
            'recommended_price' => 7720,
            'price_after' => 8490,
            'requires_price_confirmation' => true,
            'search_queries' => ['КСТБ 95-1Т цена'],
            'rejected_candidates' => [[
                'title' => 'Двухконтурная версия',
                'url' => 'https://example.by/rejected',
                'reason' => 'Другая комплектация.',
            ]],
            'observations_snapshot' => [[
                'seller' => 'Тестовый продавец',
                'title' => 'КСТБ 95-1Т',
                'url' => 'https://example.by/product',
                'price' => 8490,
                'price_byn' => 8490,
                'currency' => 'BYN',
                'availability' => 'В наличии',
                'match' => 'exact',
                'evidence' => 'Совпадает модель.',
            ]],
            'research_payload' => ['status' => 'ready'],
            'research_model' => 'test-model',
            'researched_at' => now(),
            'applied_at' => now(),
        ]);

        $presentation = app(MarketResearchReportPresenter::class)->present($report);
        $html = view('filament.market.research-report-details', [
            'report' => $report,
            ...$presentation,
        ])->render();

        $this->assertStringContainsString('mr-offers', $html);
        $this->assertStringContainsString('Открыть PDF', $html);
        $this->assertStringContainsString('Тестовый продавец', $html);
        $this->assertStringContainsString('Зафиксированное решение', $html);
        $this->assertStringNotContainsString('<pre', $html);

        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $response = $this->actingAs($manager)->get(route('admin.market-research-reports.pdf', $report));

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertGreaterThan(2000, strlen($content));

        $customer = User::factory()->create(['role' => 'client', 'is_active' => true]);
        $this->actingAs($customer)
            ->get(route('admin.market-research-reports.pdf', $report))
            ->assertForbidden();
    }
}
