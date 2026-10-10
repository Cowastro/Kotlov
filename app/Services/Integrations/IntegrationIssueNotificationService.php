<?php

namespace App\Services\Integrations;

use App\Filament\Resources\IntegrationIssues\IntegrationIssueResource;
use App\Models\IntegrationIssue;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class IntegrationIssueNotificationService
{
    /** @var array<int, string> */
    private const NOTIFIABLE_TYPES = [
        'integration_stale',
        'integration_catalog_stale',
        'integration_orders_stale',
        'integration_statuses_stale',
        'order_not_exported',
        'order_no_1c_response',
        'product_identity_collision',
    ];

    /** @param array<int, int> $issueIds */
    public function notifyOpened(array $issueIds): int
    {
        if ($issueIds === []) {
            return 0;
        }

        $issues = IntegrationIssue::query()
            ->whereIn('id', $issueIds)
            ->where('status', 'open')
            ->whereIn('type', self::NOTIFIABLE_TYPES)
            ->get();

        if ($issues->isEmpty()) {
            return 0;
        }

        $admins = User::query()
            ->where('role', 'admin')
            ->where('is_active', true)
            ->get();

        if ($admins->isEmpty()) {
            return 0;
        }

        $counts = $issues->countBy(fn (IntegrationIssue $issue): string => match (true) {
            in_array($issue->type, IntegrationIssue::EXCHANGE_TYPES, true) => 'exchange',
            in_array($issue->type, IntegrationIssue::ORDER_TYPES, true) => 'orders',
            default => 'duplicates',
        });
        $details = collect([
            'обмен: '.(int) ($counts['exchange'] ?? 0),
            'заказы: '.(int) ($counts['orders'] ?? 0),
            'возможные дубли: '.(int) ($counts['duplicates'] ?? 0),
        ])->filter(fn (string $value): bool => ! str_ends_with($value, ': 0'))->implode(' · ');
        $hasDanger = $issues->contains(fn (IntegrationIssue $issue): bool => $issue->severity === 'danger');

        $notification = Notification::make()
            ->title($issues->count() === 1
                ? $issues->first()->title
                : 'Интеграция требует внимания: '.$issues->count())
            ->body($details)
            ->icon('heroicon-o-exclamation-triangle')
            ->iconColor($hasDanger ? 'danger' : 'warning')
            ->actions([
                Action::make('openIntegrationIssues')
                    ->label('Открыть очередь')
                    ->url(IntegrationIssueResource::getUrl('index', ['tab' => 'priority']))
                    ->markAsRead(),
            ]);

        $hasDanger ? $notification->danger() : $notification->warning();
        // Monitoring must stay reliable even on hosting without a persistent
        // queue worker. This is one small database notification per admin and
        // only for newly opened high-signal issues, so send it synchronously.
        $admins->each(fn (User $admin) => $admin->notifyNow($notification->toDatabase()));

        return $issues->count();
    }
}
