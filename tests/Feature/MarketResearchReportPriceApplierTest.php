<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\MarketResearchReport;
use App\Models\Product;
use App\Models\User;
use App\Services\Market\MarketResearchReportPriceApplier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketResearchReportPriceApplierTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_apply_a_lower_report_price_to_the_site_and_the_change_is_audited(): void
    {
        [$product, $report] = $this->fixture(8487, 8063);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $result = app(MarketResearchReportPriceApplier::class)->apply(
            $report,
            $admin,
            'Цена подтверждена по актуальному анализу рынка',
            '127.0.0.1',
            'Feature test',
        );

        $this->assertSame('8063.00', $result['product']->price);
        $this->assertFalse($result['product']->requires_price_confirmation);
        $this->assertSame('price_applied', $result['report']->decision);
        $this->assertSame('8063.00', $result['report']->price_after);
        $this->assertSame('market_research_report_price_applied', $result['audit']->action);
        $this->assertSame(8487.0, (float) $result['audit']->old_values['price']);
        $this->assertSame(8063.0, (float) $result['audit']->new_values['price']);
    }

    public function test_admin_can_apply_a_higher_report_price_to_the_site(): void
    {
        [$product, $report] = $this->fixture(8000, 8500);
        $product->update(['price_old' => 8200]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $result = app(MarketResearchReportPriceApplier::class)->apply(
            $report,
            $admin,
            'Цена повышена по актуальному анализу рынка',
        );

        $this->assertSame('8500.00', $result['product']->price);
        $this->assertNull($result['product']->price_old);
    }

    public function test_manager_cannot_apply_a_report_price(): void
    {
        [$product, $report] = $this->fixture(8487, 8063);
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);

        try {
            app(MarketResearchReportPriceApplier::class)->apply(
                $report,
                $manager,
                'Попытка применить цену менеджером',
            );
            $this->fail('Manager unexpectedly changed a product price.');
        } catch (AuthorizationException) {
            $this->assertSame('8487.00', $product->fresh()->price);
            $this->assertSame(0, ActivityLog::query()->count());
        }
    }

    /** @return array{Product, MarketResearchReport} */
    private function fixture(float $sitePrice, float $recommendedPrice): array
    {
        $category = Category::query()->create([
            'name' => 'Анализ цены',
            'slug' => 'report-price-test-'.str_replace('.', '-', (string) $sitePrice),
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Товар с анализом рынка',
            'slug' => 'report-price-product-'.str_replace('.', '-', (string) $sitePrice),
            'sku' => 'REPORT-PRICE-'.(int) $sitePrice,
            'price' => $sitePrice,
            'currency' => 'BYN',
            'requires_price_confirmation' => true,
            'is_active' => true,
        ]);
        $report = MarketResearchReport::query()->create([
            'product_id' => $product->id,
            'batch_code' => 'report-price-'.(int) $sitePrice.'-'.(int) $recommendedPrice,
            'review_reason' => 'Подтверждённый анализ рынка.',
            'confidence' => 'high',
            'decision' => 'consultation_hold',
            'price_before' => $sitePrice,
            'recommended_price' => $recommendedPrice,
            'price_after' => $sitePrice,
            'requires_price_confirmation' => true,
            'applied_at' => now(),
        ]);

        return [$product, $report];
    }
}
