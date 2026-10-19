<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProbeDealByCommandTest extends TestCase
{
    public function test_it_stops_before_a_request_when_token_is_missing(): void
    {
        config()->set('services.deal_by.token', '');
        Http::preventStrayRequests();

        $this->artisan('deal:probe')
            ->expectsOutputToContain('DEAL_BY_API_TOKEN не задан')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_it_checks_products_and_orders_without_writing(): void
    {
        config()->set('services.deal_by', [
            'base_url' => 'https://my.deal.test/api/v1',
            'token' => 'secret-test-token',
            'connect_timeout' => 1,
            'timeout' => 1,
        ]);

        Http::fake([
            'https://my.deal.test/api/v1/products/list*' => Http::response(['products' => [['id' => 10]]]),
            'https://my.deal.test/api/v1/orders/list*' => Http::response(['orders' => []]),
        ]);

        $this->artisan('deal:probe')
            ->expectsOutputToContain('Товары: доступ подтверждён')
            ->expectsOutputToContain('Заказы: доступ подтверждён')
            ->expectsOutputToContain('Записывающие методы не вызывались')
            ->assertSuccessful();

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->hasHeader('Authorization', 'Bearer secret-test-token')
            && $request->url() === 'https://my.deal.test/api/v1/products/list?limit=1');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->hasHeader('Authorization', 'Bearer secret-test-token')
            && $request->url() === 'https://my.deal.test/api/v1/orders/list?limit=1');
    }
}
