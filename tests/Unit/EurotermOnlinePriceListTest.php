<?php

namespace Tests\Unit;

use App\Services\Suppliers\EurotermOnlinePriceList;
use App\Services\Suppliers\EurotermPortalCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EurotermOnlinePriceListTest extends TestCase
{
    public static function page(string $article = 'SKU-01', string $stock = 'В наличии', string $pages = ''): string
    {
        return '<form><input name="task" value="user.logout"></form><div class="spacer product-container">'
            .'<div class="sku">'.$article.'</div><div class="vm-product_name"><a href="/product.html">Товар '.$article.'</a></div>'
            .'<div class="stock-info listview">'.$stock.'</div><span class="PricesalesPrice">1 234,50 p.</span>'
            .'<span class="Priceroz">1500,00</span><div id="kurseu">1</div></div>'.$pages;
    }

    public function test_authenticated_prices_and_unknown_quantity_are_preserved(): void
    {
        $parser = app(EurotermOnlinePriceList::class);
        $row = $parser->parsePage(self::page())['rows'][0];
        $this->assertSame(1234.5, $row['wholesale']);
        $this->assertSame(1500.0, $row['retail']);
        $this->assertNull($row['stock_quantity']);
        $this->assertTrue($row['in_stock']);
        $row = $parser->parsePage(self::page(stock: 'Нет в наличии'))['rows'][0];
        $this->assertFalse($row['in_stock']);
        $this->assertSame(0, $row['stock_quantity']);
    }

    public function test_guest_page_is_rejected_even_if_it_contains_prices(): void
    {
        $this->expectExceptionMessage('Вход в Евротерм не подтверждён');
        app(EurotermOnlinePriceList::class)->parsePage(str_replace('user.logout', 'user.login', self::page()));
    }

    public function test_full_login_and_pagination_are_checked_before_returning_a_snapshot(): void
    {
        Storage::fake('local');
        app(EurotermPortalCredentials::class)->save('login', 'private-password');
        Http::preventStrayRequests();
        Http::fake([
            'euroterm.by/log-in.html' => Http::response('<form action="/index.php?task=user.login"><input type="password" name="password"><input type="hidden" name="token" value="1"></form>'),
            'euroterm.by/index.php*' => Http::response('', 303, ['Location' => '/prajs-list.html']),
            'euroterm.by/prajs-list.html' => Http::response(self::page(pages: '<a href="/results25-48.html">Вперёд</a>')),
            'euroterm.by/results25-48.html' => Http::response(self::page('SKU-02', 'Нет в наличии')),
        ]);
        $payload = app(EurotermOnlinePriceList::class)->load();
        $this->assertCount(2, $payload['rows']);
        $this->assertSame(2, $payload['source_pages']);
        $this->assertSame('online', $payload['source_kind']);
        Http::assertSent(fn ($request): bool => $request->method() === 'POST' && $request['username'] === 'login' && $request['token'] === '1');
        $this->assertFalse(app(EurotermPortalCredentials::class)->due());
    }

    public function test_partial_failure_never_returns_a_snapshot(): void
    {
        Storage::fake('local');
        app(EurotermPortalCredentials::class)->save('login', 'private-password');
        Http::fake([
            'euroterm.by/log-in.html' => Http::response('<form action="/index.php?task=user.login"><input type="password"></form>'),
            'euroterm.by/index.php*' => Http::response('', 303),
            'euroterm.by/prajs-list.html' => Http::response(self::page(pages: '<a href="/results25-48.html">2</a>')),
            'euroterm.by/results25-48.html' => Http::response('unavailable', 503),
        ]);
        $this->expectExceptionMessage('HTTP 503');
        app(EurotermOnlinePriceList::class)->load();
    }
}
