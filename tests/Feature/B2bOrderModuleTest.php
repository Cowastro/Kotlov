<?php

namespace Tests\Feature;

use App\Models\B2bClient;
use App\Models\B2bOrder;
use App\Models\User;
use App\Services\B2bOrders\B2bOrderWorkflow;
use App\Services\B2bOrders\B2bOrderInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class B2bOrderModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_designated_b2b_manager_can_accept_and_move_separate_order(): void
    {
        $partner = User::factory()->create();
        $client = B2bClient::query()->create([
            'user_id' => $partner->id,
            'status' => 'approved',
            'partner_type' => 'wholesale',
            'company_name' => 'ООО «Контур B2B»',
            'unp' => '190000001',
            'contact_name' => $partner->name,
            'contact_email' => $partner->email,
        ]);
        $order = B2bOrder::query()->create([
            'b2b_client_id' => $client->id,
            'created_by' => $partner->id,
            'number' => 'B2B-ISOLATED-1',
            'status' => 'new',
            'contact_name' => $partner->name,
            'contact_phone' => '+375291112233',
            'company_name' => $client->company_name,
            'subtotal' => 100,
            'total' => 100,
        ]);
        $retailManager = User::factory()->create([
            'role' => 'manager', 'is_active' => true, 'can_manage_b2b_orders' => false,
        ]);
        $b2bManager = User::factory()->create([
            'role' => 'manager', 'is_active' => true, 'can_manage_b2b_orders' => true,
        ]);

        try {
            app(B2bOrderWorkflow::class)->accept($order, $retailManager);
            $this->fail('Обычный менеджер не должен принимать B2B-заказ.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $accepted = app(B2bOrderWorkflow::class)->accept($order->fresh(), $b2bManager);
        $this->assertSame('checking', $accepted->status);
        $this->assertSame($b2bManager->id, $accepted->assigned_manager_id);
        $this->assertNotNull($accepted->accepted_at);
        $this->assertDatabaseHas('b2b_order_events', [
            'b2b_order_id' => $order->id,
            'actor_id' => $b2bManager->id,
            'from_status' => 'new',
            'to_status' => 'checking',
        ]);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_b2b_crm_queue_is_available_only_to_designated_staff(): void
    {
        $retailManager = User::factory()->create([
            'role' => 'manager', 'is_active' => true, 'can_manage_b2b_orders' => false,
        ]);
        $b2bManager = User::factory()->create([
            'role' => 'manager', 'is_active' => true, 'can_manage_b2b_orders' => true,
        ]);
        $partner = User::factory()->create();
        $client = B2bClient::query()->create([
            'user_id' => $partner->id,
            'status' => 'approved',
            'partner_type' => 'wholesale',
            'company_name' => 'ООО «Отдельная очередь»',
            'contact_name' => $partner->name,
        ]);
        $order = B2bOrder::query()->create([
            'b2b_client_id' => $client->id,
            'created_by' => $partner->id,
            'number' => 'B2B-CRM-QUEUE',
            'status' => 'new',
            'contact_name' => $partner->name,
            'contact_phone' => '+375291112233',
            'company_name' => $client->company_name,
            'subtotal' => 100,
            'total' => 100,
        ]);

        $this->actingAs($retailManager)->get('/admin/b2b-orders')->assertForbidden();
        $this->actingAs($b2bManager)->get('/admin/b2b-orders')->assertOk();
        $this->actingAs($b2bManager)->get('/admin/b2b-orders/'.$order->id.'/edit')->assertOk();
    }

    public function test_legacy_migration_is_preview_first_and_idempotent(): void
    {
        $partner = User::factory()->create();
        $client = B2bClient::query()->create([
            'user_id' => $partner->id,
            'status' => 'approved',
            'partner_type' => 'wholesale',
            'company_name' => 'ООО «Старый заказ»',
            'contact_name' => $partner->name,
        ]);
        $legacy = \App\Models\Order::query()->create([
            'user_id' => $partner->id,
            'number' => 'LEGACY-1',
            'status' => 'new',
            'customer_name' => $partner->name,
            'customer_phone' => '+375291112233',
            'delivery_type' => 'pickup',
            'payment_type' => 'invoice',
            'payment_status' => 'pending',
            'subtotal' => 75,
            'total' => 75,
            'b2b_commercial_terms_snapshot' => ['b2b_client_id' => $client->id],
        ]);
        $legacy->items()->create([
            'product_name' => 'Старая B2B-позиция', 'price' => 75, 'quantity' => 1,
            'total' => 75, 'pricing_type' => 'b2b',
        ]);

        $this->artisan('b2b:orders:migrate-legacy')->assertSuccessful();
        $this->assertDatabaseCount('b2b_orders', 0);

        $this->artisan('b2b:orders:migrate-legacy', ['--apply' => true])->assertSuccessful();
        $this->artisan('b2b:orders:migrate-legacy', ['--apply' => true])->assertSuccessful();
        $this->assertDatabaseCount('b2b_orders', 1);
        $this->assertDatabaseHas('b2b_orders', ['legacy_order_id' => $legacy->id, 'number' => 'B2B-LEGACY-1']);
        $this->assertDatabaseHas('b2b_order_items', ['product_name' => 'Старая B2B-позиция']);
        $this->assertDatabaseHas('orders', ['id' => $legacy->id]);
        $this->assertTrue($legacy->created_at->equalTo(B2bOrder::query()->firstOrFail()->created_at));
    }

    public function test_partner_confirms_checked_order_and_b2b_manager_issues_separate_invoice(): void
    {
        Mail::fake();
        $partner = User::factory()->create(['email_verified_at' => now()]);
        $client = B2bClient::query()->create([
            'user_id' => $partner->id,
            'status' => 'approved',
            'partner_type' => 'wholesale',
            'company_name' => 'ООО «Счёт B2B»',
            'unp' => '190000001',
            'contact_name' => $partner->name,
            'contact_email' => $partner->email,
        ]);
        $order = B2bOrder::query()->create([
            'b2b_client_id' => $client->id,
            'created_by' => $partner->id,
            'number' => 'B2B-INVOICE-NEW',
            'status' => 'awaiting_client',
            'payment_type' => 'invoice',
            'contact_name' => $partner->name,
            'contact_phone' => '+375291112233',
            'contact_email' => $partner->email,
            'company_name' => $client->company_name,
            'company_unp' => $client->unp,
            'company_email' => $partner->email,
            'subtotal' => 200,
            'total' => 200,
        ]);
        $order->items()->create([
            'product_name' => 'Подтверждённая позиция',
            'price' => 100,
            'quantity' => 2,
            'total' => 200,
            'availability_status' => 'available',
            'confirmed_quantity' => 2,
            'checked_at' => now(),
        ]);

        $this->actingAs($partner)
            ->post(route('b2b.cabinet.orders.confirm', $order))
            ->assertRedirect(route('b2b.cabinet', ['section' => 'orders']));
        $this->assertSame('client_confirmed', $order->fresh()->status);

        $manager = User::factory()->create([
            'role' => 'manager', 'is_active' => true, 'can_manage_b2b_orders' => true,
        ]);
        $invoice = app(B2bOrderInvoice::class)->issue($order->fresh(), $manager);

        $this->assertSame('invoice', $invoice->type);
        $this->assertSame('sent', $invoice->status);
        $this->assertSame(200.0, (float) $invoice->total);
        $this->assertSame('invoice_issued', $order->fresh()->status);
        $this->assertDatabaseHas('b2b_order_events', [
            'b2b_order_id' => $order->id,
            'event' => 'client_confirmed',
        ]);
        Mail::assertQueued(\App\Mail\B2bOrderInvoiceIssuedMail::class, function ($mail) use ($partner): bool {
            return $mail->hasTo($partner->email);
        });
        $this->assertNotNull($order->fresh()->invoice_notified_at);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_manager_cannot_offer_unchecked_positions_or_skip_invoice_for_credit(): void
    {
        $partner = User::factory()->create();
        $client = B2bClient::query()->create([
            'user_id' => $partner->id,
            'status' => 'approved',
            'partner_type' => 'wholesale',
            'company_name' => 'ООО «Контроль этапов»',
            'contact_name' => $partner->name,
        ]);
        $manager = User::factory()->create([
            'role' => 'manager', 'is_active' => true, 'can_manage_b2b_orders' => true,
        ]);
        $order = B2bOrder::query()->create([
            'b2b_client_id' => $client->id,
            'created_by' => $partner->id,
            'number' => 'B2B-STAGE-GUARD',
            'status' => 'checking',
            'payment_type' => 'invoice_deferred',
            'contact_name' => $partner->name,
            'contact_phone' => '+375291112233',
            'company_name' => $client->company_name,
            'subtotal' => 100,
            'total' => 100,
        ]);
        $order->items()->create([
            'product_name' => 'Непроверенная позиция', 'price' => 100, 'quantity' => 1,
            'total' => 100, 'availability_status' => 'pending',
        ]);

        try {
            app(B2bOrderWorkflow::class)->transition($order, 'proposal_ready', $manager);
            $this->fail('Нельзя готовить предложение до проверки всех позиций.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $order->items()->update(['availability_status' => 'available', 'checked_at' => now()]);
        app(B2bOrderWorkflow::class)->transition($order->fresh(), 'proposal_ready', $manager);
        app(B2bOrderWorkflow::class)->transition($order->fresh(), 'awaiting_client', $manager);
        app(B2bOrderWorkflow::class)->clientConfirm($order->fresh(), $partner);

        try {
            app(B2bOrderWorkflow::class)->transition($order->fresh(), 'credit_approved', $manager);
            $this->fail('Отсрочка не должна обходить формирование счёта.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
    }
}
