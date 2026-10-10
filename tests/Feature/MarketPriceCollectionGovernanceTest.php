<?php

namespace Tests\Feature;

use App\Filament\Resources\MarketPriceCollectionRuns\MarketPriceCollectionRunResource;
use App\Models\Category;
use App\Models\MarketPriceObservation;
use App\Models\MarketPriceSource;
use App\Models\Product;
use App\Models\User;
use App\Services\Market\MarketPriceCollectionManager;
use App\Services\Market\MarketPriceCollector;
use App\Services\Market\MarketPublicHostGuard;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketPriceCollectionGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_collection_is_limited_journaled_and_scheduled(): void
    {
        $now = CarbonImmutable::parse('2026-10-10 12:00:00');
        $source = $this->source();
        $product = $this->product();
        $manager = app(MarketPriceCollectionManager::class);

        $run = $manager->start($source, 'scheduled', now: $now);
        $observation = $manager->record($run, $product, $this->offer(), $now);
        $finished = $manager->finish($run, $now->addSeconds(3));

        $this->assertNotNull($observation);
        $this->assertSame('success', $finished->status);
        $this->assertSame(1, $finished->requested_count);
        $this->assertSame(1, $finished->recorded_count);
        $this->assertSame(0, $finished->error_count);
        $this->assertSame(1, MarketPriceObservation::query()->count());
        $this->assertTrue($source->fresh()->last_collection_at->equalTo($now->addSeconds(3)));
        $this->assertTrue($source->fresh()->next_collection_at->equalTo($now->addMinutes(60)->addSeconds(3)));
    }

    public function test_remote_collection_without_explicit_authorization_is_blocked_and_explained(): void
    {
        $source = $this->source(['collection_authorized' => false]);

        $run = app(MarketPriceCollectionManager::class)->start(
            $source,
            'scheduled',
            now: CarbonImmutable::parse('2026-10-10 12:00:00'),
        );

        $this->assertSame('blocked', $run->status);
        $this->assertSame(1, $run->error_count);
        $this->assertSame('collection_not_authorized', $run->events[0]['code']);
        $this->assertNotNull($run->finished_at);
    }

    public function test_urls_outside_allowed_domain_or_path_are_rejected_and_journaled(): void
    {
        $source = $this->source();
        $product = $this->product();
        $manager = app(MarketPriceCollectionManager::class);
        $now = CarbonImmutable::parse('2026-10-10 12:00:00');
        $run = $manager->start($source, 'api', now: $now);

        $this->assertNull($manager->record($run, $product, $this->offer('https://other.example.by/catalog/item'), $now));
        $this->assertNull($manager->record($run, $product, $this->offer('https://prices.example.by/private/item'), $now));

        $finished = $manager->finish($run, $now->addSecond());
        $this->assertSame('failed', $finished->status);
        $this->assertSame(0, $finished->requested_count);
        $this->assertSame(2, $finished->skipped_count);
        $this->assertSame(2, $finished->error_count);
        $this->assertSame(['host_not_allowed', 'path_not_allowed'], collect($finished->events)->pluck('code')->all());
        $this->assertSame(0, MarketPriceObservation::query()->count());
    }

    public function test_run_limit_and_not_due_schedule_are_enforced(): void
    {
        $now = CarbonImmutable::parse('2026-10-10 12:00:00');
        $source = $this->source(['max_requests_per_run' => 1]);
        $product = $this->product();
        $manager = app(MarketPriceCollectionManager::class);
        $run = $manager->start($source, 'scheduled', now: $now);

        $this->assertNotNull($manager->record($run, $product, $this->offer(), $now));
        $this->assertNull($manager->record($run, $product, $this->offer('https://prices.example.by/catalog/second'), $now));
        $finished = $manager->finish($run, $now->addSecond());

        $this->assertSame('warning', $finished->status);
        $this->assertSame('run_limit_reached', collect($finished->events)->last()['code']);

        $blocked = $manager->start($source->fresh(), 'scheduled', now: $now->addMinutes(5));
        $this->assertSame('blocked', $blocked->status);
        $this->assertSame('not_due', $blocked->events[0]['code']);
    }

    public function test_daily_limit_blocks_the_next_run_after_the_quota_is_used(): void
    {
        $now = CarbonImmutable::parse('2026-10-10 12:00:00');
        $source = $this->source(['max_requests_per_day' => 1]);
        $manager = app(MarketPriceCollectionManager::class);
        $first = $manager->start($source, 'api', now: $now);

        $this->assertNotNull($manager->record($first, $this->product(), $this->offer(), $now));
        $manager->finish($first, $now->addSecond());

        $blocked = $manager->start($source->fresh(), 'api', now: $now->addMinutes(10));
        $this->assertSame('blocked', $blocked->status);
        $this->assertSame('daily_limit_reached', $blocked->events[0]['code']);
    }

    public function test_manager_can_read_collection_journal_but_cannot_change_market_sources(): void
    {
        $source = $this->source(['name' => 'Контролируемый источник']);
        app(MarketPriceCollectionManager::class)->start(
            $source,
            'scheduled',
            now: CarbonImmutable::parse('2026-10-10 12:00:00'),
        );
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);

        $this->actingAs($manager);
        $this->assertTrue(MarketPriceCollectionRunResource::canViewAny());
        $this->assertFalse(MarketPriceCollectionRunResource::canCreate());
        $this->get(MarketPriceCollectionRunResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Журнал сбора рынка')
            ->assertSeeText('Контролируемый источник');
    }

    public function test_json_feed_adapter_updates_only_existing_exact_skus_as_unconfirmed_evidence(): void
    {
        $product = $this->product();
        $source = $this->source([
            'adapter_key' => 'json_feed_v1',
            'collection_settings' => [
                'endpoint_path' => '/catalog/feed.json',
                'items_path' => 'data.items',
                'max_items_per_run' => 100,
            ],
        ]);
        $this->mock(MarketPublicHostGuard::class)
            ->shouldReceive('allows')->once()->andReturnTrue();
        Http::fake([
            'https://prices.example.by/catalog/feed.json' => Http::response([
                'data' => ['items' => [
                    [
                        'product_sku' => $product->sku,
                        'url' => 'https://prices.example.by/catalog/item',
                        'name' => 'Рыночное предложение',
                        'price' => 99.90,
                        'currency' => 'BYN',
                        'availability_status' => 'in_stock',
                        'observed_at' => '2026-10-10 12:00:00',
                    ],
                    [
                        'product_sku' => 'UNKNOWN-SKU',
                        'url' => 'https://prices.example.by/catalog/unknown',
                        'price' => 10,
                    ],
                ]],
            ]),
        ]);

        $run = app(MarketPriceCollector::class)->collect($source, 'api');

        $this->assertSame('warning', $run->status);
        $this->assertSame(1, $run->requested_count);
        $this->assertSame(1, $run->recorded_count);
        $this->assertSame(1, $run->skipped_count);
        $this->assertSame(1, $run->warning_count);
        $observation = MarketPriceObservation::query()->sole();
        $this->assertSame($product->id, $observation->product_id);
        $this->assertFalse($observation->is_confirmed);
        $this->assertFalse($observation->is_comparable);
        $this->assertSame(['requires_human_confirmation'], $observation->validation_flags);
        $this->assertSame('product_not_found', collect($run->events)->last()['code']);
    }

    public function test_scrape_adapter_stops_before_feed_when_robots_disallows_endpoint(): void
    {
        $source = $this->source([
            'collection_method' => 'scrape',
            'adapter_key' => 'json_feed_v1',
            'collection_settings' => ['endpoint_path' => '/catalog/feed.json'],
        ]);
        $this->mock(MarketPublicHostGuard::class)
            ->shouldReceive('allows')->once()->andReturnTrue();
        Http::fake([
            'https://prices.example.by/robots.txt' => Http::response("User-agent: *\nDisallow: /catalog/", 200),
            '*' => Http::response(['items' => []]),
        ]);

        $run = app(MarketPriceCollector::class)->collect($source, 'api');

        $this->assertSame('failed', $run->status);
        $this->assertSame(1, $run->requested_count);
        $this->assertSame(0, $run->recorded_count);
        $this->assertSame('robots_disallowed', collect($run->events)->last()['code']);
        Http::assertSentCount(1);
    }

    public function test_feed_redirect_is_not_followed_and_is_recorded_as_a_failure(): void
    {
        $source = $this->source([
            'adapter_key' => 'json_feed_v1',
            'collection_settings' => ['endpoint_path' => '/catalog/feed.json'],
        ]);
        $this->mock(MarketPublicHostGuard::class)
            ->shouldReceive('allows')->once()->andReturnTrue();
        Http::fake([
            'https://prices.example.by/catalog/feed.json' => Http::response('', 302, [
                'Location' => 'http://127.0.0.1/private',
            ]),
        ]);

        $run = app(MarketPriceCollector::class)->collect($source, 'api');

        $this->assertSame('failed', $run->status);
        $this->assertSame(1, $run->requested_count);
        $this->assertSame('redirect_blocked', collect($run->events)->last()['code']);
        Http::assertSentCount(1);
    }

    public function test_scheduled_command_is_a_no_op_without_due_authorized_sources(): void
    {
        $this->artisan('market:collect-prices')
            ->expectsOutput('Источников: 0 · успешно: 0 · с предупреждениями: 0 · ошибок/блокировок: 0')
            ->assertSuccessful();
    }

    private function source(array $overrides = []): MarketPriceSource
    {
        return MarketPriceSource::query()->create(array_merge([
            'code' => 'prices-example',
            'name' => 'Цены example.by',
            'kind' => 'competitor',
            'collection_method' => 'api',
            'base_url' => 'https://prices.example.by',
            'currency' => 'BYN',
            'region' => 'Беларусь',
            'freshness_hours' => 48,
            'collection_interval_minutes' => 60,
            'max_requests_per_run' => 10,
            'max_requests_per_day' => 100,
            'allowed_path_prefixes' => ['/catalog/'],
            'respect_robots_txt' => true,
            'collection_authorized' => true,
            'minimum_match_confidence' => 0.85,
            'is_active' => true,
        ], $overrides));
    }

    private function product(): Product
    {
        $category = Category::query()->create([
            'name' => 'Тестовая категория рынка',
            'slug' => 'market-governance-category',
            'parent_id' => 0,
        ]);

        return Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Тестовый товар рынка',
            'slug' => 'market-governance-product',
            'sku' => 'MARKET-GOV-1',
            'price' => 120,
            'currency' => 'BYN',
        ]);
    }

    private function offer(string $url = 'https://prices.example.by/catalog/item'): array
    {
        return [
            'url' => $url,
            'external_name' => 'Сопоставимый товар',
            'observed_price' => 100,
            'currency' => 'BYN',
            'exchange_rate_to_byn' => 1,
            'availability_status' => 'in_stock',
            'match_method' => 'exact_model',
            'match_confidence' => 0.95,
            'is_confirmed' => true,
            'is_comparable' => true,
            'observed_at' => '2026-10-10 12:00:00',
        ];
    }
}
