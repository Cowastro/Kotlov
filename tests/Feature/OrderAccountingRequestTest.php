<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\CrmMailbox;
use App\Models\Order;
use App\Models\User;
use App\Services\CrmMail\SupplierRequestMailDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderAccountingRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounting_draft_uses_order_mailbox_and_keeps_document_linked_to_order(): void
    {
        Storage::fake('local');
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $order = Order::query()->create([
            'number' => 'ORD-ACC-001',
            'status' => 'confirmed',
            'customer_name' => 'Клиент',
            'customer_phone' => '+375291111111',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 500,
            'total' => 500,
        ]);
        CrmMailbox::query()->create([
            'name' => 'Заказы KOTLOV',
            'email' => 'order@kotlov.by',
            'username' => 'order@kotlov.by',
            'password' => 'secret',
            'imap_host' => 'imap.example.test',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_folder' => 'INBOX',
            'smtp_host' => 'smtp.example.test',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'is_enabled' => true,
        ]);
        $path = 'crm-mail-attachments/accounting/invoice.pdf';
        Storage::disk('local')->put($path, 'test invoice');

        $thread = app(SupplierRequestMailDraftService::class)->createOrderAccountingDraft(
            $order,
            $manager,
            'buh-sbg@yandex.ru',
            'Оплата поставщику — заказ ORD-ACC-001',
            'Просим оплатить приложенный счёт.',
            [$path],
            [$path => 'Счёт поставщика.pdf'],
        );

        $draft = $thread->messages->first();
        $this->assertSame($order->id, $thread->order_id);
        $this->assertSame('order@kotlov.by', $thread->mailbox->email);
        $this->assertSame('buh-sbg@yandex.ru', $thread->participant_email);
        $this->assertSame('order_accounting_payment', $draft->metadata['source']);
        $this->assertSame('Счёт поставщика.pdf', $draft->metadata['attachments'][0]['name']);
        $this->assertSame(1, $draft->attachments_count);
    }

    public function test_accounting_assignment_is_visible_inside_the_order(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $order = Order::query()->create([
            'number' => 'ORD-ACC-002',
            'status' => 'confirmed',
            'customer_name' => 'Клиент',
            'customer_phone' => '+375292222222',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 913,
            'total' => 1185,
        ]);
        $order->accountingRequests()->create([
            'requested_by_user_id' => $manager->id,
            'recipient_email' => 'buh-sbg@yandex.ru',
            'amount' => 913,
            'due_date' => '2026-10-22',
            'status' => 'sent',
            'sent_at' => now(),
            'comment' => 'Оплатить после подтверждения резерва.',
        ]);

        $this->actingAs($manager)
            ->get(OrderResource::getUrl('view', ['record' => $order], panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Передать счёт в бухгалтерию')
            ->assertSeeText('История счетов и переписки с бухгалтерией')
            ->assertSeeText('Передано в бухгалтерию')
            ->assertSeeText('buh-sbg@yandex.ru')
            ->assertSeeText('913.00 BYN')
            ->assertSeeText('Оплатить после подтверждения резерва.');
    }
}
