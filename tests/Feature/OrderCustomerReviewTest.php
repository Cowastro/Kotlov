<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\Orders\OrderReviewInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderCustomerReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_only_order_products_and_no_private_order_details(): void
    {
        [$order, $product] = $this->order();
        $url = app(OrderReviewInvitation::class)->url($order);
        $this->get($url)->assertOk()->assertSee($order->number)->assertSee($product->name)
            ->assertSee('Обслуживание и заказ в целом')->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertDontSee($order->customer_email)->assertDontSee($order->customer_phone)
            ->assertDontSee('Поставщик секретный')->assertDontSee('SECRET-ADDRESS');
        $this->get('/order-review/'.$order->id)->assertForbidden();
        $this->post('/order-review/'.$order->id, $this->data())->assertForbidden();
        $other = $order->replicate();
        $other->number = 'ORD-REVIEW-OTHER';
        $other->save();
        $this->get(str_replace('/order-review/'.$order->id, '/order-review/'.$other->id, $url))->assertForbidden();
    }

    public function test_product_review_is_bound_to_order_moderated_and_deduplicated(): void
    {
        [$order, $product] = $this->order();
        $url = app(OrderReviewInvitation::class)->url($order);
        $this->post($url, $this->data((string) $product->id) + ['is_approved' => true])->assertRedirect($url);
        $review = Review::sole();
        $this->assertSame($order->id, $review->order_id);
        $this->assertTrue($review->reviewable->is($product));
        $this->assertFalse($review->is_approved);
        $this->assertNull($review->author_email);
        $this->assertNull($review->author_phone);
        $this->post($url, $this->data((string) $product->id))->assertRedirect($url);
        $this->assertDatabaseCount('reviews', 1);
        $this->get('/reviews')->assertOk()->assertDontSee('Заказ доставили аккуратно и вовремя');
        $review->update(['is_approved' => true]);
        $this->get('/reviews')->assertOk()->assertSee('Заказ доставили аккуратно и вовремя')->assertSee('Отзыв по заказу')
            ->assertDontSee($order->customer_email)->assertDontSee($order->customer_phone);
        $this->assertSame(1, $product->fresh()->reviews_count);
        $this->assertEquals(4, $product->fresh()->rating);
    }

    public function test_service_review_and_product_review_can_both_be_left_and_low_ratings_are_accepted(): void
    {
        [$order, $product] = $this->order();
        $url = app(OrderReviewInvitation::class)->url($order);
        $this->post($url, array_replace($this->data(), ['rating' => 1]))->assertRedirect($url);
        $review = Review::sole();
        $this->assertTrue($review->reviewable->is($order));
        $this->assertSame(1, (int) $review->rating);
        $this->post($url, $this->data((string) $product->id))->assertRedirect($url);
        $this->get($url)->assertOk()->assertSee('Спасибо за ваши отзывы!')->assertDontSee('name="rating"', false);
        $this->assertDatabaseCount('reviews', 2);
    }

    public function test_unknown_product_invalid_rating_and_missing_consent_are_rejected(): void
    {
        [$order] = $this->order();
        $url = app(OrderReviewInvitation::class)->url($order);
        $this->post($url, $this->data('99999999'))->assertSessionHasErrors('target');
        $this->post($url, array_replace($this->data(), ['rating' => 6]))->assertSessionHasErrors('rating');
        $this->post($url, array_replace($this->data(), ['consent' => 0]))->assertSessionHasErrors('consent');
        $this->post($url, array_replace($this->data(), ['text' => '<script>alert(1)</script>']))->assertSessionHasErrors('text');
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_manager_can_copy_invitation_from_order_list_without_sending_anything(): void
    {
        [$order] = $this->order();
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true, 'is_crm_user' => true]);
        $page = Livewire::actingAs($manager)->test(ListOrders::class)
            ->set('activeTab', 'all')
            ->mountTableAction('customer_review_link', $order)
            ->assertActionMounted(\Filament\Actions\Testing\TestAction::make('customer_review_link')->table($order));
        $this->assertSame('Ссылка для отзыва клиента', $page->instance()->getMountedAction()->getModalHeading());
        $schema = $page->instance()->getSchema($page->instance()->getMountedActionSchemaName());
        $this->assertStringContainsString('/order-review/'.$order->id, $schema->toHtml());
        $this->assertDatabaseCount('reviews', 0);
    }

    private function data(string $target = 'order'): array
    {
        return ['target' => $target, 'author_name' => 'Александр', 'rating' => 4, 'text' => 'Заказ доставили аккуратно и вовремя', 'consent' => 1];
    }

    private function order(): array
    {
        $category = Category::create(['name' => 'Печи', 'slug' => 'review-pechi', 'is_active' => true]);
        $product = Product::create(['name' => 'Печь для отзыва', 'slug' => 'review-stove', 'category_id' => $category->id, 'price' => 100, 'is_active' => true]);
        $order = Order::create([
            'number' => 'ORD-REVIEW-001', 'status' => 'delivered',
            'customer_name' => 'Секретное имя клиента', 'customer_phone' => '+375291234567', 'customer_email' => 'private-client@example.com',
            'delivery_type' => 'pickup', 'delivery_address' => 'SECRET-ADDRESS',
            'payment_type' => 'cash', 'payment_status' => 'paid', 'subtotal' => 100, 'total' => 100,
        ]);
        $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'product_sku' => 'TEST-REVIEW', 'price' => 100, 'quantity' => 1, 'total' => 100, 'supply_supplier_name' => 'Поставщик секретный']);

        return [$order, $product];
    }
}
