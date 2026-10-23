<?php

namespace Tests\Feature;

use App\Events\NewOrderCreated;
use App\Models\DealByOrderInbox;
use App\Models\MarketplaceSyncCursor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
        Event::fake([NewOrderCreated::class]);
        Carbon::setTestNow('2026-10-19 08:00:00 UTC');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_it_captures_and_projects_an_operational_order(): void
    {
        Http::fake([
            'https://my.deal.test/api/v1/orders/list*' => Http::response([
                'orders' => [$this->orderPayload()],
            ]),
            'https://my.deal.test/api/v1/orders/set_status' => Http::response([
                'processed_ids' => [987654],
            ]),
        ]);

        $this->artisan('deal:pull-orders')->assertSuccessful();

        $this->assertDatabaseCount('deal_by_order_inboxes', 1);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);

        $inbox = DealByOrderInbox::query()->sole();
        $this->assertSame('987654', $inbox->remote_order_id);
        $this->assertSame('received', $inbox->remote_status);
        $this->assertSame('received', $inbox->last_pushed_status);
        $this->assertSame('buyer@example.test', $inbox->payload['email']);
        $this->assertSame(DealByOrderInbox::STATUS_PROCESSED, $inbox->processing_status);
        $this->assertNotNull($inbox->order_id);
        $this->assertDatabaseHas('orders', [
            'id' => $inbox->order_id,
            'number' => 'DBY-987654',
            'customer_email' => 'buyer@example.test',
            'status' => 'new',
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $inbox->order_id,
            'product_sku' => 'test-product',
            'price' => 125.50,
            'quantity' => 1,
        ]);
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
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://my.deal.test/api/v1/orders/set_status'
            && $request['status'] === 'received'
            && $request['ids'] === ['987654']);
        Event::assertDispatched(NewOrderCreated::class, 1);
    }

    public function test_repeated_capture_updates_the_same_inbox_record(): void
    {
        Http::fake([
            'https://my.deal.test/api/v1/orders/list*' => Http::sequence()
                ->push(['orders' => [$this->orderPayload()]])
                ->push(['orders' => [$this->orderPayload(['status' => 'received', 'status_name' => 'Принят'])]]),
            'https://my.deal.test/api/v1/orders/set_status' => Http::response([
                'processed_ids' => [987654],
            ]),
        ]);

        $this->artisan('deal:pull-orders')->assertSuccessful();
        Carbon::setTestNow('2026-10-19 08:03:00 UTC');
        $this->artisan('deal:pull-orders')->assertSuccessful();

        $this->assertDatabaseCount('deal_by_order_inboxes', 1);
        $this->assertDatabaseCount('orders', 1);
        $inbox = DealByOrderInbox::query()->sole();
        $this->assertSame('received', $inbox->remote_status);
        $this->assertSame('received', $inbox->payload['status']);
        $this->assertNotNull($inbox->order_id);

        $requests = Http::recorded();
        $listRequests = collect($requests)->filter(fn (array $record): bool => $record[0]->method() === 'GET')->values();
        $this->assertSame('2026-10-19 07:45:00', $listRequests[1][0]['last_modified_from']);
        Event::assertDispatched(NewOrderCreated::class, 1);
    }

    public function test_it_parses_deal_by_prices_with_currency_and_repairs_existing_order(): void
    {
        $formattedPayload = $this->orderPayload([
            'price' => '505,40 BYN',
            'full_price' => '515.40 BYN',
            'delivery_cost' => '10 руб.',
            'products' => [[
                'id' => 111,
                'sku' => 'test-product',
                'name' => 'Тестовый товар',
                'price' => '505,40 BYN',
                'total_price' => '505.40 BYN',
                'quantity' => 1,
            ]],
        ]);

        Http::fake([
            'https://my.deal.test/api/v1/orders/list*' => Http::sequence()
                ->push(['orders' => [$this->orderPayload(['price' => 'Цена по запросу'])]])
                ->push(['orders' => [$formattedPayload]]),
            'https://my.deal.test/api/v1/orders/set_status' => Http::response([
                'processed_ids' => [987654],
            ]),
        ]);

        $this->artisan('deal:pull-orders')->assertSuccessful();
        $this->assertDatabaseHas('order_items', ['price' => 0]);

        Carbon::setTestNow('2026-10-19 08:03:00 UTC');
        $this->artisan('deal:pull-orders')->assertSuccessful();

        $this->assertDatabaseHas('order_items', [
            'product_sku' => 'test-product',
            'price' => 505.40,
            'total' => 505.40,
        ]);
        $this->assertDatabaseHas('orders', [
            'number' => 'DBY-987654',
            'subtotal' => 505.40,
            'delivery_price' => 10,
            'total' => 515.40,
        ]);
        Event::assertDispatched(NewOrderCreated::class, 1);
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

        Http::fake([
            'https://my.deal.test/api/v1/orders/list*' => Http::sequence()
                ->push(['orders' => $firstPage])
                ->push(['orders' => [$this->orderPayload(['id' => 101])]]),
            'https://my.deal.test/api/v1/orders/set_status' => Http::response([
                'processed_ids' => range(1, 101),
            ]),
        ]);

        $this->artisan('deal:pull-orders')->assertSuccessful();

        $this->assertDatabaseCount('deal_by_order_inboxes', 101);
        $this->assertDatabaseCount('orders', 101);
        $listRequests = collect(Http::recorded())
            ->filter(fn (array $record): bool => $record[0]->method() === 'GET')
            ->values();
        $this->assertCount(2, $listRequests);
        $this->assertFalse(isset($listRequests[0][0]['last_id']));
        $this->assertSame(100, $listRequests[1][0]['last_id']);
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
