<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\CrmTodayCenter;
use App\Models\ContactRequest;
use App\Models\CrmMailbox;
use App\Models\CrmMailMessage;
use App\Models\CrmMailThread;
use App\Models\InstallRequest;
use App\Models\Order;
use App\Models\SupportConversation;
use App\Models\TelephonyCall;
use App\Models\User;
use App\Services\Crm\AdminTodayDashboard;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTodayDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_home_contains_only_the_crm_today_widget(): void
    {
        $widgets = app(Dashboard::class)->getWidgets();

        $this->assertSame([CrmTodayCenter::class], $widgets);

        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);

        $this->actingAs($manager)
            ->get('/admin')
            ->assertOk()
            ->assertSee('CRM сегодня')
            ->assertSee('Не пропустить')
            ->assertSee('Поступления')
            ->assertSee('Управление')
            ->assertSee('Только незакрытые')
            ->assertDontSee('Состояние обменов');
    }

    public function test_it_collects_today_arrivals_and_due_actions_in_one_place(): void
    {
        Carbon::setTestNow('2026-10-18 08:00:00');

        TelephonyCall::query()->create([
            'external_uuid' => 'dashboard-call',
            'direction' => 'inbound',
            'customer_phone' => '+375291112233',
            'status' => 'missed',
            'handling_status' => 'callback_required',
            'started_at' => now()->subMinutes(30),
            'next_follow_up_at' => now()->subMinutes(10),
        ]);

        ContactRequest::query()->create([
            'name' => 'Иван',
            'phone' => '+375292223344',
            'message' => 'Нужна консультация',
            'status' => 'new',
        ]);

        InstallRequest::query()->create([
            'customer_name' => 'Анна',
            'customer_phone' => '+375293334455',
            'status' => 'new',
        ]);

        Order::query()->create([
            'number' => 'ORD-DASHBOARD',
            'status' => 'new',
            'customer_name' => 'Пётр',
            'customer_phone' => '+375294445566',
            'total' => 450,
        ]);

        SupportConversation::query()->create([
            'public_id' => fake()->uuid(),
            'session_hash' => hash('sha256', 'dashboard-chat'),
            'name' => 'Ольга',
            'phone' => '+375295556677',
            'status' => 'new',
            'last_message_at' => now()->subMinutes(5),
        ]);

        $mailbox = CrmMailbox::query()->firstOrFail();
        $thread = CrmMailThread::query()->create([
            'mailbox_id' => $mailbox->id,
            'thread_key' => 'dashboard-mail',
            'subject' => 'Запрос цены',
            'participant_email' => 'client@example.com',
            'status' => 'waiting_manager',
            'last_message_at' => now()->subMinutes(15),
            'next_follow_up_at' => now()->subMinutes(5),
        ]);
        CrmMailMessage::query()->create([
            'thread_id' => $thread->id,
            'mailbox_id' => $mailbox->id,
            'message_id' => 'dashboard-message',
            'direction' => 'incoming',
            'from_email' => 'client@example.com',
            'subject' => 'Запрос цены',
            'received_at' => now()->subMinutes(15),
        ]);

        $data = app(AdminTodayDashboard::class)->data(period: 'today', status: 'all');

        $this->assertSame(6, $data['summary']['today']);
        $this->assertSame(6, $data['summary']['attention']);
        $this->assertSame(2, $data['summary']['due']);
        $this->assertSame(2, $data['summary']['overdue']);
        $this->assertSame([
            'calls' => 1,
            'orders' => 1,
            'requests' => 1,
            'installations' => 1,
            'mail' => 1,
            'chats' => 1,
        ], $data['todayCounts']);
        $this->assertCount(6, $data['attentionItems']);
        $this->assertCount(6, $data['activityItems']);
        $this->assertSame('Перезвонить', $data['attentionItems']->first()['kind']);
        $this->assertStringContainsString('/admin/telephony-calls/', $data['attentionItems']->first()['url']);
    }

    public function test_open_items_from_yesterday_remain_visible_after_the_weekend(): void
    {
        Carbon::setTestNow('2026-10-19 08:00:00');

        $open = Order::query()->create([
            'number' => 'ORD-YESTERDAY-OPEN',
            'status' => 'new',
            'customer_name' => 'Открытый заказ',
            'customer_phone' => '+375290000001',
            'total' => 100,
        ]);
        $open->forceFill(['created_at' => now()->subDay()])->saveQuietly();

        $closed = Order::query()->create([
            'number' => 'ORD-YESTERDAY-CLOSED',
            'status' => 'delivered',
            'customer_name' => 'Закрытый заказ',
            'customer_phone' => '+375290000002',
            'total' => 100,
        ]);
        $closed->forceFill(['created_at' => now()->subDay()])->saveQuietly();

        $openData = app(AdminTodayDashboard::class)->data(period: 'yesterday', status: 'open');
        $closedData = app(AdminTodayDashboard::class)->data(period: 'yesterday', status: 'closed');

        $this->assertTrue($openData['attentionItems']->contains('title', $open->number.' · '.$open->customer_name));
        $this->assertSame(['ORD-YESTERDAY-OPEN · Открытый заказ'], $openData['activityItems']->pluck('title')->all());
        $this->assertSame(['ORD-YESTERDAY-CLOSED · Закрытый заказ'], $closedData['activityItems']->pluck('title')->all());
    }
}
