<?php

namespace Tests\Feature;

use App\Models\IntegrationIssue;
use App\Models\User;
use App\Services\Integrations\IntegrationIssueNotificationService;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class IntegrationIssueNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_one_aggregated_notification_only_for_important_new_issues(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $inactiveAdmin = User::factory()->create(['role' => 'admin', 'is_active' => false]);
        $important = IntegrationIssue::query()->create([
            'fingerprint' => 'notification-order',
            'type' => 'order_not_exported',
            'severity' => 'danger',
            'status' => 'open',
            'title' => 'Заказ не передан в 1С',
            'first_detected_at' => now(),
            'last_detected_at' => now(),
        ]);
        $routine = IntegrationIssue::query()->create([
            'fingerprint' => 'notification-product',
            'type' => 'product_attention',
            'severity' => 'warning',
            'status' => 'open',
            'title' => 'Товар не привязан',
            'first_detected_at' => now(),
            'last_detected_at' => now(),
        ]);

        $count = app(IntegrationIssueNotificationService::class)
            ->notifyOpened([$important->id, $routine->id]);

        $this->assertSame(1, $count);
        Notification::assertSentTo(
            $admin,
            DatabaseNotification::class,
            fn (DatabaseNotification $notification): bool => $notification->data['title'] === 'Заказ не передан в 1С'
                && str_contains((string) $notification->data['body'], 'заказы: 1')
                && count($notification->data['actions']) === 1
                && str_contains((string) data_get($notification->data, 'actions.0.url'), 'tab=priority'),
        );
        Notification::assertNotSentTo($inactiveAdmin, DatabaseNotification::class);
    }

    public function test_it_does_not_notify_for_routine_product_queue_items(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $routine = IntegrationIssue::query()->create([
            'fingerprint' => 'notification-routine-only',
            'type' => 'product_attention',
            'severity' => 'danger',
            'status' => 'open',
            'title' => 'Товар без цены',
            'first_detected_at' => now(),
            'last_detected_at' => now(),
        ]);

        $count = app(IntegrationIssueNotificationService::class)->notifyOpened([$routine->id]);

        $this->assertSame(0, $count);
        Notification::assertNothingSentTo($admin);
    }
}
