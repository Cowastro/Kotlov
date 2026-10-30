<?php

namespace Tests\Feature;

use App\Services\Market\MarketWebResearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MarketResearchDailyLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_daily_limit_of_background_searches_survives_a_cache_clear_and_resets_next_day(): void
    {
        config()->set('services.ai.market_research_daily_background_limit', 2);
        Carbon::setTestNow('2026-10-30 10:00:00');

        $this->assertTrue(MarketWebResearchService::takeBackgroundSearch());
        Cache::flush(); // a deploy runs optimize:clear
        $this->assertTrue(MarketWebResearchService::takeBackgroundSearch());
        Cache::flush();
        $this->assertFalse(MarketWebResearchService::takeBackgroundSearch());
        $this->assertSame(2, MarketWebResearchService::backgroundSearchesToday());

        Carbon::setTestNow('2026-10-31 00:05:00');
        $this->assertSame(0, MarketWebResearchService::backgroundSearchesToday());
        $this->assertTrue(MarketWebResearchService::takeBackgroundSearch());
        Carbon::setTestNow();
    }
}
