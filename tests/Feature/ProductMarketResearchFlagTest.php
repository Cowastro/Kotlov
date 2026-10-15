<?php

namespace Tests\Feature;

use App\Filament\Resources\MarketResearchReports\MarketResearchReportResource;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Category;
use App\Models\MarketResearchReport;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductMarketResearchFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_high_market_price_flag_links_to_the_products_research_archive(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $category = Category::query()->create([
            'name' => 'Рыночные флажки',
            'slug' => 'market-price-flags',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $high = $this->product($category, 'HIGH-MARKET-PRICE', 1500);
        $normal = $this->product($category, 'NORMAL-MARKET-PRICE', 900);
        $withoutResearch = $this->product($category, 'WITHOUT-MARKET-RESEARCH', 1200);

        $this->report($high, 'high-price-report', 1000);
        $this->report($normal, 'normal-price-report', 1000);

        $archiveUrl = MarketResearchReportResource::getUrl('index', [
            'filters' => ['product_id' => ['value' => $high->id]],
        ]);

        Livewire::actingAs($admin)
            ->test(ListProducts::class)
            ->assertTableActionVisible('review_high_market_price', $high)
            ->assertTableActionHasIcon('review_high_market_price', 'heroicon-o-flag', $high)
            ->assertTableActionHasColor('review_high_market_price', 'danger', $high)
            ->assertTableActionHasUrl('review_high_market_price', $archiveUrl, $high)
            ->assertTableActionHidden('review_high_market_price', $normal)
            ->assertTableActionHidden('review_high_market_price', $withoutResearch);
    }

    private function product(Category $category, string $sku, float $price): Product
    {
        return Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Товар '.$sku,
            'slug' => strtolower($sku),
            'sku' => $sku,
            'price' => $price,
            'currency' => 'BYN',
            'is_active' => true,
            'is_archived' => false,
        ]);
    }

    private function report(Product $product, string $batchCode, float $recommendedPrice): MarketResearchReport
    {
        return MarketResearchReport::query()->create([
            'product_id' => $product->id,
            'batch_code' => $batchCode,
            'review_reason' => 'Цена проверена по рынку.',
            'decision' => 'consultation_hold',
            'decision_reason' => 'Ожидает решения администратора.',
            'price_before' => $product->price,
            'recommended_price' => $recommendedPrice,
            'price_after' => $product->price,
            'requires_price_confirmation' => true,
            'researched_at' => now(),
            'applied_at' => now(),
        ]);
    }
}
