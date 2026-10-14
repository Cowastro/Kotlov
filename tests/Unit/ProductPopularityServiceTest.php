<?php

namespace Tests\Unit;

use App\Services\ProductPopularityService;
use PHPUnit\Framework\TestCase;

class ProductPopularityServiceTest extends TestCase
{
    public function test_recent_interest_outweighs_old_accumulated_views(): void
    {
        $service = new ProductPopularityService;

        $freshInterest = $service->score(0, 0, 20, 30, 50);
        $oldTraffic = $service->score(0, 0, 0, 0, 100000);

        $this->assertGreaterThan($oldTraffic, $freshInterest);
    }

    public function test_rating_needs_review_confidence_to_dominate_catalog(): void
    {
        $service = new ProductPopularityService;

        $trustedRating = $service->score(4.8, 8, 10, 20, 100);
        $unconfirmedRating = $service->score(5, 0, 10, 20, 100);

        $this->assertGreaterThan($unconfirmedRating, $trustedRating);
    }
}
