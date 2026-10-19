<?php

namespace Tests\Feature;

use App\Models\DealByOrderInbox;
use App\Models\MarketplaceSyncCursor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DealByOrderCaptureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.deal_by', [
            'base_url' => 'https://my.deal.test/api/v1',
            'token' => 'secret-test-token',
            'connect_timeout' => 1,
            'timeout' => 1,
            'orders_capture_enabled' => true,
            'orders_initial_lookback_days' => 30,
            'orders_overlap_minutes' => 15,
        ]);
        Carbon::setTestNow('2026-10-19 08:00:00 UTC');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_it_captures_an_order_without_creating_an_operational_order(): void
    {
        Http::fake([
            'https://my.deal.test/api/v1/orders/list*' => Http::response([
                'orders' => [$this->orderPayload()],
            ]),
        ]);

        $this->artisan('deal:pull-orders')->assertSuccessful();

        $this->assertDatabaseCount('deal_by_order_inboxes', 1);
        $this->assertDatabaseCount('orders', 0);

        $inbox = DealByOrderInbox::query()->sole();
        $this->assertSame('987654', $inbox->remote_order_id);
        $this->assertSame('pending', $inbox->remote_status);
        $this->assertSame('buyer@example.test', $inbox->payload['email']);
        $this->assertSame(DealByOrderInbox::STATUS_PENDING, $inbox->processing_status);
        $this->assertNotNull(MarketplaceSyncCursor::query()->where('channel', 'deal_by')->where('stream', 'orders')->first()?->last_succeeded_at);

        $rawPayload = DB::table('deal_by_order_inboxes')->value('payload');
        $this->assertIsString($rawPayload);
        $this->assertStringNotContainsString('buyer@example.test', $rawPayload);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->hasHeader('Authorization', 'Bearer secret-test-token')
            && $request['limit'] === 100
            && $request['sort_dir'] === 'asc'
            && $request['last_modified_from'] === '2026-09-19 08:00:00'
            && $request['last_modified_to'] === '2026-10-19 08:00:00');
    }

    public function test_repeated_capture_updates_the_same_inbox_record(): void
    {
        Http::fakeSequence('https://my.deal.test/api/v1/orders/list*')
            ->push(['orders' => [$this->orderPayload()]])
            ->push(['orders' => [$this->orderPayload(['status' => 'received', 'status_name' => 'Принят'])]]);

        $this->artisan('deal:pull-orders')->assertSuccessful();
        Carbon::setTestNow('2026-10-19 08:03:00 UTC');
        $this->artisan('deal:pull-orders')->assertSuccessful();

        $this->assertDatabaseCount('deal_by_order_inboxes', 1);
        $inbox = DealByOrderInbox::query()->sole();
        $this->assertSame('received', $inbox->remote_status);
        $this->assertSame('received', $inbox->payload['status']);
        $this->assertNull($inbox->order_id);

        $requests = Http::recorded();
        $this->assertSame('2026-10-19 07:45:00', $requests[1][0]['last_modified_from']);
    }

    public function test_disabled_capture_does_not_call_deal_by(): void
    {
        config()->set('services.deal_by.orders_capture_enabled', false);
        Http::preventStrayRequests();

        $this->artisan('deal:pull-orders')
            ->expectsOutputToContain('Приём заказов Deal.by выключен')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_api_failure_is_recorded_without_advancing_the_cursor(): void
    {
        Http::fake([
            'https://my.deal.test/api/v1/orders/list*' => Http::response(['error' => 'temporarily unavailable'], 503),
        ]);

        $this->artisan('deal:pull-orders')->assertFailed();

        $cursor = MarketplaceSyncCursor::query()->where('channel', 'deal_by')->where('stream', 'orders')->sole();
        $this->assertNull($cursor->cursor_at);
        $this->assertNull($cursor->last_succeeded_at);
        $this->assertNotNull($cursor->last_failed_at);
        $this->assertNotNull($cursor->last_error);
        $this->assertDatabaseCount('deal_by_order_inboxes', 0);
    }

    public function test_it_paginates_without_losing_orders(): void
    {
        $firstPage = [];
        for ($id = 1; $id <= 100; $id++) {
            $firstPage[] = $this->orderPayload(['id' => $id]);
        }

        Http::fakeSequence('https://my.deal.test/api/v1/orders/list*')
            ->push(['orders' => $firstPage])
            ->push(['orders' => [$this->orderPayload(['id' => 101])]]);

        $this->artisan('deal:pull-orders')->assertSuccessful();

        $this->assertDatabaseCount('deal_by_order_inboxes', 101);
        Http::assertSentCount(2);
        $requests = Http::recorded();
        $this->assertFalse(isset($requests[0][0]['last_id']));
        $this->assertSame(100, $requests[1][0]['last_id']);
    }

    public function test_invalid_response_does_not_advance_the_cursor(): void
    {
        Http::fake([
            'https://my.deal.test/api/v1/orders/list*' => Http::response(['unexpected' => []]),
        ]);

        $this->artisan('deal:pull-orders')->assertFailed();

        $cursor = MarketplaceSyncCursor::query()->where('channel', 'deal_by')->where('stream', 'orders')->sole();
        $this->assertNull($cursor->cursor_at);
        $this->assertNotNull($cursor->last_failed_at);
        $this->assertStringContainsString('без списка заказов', $cursor->last_error);
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function orderPayload(array $overrides = []): array
    {
        return array_merge([
            'id' => 987654,
            'date_created' => '2026-10-19T07:45:00Z',
            'date_modified' => '2026-10-19T07:50:00Z',
            'client_first_name' => 'Покупатель',
            'email' => 'buyer@example.test',
            'phone' => '+375290000000',
            'price' => 125.50,
            'status' => 'pending',
            'status_name' => 'Новый',
            'products' => [[
                'id' => 111,
                'sku' => 'test-product',
                'name' => 'Тестовый товар',
                'price' => 125.50,
                'quantity' => 1,
            ]],
        ], $overrides);
    }
}
