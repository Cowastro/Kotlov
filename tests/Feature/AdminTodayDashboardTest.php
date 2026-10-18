<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\CrmTodayCenter;
use App\Models\ContactRequest;
use App\Models\CrmMailbox;
use App\Models\CrmMailMessage;
use App\Models\CrmMailThread;
use App\Models\CrmReminder;
use App\Models\InstallRequest;
use App\Models\Order;
use App\Models\SupportConversation;
use App\Models\TelephonyCall;
use App\Models\User;
use App\Services\Crm\AdminTodayDashboard;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
            ->assertSee('Напоминания')
            ->assertSee('+ Добавить')
            ->assertSee('Сегодня')
            ->assertSee('Вчера')
            ->assertSee('Эта неделя')
            ->assertSee('Этот месяц')
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

    public function test_manager_sees_only_assigned_records_while_admin_sees_everything(): void
    {
        Carbon::setTestNow('2026-10-18 10:00:00');

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $otherManager = User::factory()->create(['role' => 'sales_manager', 'is_active' => true]);

        foreach ([
            ['ORD-MINE', $manager->id],
            ['ORD-OTHER', $otherManager->id],
            ['ORD-UNASSIGNED', null],
        ] as [$number, $managerId]) {
            Order::query()->create([
                'number' => $number,
                'status' => 'new',
                'customer_name' => $number,
                'customer_phone' => '+375290000000',
                'manager_id' => $managerId,
                'total' => 100,
            ]);
        }

        foreach ([
            ['call-mine', $manager->id],
            ['call-other', $otherManager->id],
            ['call-unassigned', null],
        ] as [$uuid, $assignedUserId]) {
            TelephonyCall::query()->create([
                'external_uuid' => $uuid,
                'direction' => 'inbound',
                'customer_phone' => '+375291111111',
                'status' => 'answered',
                'handling_status' => 'unprocessed',
                'assigned_user_id' => $assignedUserId,
                'started_at' => now(),
            ]);
        }

        $managerData = app(AdminTodayDashboard::class)->data(period: 'today', status: 'all', viewer: $manager);
        $adminData = app(AdminTodayDashboard::class)->data(period: 'today', status: 'all', viewer: $admin);

        $this->assertSame(1, $managerData['todayCounts']['orders']);
        $this->assertSame(1, $managerData['todayCounts']['calls']);
        $this->assertCount(2, $managerData['activityItems']);
        $this->assertTrue($managerData['activityItems']->contains('title', 'ORD-MINE · ORD-MINE'));
        $this->assertSame($manager->name, $managerData['activityItems']->firstWhere('title', 'ORD-MINE · ORD-MINE')['responsible']);
        $this->assertFalse($managerData['activityItems']->contains('title', 'ORD-OTHER · ORD-OTHER'));
        $this->assertSame(3, $adminData['todayCounts']['orders']);
        $this->assertSame(3, $adminData['todayCounts']['calls']);
        $this->assertCount(6, $adminData['activityItems']);
    }

    public function test_order_assigned_to_is_shown_when_manager_relation_is_missing(): void
    {
        Carbon::setTestNow('2026-10-18 10:00:00');

        Order::query()->create([
            'number' => 'ORD-TEXT-ASSIGNEE',
            'status' => 'new',
            'customer_name' => 'Заказ с текстовым ответственным',
            'customer_phone' => '+375290000000',
            'manager_id' => null,
            'assigned_to' => 'Денис Готто',
            'total' => 100,
        ]);

        $data = app(AdminTodayDashboard::class)->data(period: 'today', status: 'all');
        $title = 'ORD-TEXT-ASSIGNEE · Заказ с текстовым ответственным';

        $this->assertSame('Денис Готто', $data['attentionItems']->firstWhere('title', $title)['responsible']);
        $this->assertSame('Денис Готто', $data['activityItems']->firstWhere('title', $title)['responsible']);
    }

    public function test_manager_can_create_and_complete_only_an_assigned_quick_reminder(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $otherManager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $this->actingAs($manager);

        Livewire::test(CrmTodayCenter::class)
            ->set('reminderTitle', 'Перезвонить клиенту')
            ->set('reminderNote', 'Уточнить объём бака')
            ->set('reminderDueAt', '2026-10-19T10:30')
            ->set('reminderAssignedUserId', $otherManager->id)
            ->call('saveQuickReminder')
            ->assertHasNoErrors();

        $reminder = CrmReminder::query()->firstOrFail();
        $this->assertSame($manager->id, $reminder->assigned_user_id);
        $this->assertSame('Уточнить объём бака', $reminder->note);

        Livewire::test(CrmTodayCenter::class)
            ->call('completeReminder', $reminder->id);

        $this->assertNotNull($reminder->fresh()->completed_at);
        $this->assertSame($manager->id, $reminder->fresh()->completed_by);
    }

    public function test_admin_sees_all_reminders_and_can_leave_one_unassigned(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);

        CrmReminder::query()->create([
            'title' => 'Задача менеджера',
            'assigned_user_id' => $manager->id,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin);

        Livewire::test(CrmTodayCenter::class)
            ->set('reminderTitle', 'Распределить новые заявки')
            ->call('saveQuickReminder')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('crm_reminders', [
            'title' => 'Распределить новые заявки',
            'assigned_user_id' => null,
            'created_by' => $admin->id,
        ]);
        $this->assertCount(2, app(AdminTodayDashboard::class)->data(viewer: $admin)['reminders']);
        $this->assertCount(1, app(AdminTodayDashboard::class)->data(viewer: $manager)['reminders']);
    }

    public function test_calendar_week_and_month_periods_have_correct_boundaries(): void
    {
        Carbon::setTestNow('2026-10-18 10:00:00');

        foreach ([
            ['ORD-WEEK', '2026-10-12 08:00:00'],
            ['ORD-MONTH', '2026-10-05 08:00:00'],
            ['ORD-PREVIOUS', '2026-09-30 08:00:00'],
        ] as [$number, $createdAt]) {
            $order = Order::query()->create([
                'number' => $number,
                'status' => 'new',
                'customer_name' => $number,
                'customer_phone' => '+375290000000',
                'total' => 100,
            ]);
            $order->forceFill(['created_at' => $createdAt])->saveQuietly();
        }

        $week = app(AdminTodayDashboard::class)->data(period: 'week');
        $month = app(AdminTodayDashboard::class)->data(period: 'month');

        $this->assertSame('Эта неделя', $week['rangeLabel']);
        $this->assertSame(['ORD-WEEK · ORD-WEEK'], $week['activityItems']->pluck('title')->all());
        $this->assertSame('Этот месяц', $month['rangeLabel']);
        $this->assertEqualsCanonicalizing(
            ['ORD-WEEK · ORD-WEEK', 'ORD-MONTH · ORD-MONTH'],
            $month['activityItems']->pluck('title')->all(),
        );
    }
}
