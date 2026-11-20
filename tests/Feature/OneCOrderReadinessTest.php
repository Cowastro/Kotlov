<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Actions\OneCOrderPreviewAction;
use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Order;
use App\Models\OrderIntegrationDelivery;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Integrations\OneCOrderQueue;
use App\Services\Integrations\OneCOrderReadiness;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OneCOrderReadinessTest extends TestCase
{
    use RefreshDatabase;

    private IntegrationSource $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->source = IntegrationSource::query()->updateOrCreate(['code' => 'onec'], ['name' => 'Центральная 1С', 'is_active' => true]);
        // Retail orders go to 1C as well (option «а»: one «Розничный покупатель» counterparty).
        $this->source->forceFill(['settings' => array_merge((array) $this->source->settings,
            ['order_pricing_types' => ['retail', 'b2b'], 'require_linked_order_products' => false])])->save();
    }

    public function test_an_order_of_linked_cards_is_ready_and_a_retail_buyer_is_the_one_retail_counterparty(): void
    {
        $order = $this->order([['Котёл', $this->card('Котёл', ['guid-kotel'])]]);

        $result = app(OneCOrderReadiness::class)->check($order, $this->source);

        $this->assertSame([], $result['problems']);
        $this->assertSame(['guid-kotel'], array_values($result['external_ids']));
        $this->assertFalse($result['partner']['b2b']);
        $this->assertSame('kotlov-retail-customer', $result['partner']['id']);
        $this->assertSame('Розничный покупатель', $result['partner']['name']);
    }

    public function test_an_unlinked_line_a_card_with_two_1c_items_and_a_line_without_a_card_block_the_order(): void
    {
        $order = $this->order([
            ['Не привязан', $this->card('Не привязан', [])],
            ['Два товара 1С', $this->card('Два товара 1С', ['guid-a', 'guid-b'])],
            ['Ручная строка', null],
        ]);

        $problems = app(OneCOrderReadiness::class)->check($order, $this->source)['problems'];

        $this->assertCount(3, $problems);
        $this->assertStringContainsString('«Не привязан»: карточка не привязана к товару 1С', implode("\n", $problems));
        $this->assertStringContainsString('«Два товара 1С»: карточка связана с несколькими товарами 1С', implode("\n", $problems));
        $this->assertStringContainsString('«Ручная строка»: позиция без карточки товара', implode("\n", $problems));

        try {
            app(OneCOrderQueue::class)->queue($order, User::factory()->create(['role' => 'manager']));
            $this->fail('The order must not be queued.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Заказ нельзя передать в 1С', $e->getMessage());
        }
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame(0, OrderIntegrationDelivery::query()->count());
    }

    public function test_a_company_order_needs_the_unp(): void
    {
        $partner = User::factory()->create(['client_type' => 'wholesale', 'b2b_approved' => true, 'company_name' => 'ООО «Партнёр»', 'company_inn' => null]);
        $order = $this->order([['Котёл', $this->card('Котёл', ['guid-kotel'])]], ['user_id' => $partner->id], 'b2b');

        $result = app(OneCOrderReadiness::class)->check($order, $this->source);

        $this->assertTrue($result['partner']['b2b']);
        $this->assertContains('У компании-покупателя не указан УНП — по нему 1С находит контрагента', $result['problems']);
    }

    public function test_the_retail_export_sends_the_retail_counterparty_and_the_buyer_in_requisites(): void
    {
        $this->source->forceFill(['username' => 'onec-test', 'password_hash' => bcrypt('secret-test')])->save();
        $order = $this->order([['Котёл', $this->card('Котёл', ['guid-kotel'])]]);
        app(OneCOrderQueue::class)->queue($order, User::factory()->create(['role' => 'manager']));

        $this->withBasicAuth('onec-test', 'secret-test')->get('/1c/exchange?type=sale&mode=query')->assertOk()
            ->assertSee('<Ид>kotlov-retail-customer</Ид>', false)
            ->assertSee('<Наименование>Розничный покупатель</Наименование>', false)
            ->assertSee('<Ид>guid-kotel</Ид>', false)
            ->assertSee('Иван Покупатель, +375291112233', false)
            ->assertDontSee('<Контакты>', false)
            ->assertDontSee('kotlov-customer-', false)
            ->assertDontSee('kotlov-product-', false);
    }

    public function test_the_order_preview_shows_the_1c_codes_and_the_reasons(): void
    {
        $order = $this->order([['Котёл', $this->card('Котёл', ['guid-kotel'])], ['Не привязан', $this->card('Не привязан', [])]]);
        IntegrationProduct::query()->where('external_id', 'guid-kotel')->update(['external_code' => 'УТ-00000777']);

        $render = new \ReflectionMethod(OneCOrderPreviewAction::class, 'render');
        $html = (string) $render->invoke(null, $order);

        $this->assertStringContainsString('Заказ не уйдёт в 1С', $html);
        $this->assertStringContainsString('УТ-00000777', $html);
        $this->assertStringContainsString('карточка не привязана к товару 1С', $html);
        $this->assertStringContainsString('Розничный покупатель', $html);
    }

    /** @param array<int, string> $externalIds */
    private function card(string $name, array $externalIds): Product
    {
        $category = Category::query()->firstOrCreate(['slug' => 'kotly'], ['name' => 'Котлы', 'is_active' => true]);
        $card = Product::query()->create(['category_id' => $category->id, 'name' => $name, 'slug' => 'card-'.uniqid(), 'price' => 100]);
        foreach ($externalIds as $id) {
            IntegrationProduct::query()->create(['integration_source_id' => $this->source->id, 'external_id' => $id,
                'name' => $name, 'product_id' => $card->id, 'match_status' => 'matched']);
        }

        return $card;
    }

    /** @param array<int, array{0: string, 1: ?Product}> $lines */
    private function order(array $lines, array $attributes = [], string $pricing = 'retail'): Order
    {
        $order = Order::query()->create(array_merge([
            'number' => 'ORD-READY-'.uniqid(), 'status' => 'confirmed', 'customer_name' => 'Иван Покупатель',
            'customer_phone' => '+375291112233', 'delivery_type' => 'pickup', 'payment_type' => 'cash',
            'payment_status' => 'pending', 'subtotal' => 100, 'total' => 100,
        ], $attributes));
        foreach ($lines as [$name, $card]) {
            OrderItem::query()->create(['order_id' => $order->id, 'product_id' => $card?->id, 'product_name' => $name,
                'price' => 100, 'quantity' => 1, 'total' => 100, 'pricing_type' => $pricing, 'fulfillment_confirmed_at' => now()]);
        }

        return $order;
    }
}
