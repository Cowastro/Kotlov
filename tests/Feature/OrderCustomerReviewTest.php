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

    public function test_submission_and_reopening_show_confirmation_without_another_form_even_with_unreviewed_products(): void
    {
        [$order, $product] = $this->order();
        $url = app(OrderReviewInvitation::class)->url($order);
        $this->post($url, array_replace($this->data(), ['rating' => 1]))->assertRedirect($url);
        $review = Review::sole();
        $this->assertTrue($review->reviewable->is($order));
        $this->assertSame(1, (int) $review->rating);
        $this->get($url)->assertOk()->assertSee('Ваш отзыв отправлен')->assertSee('Перейти в каталог')
            ->assertDontSee('name="rating"', false)->assertDontSee('name="target"', false)
            ->assertDontSee('Как прошёл ваш заказ?')->assertDontSee('Спасибо! Ваш отзыв получен и появится на сайте после проверки.');
        $this->post($url, $this->data((string) $product->id))->assertRedirect($url);
        $this->get($url)->assertOk()->assertSee('Ваш отзыв отправлен')->assertDontSee('name="rating"', false);
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

    public function test_short_sms_link_fits_one_message_and_opens_only_the_review_form(): void
    {
        [$order] = $this->order();
        config(['app.url' => 'https://kotlov.by']);
        $service = app(\App\Services\Orders\OrderReviewSms::class);
        $message = $service->message($order);
        $this->assertLessThanOrEqual(70, strlen(mb_convert_encoding($message, 'UTF-16BE', 'UTF-8')) / 2);
        $this->assertSame($message, $service->message($order));
        $this->assertStringNotContainsString($order->customer_phone, $message);
        $url = 'https://kotlov.by/r/'.$order->fresh()->review_sms_token;
        $redirect = $this->get($url)->assertRedirect()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get($redirect->headers->get('Location'))->assertOk()->assertSee('Ваш отзыв')->assertDontSee($order->customer_phone);
        $this->get('/r/'.str_repeat('x', 22))->assertNotFound();
    }

    public function test_manager_can_send_one_sms_and_duplicate_click_is_blocked_and_logged(): void
    {
        [$order] = $this->order();
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true, 'is_crm_user' => true]);
        $this->actingAs($manager);
        config(['app.url' => 'https://kotlov.by', 'services.sms_by.enabled' => true,
            'services.sms_by.token' => 'test-token', 'services.sms_by.alphaname_id' => '123',
            'services.sms_by.base_url' => 'https://app.sms.by']);
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        \Illuminate\Support\Facades\Http::fake(['app.sms.by/*' => \Illuminate\Support\Facades\Http::response(['sms_id' => 'review-123'])]);
        $page = Livewire::test(ListOrders::class)->set('activeTab', 'all')->mountTableAction('customer_review_sms', $order);
        \Illuminate\Support\Facades\Http::assertNothingSent();
        $page->setTableActionData(['phone' => '+375 (29) 123-45-67'])->callMountedTableAction()->assertHasNoTableActionErrors();
        \Illuminate\Support\Facades\Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/sendQuickSMS')
            && $request['phone'] === '375291234567' && mb_strlen($request['message']) <= 70);
        $this->assertSame('review-123', $order->fresh()->review_sms_id);
        $history = $order->statusHistory()->where('comment', 'like', '%review-123%')->sole();
        $this->assertSame($manager->id, $history->user_id);
        $this->assertSame('delivered', $order->fresh()->status);
        try {
            app(\App\Services\Orders\OrderReviewSms::class)->send($order, $order->customer_phone);
            $this->fail('Duplicate SMS should be blocked');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('phone', $e->errors());
        }
        \Illuminate\Support\Facades\Http::assertSentCount(1);
    }

    public function test_sms_failure_is_not_recorded_as_sent_and_invalid_numbers_are_rejected(): void
    {
        [$order] = $this->order();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        config(['app.url' => 'https://kotlov.by', 'services.sms_by.enabled' => true,
            'services.sms_by.token' => 'test-token', 'services.sms_by.alphaname_id' => '123',
            'services.sms_by.base_url' => 'https://app.sms.by']);
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        \Illuminate\Support\Facades\Http::fake(['app.sms.by/*' => \Illuminate\Support\Facades\Http::response(['status' => 'error', 'message' => 'No balance'])]);
        try {
            app(\App\Services\Orders\OrderReviewSms::class)->send($order, '123');
            $this->fail('Invalid phone should be rejected');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('phone', $e->errors());
        }
        \Illuminate\Support\Facades\Http::assertNothingSent();
        try {
            app(\App\Services\Orders\OrderReviewSms::class)->send($order, $order->customer_phone);
            $this->fail('Provider error should be returned');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('SMS.BY', $e->getMessage());
        }
        $this->assertNull($order->fresh()->review_sms_sent_at);
        $this->assertNull($order->fresh()->review_sms_id);
        $this->assertSame(0, $order->statusHistory()->count());
    }

    public function test_message_over_one_sms_is_blocked_before_provider_request(): void
    {
        [$order] = $this->order();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        config(['app.url' => 'https://'.str_repeat('long', 20).'.example.com',
            'services.sms_by.enabled' => true, 'services.sms_by.alphaname_id' => '123']);
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        \Illuminate\Support\Facades\Http::fake();
        try {
            app(\App\Services\Orders\OrderReviewSms::class)->send($order, $order->customer_phone);
            $this->fail('Multipart SMS must not be sent');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('одной СМС', $e->errors()['phone'][0]);
        }
        \Illuminate\Support\Facades\Http::assertNothingSent();
        $this->assertNull($order->fresh()->review_sms_attempted_at);
    }

    public function test_manager_can_publish_from_list_and_action_disappears_with_rating_updated(): void
    {
        [$order, $product] = $this->order();
        $review = Review::create(['order_id' => $order->id, 'reviewable_type' => $product->getMorphClass(),
            'reviewable_id' => $product->id, 'author_name' => 'Покупатель', 'rating' => 2,
            'text' => 'Публикация из списка отзывов', 'is_approved' => false]);
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true, 'is_crm_user' => true]);
        $page = Livewire::actingAs($manager)->test(\App\Filament\Resources\Reviews\Pages\ListReviews::class)
            ->assertTableActionVisible('publish', $review)
            ->callTableAction('publish', $review)->assertHasNoTableActionErrors();
        $this->assertTrue($review->fresh()->is_approved);
        $page->assertTableActionHidden('publish', $review->fresh());
        $this->assertSame(1, $product->fresh()->reviews_count);
        $this->assertEquals(2, $product->fresh()->rating);
        $this->get('/reviews')->assertOk()->assertSee('Публикация из списка отзывов');
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
