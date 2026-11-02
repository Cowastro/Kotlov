<?php

namespace Tests\Feature;

use App\Filament\Resources\B2bOrders\Pages\EditB2bOrder;
use App\Mail\B2bOrderInvoiceIssuedMail;
use App\Mail\B2bOrderStageMail;
use App\Models\B2bClient;
use App\Models\B2bOrder;
use App\Models\B2bOrderItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class B2bOrderProcessTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_process_from_reserve_to_invoice_in_cabinet_payment_and_shipment(): void
    {
        Mail::fake();
        [$order, $partner, $manager] = $this->scenario('invoice');

        $this->actingAs($manager);
        $page = Livewire::test(EditB2bOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('accept')
            ->assertHasNoActionErrors();
        $this->assertSame('checking', $order->fresh()->status);
        $this->assertSame('available', $order->items()->sole()->availability_status);

        $page->callAction('sendToClient', data: ['message' => 'Доставка в четверг.'])->assertHasNoActionErrors();
        $this->assertSame('awaiting_client', $order->fresh()->status);
        Mail::assertQueued(B2bOrderStageMail::class, fn (B2bOrderStageMail $mail): bool => $mail->stage === 'proposal'
            && $mail->hasTo('buh@process.test'));
        $this->assertDatabaseHas('b2b_order_comments', ['b2b_order_id' => $order->id, 'visible_to_client' => true, 'body' => 'Доставка в четверг.']);

        $this->actingAs($partner)->get(route('b2b.cabinet', ['section' => 'orders']))
            ->assertOk()->assertSee('Подтвердить и получить счёт')->assertSee('Доставка в четверг.');
        $this->actingAs($partner)->post(route('b2b.cabinet.orders.confirm', $order))
            ->assertRedirect(route('b2b.cabinet', ['section' => 'orders']))
            ->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'сформирован'));

        $order->refresh();
        $this->assertSame('awaiting_payment', $order->status);
        $this->assertSame('invoiced', $order->payment_status);
        $this->assertNotNull($order->commercial_document_id);
        $this->assertSame($manager->id, $order->commercialDocument->manager_id);
        Mail::assertQueued(B2bOrderInvoiceIssuedMail::class, fn ($mail): bool => $mail->hasTo('buh@process.test'));
        $this->assertTrue($manager->notifications()->get()
            ->contains(fn ($notification): bool => str_contains((string) ($notification->data['title'] ?? ''), 'Клиент подтвердил')));

        // The invoice is in the cabinet at once: on the order and among documents.
        $this->actingAs($partner)->get(route('b2b.cabinet', ['section' => 'orders']))
            ->assertOk()->assertSee('Открыть счёт')->assertSee('Ожидаем оплату по счёту');
        $this->actingAs($partner)->get(route('b2b.cabinet', ['section' => 'documents']))
            ->assertOk()->assertSee($order->commercialDocument->number);

        $this->actingAs($manager);
        $page = Livewire::test(EditB2bOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('markPaid', data: ['comment' => 'П/п 145'])->assertHasNoActionErrors();
        $this->assertSame('paid', $order->fresh()->status);

        foreach (['assembling', 'ready_to_ship', 'shipped', 'completed'] as $expected) {
            $page->callAction('fulfil')->assertHasNoActionErrors();
            $this->assertSame($expected, $order->fresh()->status);
        }
        $this->assertDatabaseHas('b2b_order_reservations', ['b2b_order_id' => $order->id, 'status' => 'shipped']);
        Mail::assertQueued(B2bOrderStageMail::class, fn (B2bOrderStageMail $mail): bool => $mail->stage === 'shipped');
    }

    public function test_deferred_payment_goes_to_warehouse_right_after_confirmation(): void
    {
        Mail::fake();
        [$order, $partner, $manager] = $this->scenario('invoice_deferred');
        $this->actingAs($manager);
        Livewire::test(EditB2bOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('accept')->callAction('sendToClient', data: ['message' => null]);

        $this->actingAs($partner)->post(route('b2b.cabinet.orders.confirm', $order));

        $order->refresh();
        $this->assertSame('credit_approved', $order->status);
        $this->assertSame('credit', $order->payment_status);
        $this->assertNotNull($order->commercial_document_id);
    }

    public function test_send_to_client_is_blocked_until_lines_are_decided_and_cancel_releases_stock(): void
    {
        Mail::fake();
        [$order, $partner, $manager] = $this->scenario('invoice', stock: 1);
        $this->actingAs($manager);
        $page = Livewire::test(EditB2bOrder::class, ['record' => $order->getRouteKey()])->callAction('accept');
        $this->assertSame('partial', $order->items()->sole()->availability_status);

        // Partial is decided: the client sees 1 of 2. A pending line would disable the button.
        $order->items()->update(['availability_status' => 'pending', 'checked_by' => null]);
        $page->assertActionDisabled('sendToClient');

        $page->callAction('cancel', data: ['reason' => 'Клиент купил в другом месте'])->assertHasNoActionErrors();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertDatabaseMissing('b2b_order_reservations', ['b2b_order_id' => $order->id, 'status' => 'active']);
        $this->assertDatabaseHas('b2b_order_comments', [
            'b2b_order_id' => $order->id, 'visible_to_client' => true, 'body' => 'Заказ отменён: Клиент купил в другом месте',
        ]);
        Mail::assertQueued(B2bOrderStageMail::class, fn (B2bOrderStageMail $mail): bool => $mail->stage === 'cancelled');
    }

    public function test_client_letters_render_for_every_stage(): void
    {
        [$order] = $this->scenario('invoice');
        $order->items()->update(['availability_status' => 'available', 'confirmed_quantity' => 2, 'delivery_days' => 2]);

        $proposal = (new B2bOrderStageMail($order->fresh(), 'proposal', 'Доставка в четверг.'))->render();
        $this->assertStringContainsString('Подтвердить и получить счёт', $proposal);
        $this->assertStringContainsString('Труба процесса', $proposal);
        $this->assertStringContainsString('300,00 BYN', $proposal);
        $this->assertStringContainsString('Доставка в четверг.', $proposal);
        $this->assertStringContainsString('Заказ отгружен', (new B2bOrderStageMail($order->fresh(), 'shipped'))->render());
        $this->assertStringContainsString('Резерв по заказу снят', (new B2bOrderStageMail($order->fresh(), 'cancelled', 'Отказ'))->render());
    }

    public function test_every_client_letter_is_recorded_in_order_history_queued_and_sent(): void
    {
        // Real sending through the sync queue into the array transport.
        config(['mail.mailers.transactional' => ['transport' => 'array']]);
        [$order, $partner, $manager] = $this->scenario('invoice');
        $this->actingAs($manager);
        Livewire::test(EditB2bOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('accept')->callAction('sendToClient', data: ['message' => null]);

        $this->assertDatabaseHas('b2b_order_events', ['b2b_order_id' => $order->id, 'event' => 'client_email_queued',
            'comment' => 'Письмо «Наличие подтверждено, товар в резерве» поставлено в очередь на buh@process.test.']);
        $this->assertDatabaseHas('b2b_order_events', ['b2b_order_id' => $order->id, 'event' => 'client_email_sent',
            'comment' => 'Письмо «Наличие подтверждено, товар в резерве» отправлено на buh@process.test.']);

        $this->actingAs($partner)->post(route('b2b.cabinet.orders.confirm', $order));
        $this->assertDatabaseHas('b2b_order_events', ['b2b_order_id' => $order->id, 'event' => 'client_email_sent',
            'comment' => 'Письмо «Счёт» отправлено на buh@process.test.']);

        $this->actingAs($manager);
        Livewire::test(\App\Filament\Resources\B2bOrders\RelationManagers\EventsRelationManager::class, [
            'ownerRecord' => $order, 'pageClass' => EditB2bOrder::class,
        ])->assertSee('Письмо отправлено')->assertSee('buh@process.test');
    }

    public function test_letter_without_valid_address_is_recorded_as_not_sent(): void
    {
        Mail::fake();
        [$order, $partner, $manager] = $this->scenario('invoice');
        $order->forceFill(['company_email' => 'нет-почты', 'contact_email' => null])->save();
        $this->actingAs($manager);
        Livewire::test(EditB2bOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('accept')->callAction('sendToClient', data: ['message' => null]);

        $this->assertDatabaseHas('b2b_order_events', ['b2b_order_id' => $order->id, 'event' => 'client_email_skipped']);
        Mail::assertNothingQueued();
    }

    /** @return array{B2bOrder, User, User} */
    private function scenario(string $paymentType, int $stock = 10): array
    {
        $supplier = Supplier::query()->create([
            'code' => 'process-b2b', 'name' => 'Склад процесса', 'is_active' => true,
            'b2b_partner_enabled' => true, 'b2b_client_warehouse_label' => 'Партнёрский склад',
            'b2b_delivery_days_max' => 2, 'b2b_require_positive_stock' => true,
        ]);
        $category = Category::query()->create(['name' => 'Трубы', 'slug' => 'process-b2b', 'is_active' => true]);
        $product = Product::query()->create([
            'category_id' => $category->id, 'name' => 'Труба процесса', 'slug' => 'process-b2b-pipe',
            'sku' => 'PRC-1', 'price' => 150, 'is_active' => true, 'is_archived' => false,
        ]);
        $offer = SupplierProduct::query()->create([
            'supplier_id' => $supplier->id, 'product_id' => $product->id, 'product_sku' => 'PRC-1',
            'supplier_article' => 'PRC-1', 'supplier_name' => $product->name, 'price' => 80, 'price_byn' => 80,
            'currency' => 'BYN', 'in_stock' => true, 'stock_quantity' => $stock, 'match_status' => 'matched',
            'last_stock_synced_at' => now(), 'last_synced_at' => now(),
        ]);
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true, 'can_manage_b2b_orders' => true]);
        $partner = User::factory()->create(['client_type' => 'wholesale', 'b2b_approved' => true, 'is_active' => true]);
        $client = B2bClient::query()->create([
            'user_id' => $partner->id, 'status' => 'approved', 'partner_type' => 'wholesale',
            'company_name' => 'ООО «Процесс»', 'unp' => '190000077', 'contact_name' => $partner->name,
            'contact_email' => $partner->email, 'responsible_user_id' => $manager->id,
        ]);
        $order = B2bOrder::query()->create([
            'b2b_client_id' => $client->id, 'created_by' => $partner->id, 'assigned_manager_id' => $manager->id,
            'number' => 'B2B-PROCESS-'.$paymentType, 'status' => 'assigned', 'payment_type' => $paymentType,
            'contact_name' => $partner->name, 'contact_phone' => '+375291112233', 'contact_email' => $partner->email,
            'company_name' => $client->company_name, 'company_unp' => $client->unp, 'company_email' => 'buh@process.test',
            'subtotal' => 300, 'total' => 300,
        ]);
        B2bOrderItem::query()->create([
            'b2b_order_id' => $order->id, 'product_id' => $product->id, 'supplier_product_id' => $offer->id,
            'product_name' => $product->name, 'price' => 150, 'quantity' => 2, 'total' => 300,
        ]);

        return [$order, $partner, $manager];
    }
}
