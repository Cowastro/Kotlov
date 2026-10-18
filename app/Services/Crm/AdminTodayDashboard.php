<?php

namespace App\Services\Crm;

use App\Filament\Resources\ContactRequests\ContactRequestResource;
use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Filament\Resources\InstallRequests\InstallRequestResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\SupportConversations\SupportConversationResource;
use App\Filament\Resources\TelephonyCalls\TelephonyCallResource;
use App\Models\ContactRequest;
use App\Models\CrmMailThread;
use App\Models\CrmReminder;
use App\Models\InstallRequest;
use App\Models\Order;
use App\Models\SupportConversation;
use App\Models\TelephonyCall;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AdminTodayDashboard
{
    private const TIMEZONE = 'Europe/Minsk';

    /** @return array<string, mixed> */
    public function data(
        string $period = 'week',
        string $status = 'open',
        string $sort = 'desc',
        ?User $viewer = null,
    ): array {
        $period = in_array($period, ['today', 'yesterday', 'week', 'month', 'all'], true) ? $period : 'week';
        $status = in_array($status, ['open', 'closed', 'all'], true) ? $status : 'open';
        $sort = $sort === 'asc' ? 'asc' : 'desc';

        $now = now();
        $localNow = $now->copy()->timezone(self::TIMEZONE);
        $dayStart = $localNow->copy()->startOfDay()->utc();
        $dayEnd = $localNow->copy()->endOfDay()->utc();
        [$rangeStart, $rangeEnd, $rangeLabel] = $this->activityRange($localNow, $period);
        $managerId = $viewer?->isOperationalManager() === true ? $viewer->id : null;

        $todayCounts = [
            'calls' => $this->calls($managerId)
                ->where('direction', 'inbound')
                ->whereBetween('started_at', [$dayStart, $dayEnd])
                ->count(),
            'orders' => $this->orders($managerId)->whereBetween('created_at', [$dayStart, $dayEnd])->count(),
            'requests' => $this->requests($managerId)->whereBetween('created_at', [$dayStart, $dayEnd])->count(),
            'installations' => $this->installations($managerId)->whereBetween('created_at', [$dayStart, $dayEnd])->count(),
            'mail' => $this->mail($managerId)
                ->whereHas('messages', fn ($query) => $query
                    ->where('direction', 'incoming')
                    ->whereBetween('received_at', [$dayStart, $dayEnd]))
                ->count(),
            'chats' => $this->chats($managerId)->whereBetween('created_at', [$dayStart, $dayEnd])->count(),
        ];

        $attentionCounts = [
            'calls' => $this->calls($managerId)->needsAttention()->count(),
            'orders' => $this->orders($managerId)->whereNull('archived_at')->where('status', 'new')->count(),
            'requests' => $this->requests($managerId)->whereIn('status', ContactRequest::OPEN_STATUSES)->count(),
            'installations' => $this->installations($managerId)->where('status', 'new')->count(),
            'mail' => $this->mail($managerId)->needsAttention()->count(),
            'chats' => $this->chats($managerId)->where('status', 'new')->count(),
        ];

        $dueCalls = $this->calls($managerId)
            ->whereIn('handling_status', ['unprocessed', 'callback_required', 'in_progress'])
            ->whereNotNull('next_follow_up_at');
        $dueMail = $this->mail($managerId)
            ->whereNotIn('status', ['closed', 'spam'])
            ->whereNotNull('next_follow_up_at');
        $dueRequests = $this->requests($managerId)
            ->whereIn('status', ContactRequest::OPEN_STATUSES)
            ->whereNotNull('next_follow_up_at');

        $activity = $this->activityItems($rangeStart, $rangeEnd, $status, $sort, $managerId);

        return [
            'dateLabel' => $localNow->translatedFormat('j F, l'),
            'updatedAt' => $localNow->format('H:i'),
            'summary' => [
                'today' => array_sum($todayCounts),
                'attention' => array_sum($attentionCounts),
                'due' => (clone $dueCalls)->where('next_follow_up_at', '<=', $dayEnd)->count()
                    + (clone $dueMail)->where('next_follow_up_at', '<=', $dayEnd)->count()
                    + (clone $dueRequests)->where('next_follow_up_at', '<=', $dayEnd)->count(),
                'overdue' => (clone $dueCalls)->where('next_follow_up_at', '<', $now)->count()
                    + (clone $dueMail)->where('next_follow_up_at', '<', $now)->count()
                    + (clone $dueRequests)->where('next_follow_up_at', '<', $now)->count(),
            ],
            'todayCounts' => $todayCounts,
            'attentionItems' => $this->attentionItems($now, $dayEnd, $managerId),
            'activityItems' => $activity,
            'activityCount' => $activity->count(),
            'rangeLabel' => $rangeLabel,
            'reminders' => $this->reminders($viewer),
            'canAssignReminders' => $viewer?->isAdmin() === true,
            'managerOptions' => $viewer?->isAdmin() === true
                ? User::query()
                    ->whereIn('role', ['admin', 'manager', 'sales_manager'])
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                : collect(),
            'links' => [
                ['label' => 'Звонки', 'url' => TelephonyCallResource::getUrl('index')],
                ['label' => 'Заказы', 'url' => OrderResource::getUrl('index')],
                ['label' => 'Заявки', 'url' => ContactRequestResource::getUrl('index')],
                ['label' => 'Монтаж', 'url' => InstallRequestResource::getUrl('index')],
                ['label' => 'Почта', 'url' => CrmMailThreadResource::getUrl('index')],
                ['label' => 'Чат', 'url' => SupportConversationResource::getUrl('index')],
            ],
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function attentionItems(CarbonInterface $now, CarbonInterface $dayEnd, ?int $managerId): Collection
    {
        $items = collect();

        $this->calls($managerId)
            ->with('assignedUser')
            ->whereIn('handling_status', ['unprocessed', 'callback_required', 'in_progress'])
            ->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<=', $dayEnd)
            ->oldest('next_follow_up_at')
            ->limit(20)
            ->get()
            ->each(fn (TelephonyCall $call) => $items->push($this->item(
                key: 'call:'.$call->id,
                kind: 'Перезвонить',
                title: $call->contact_name,
                subtitle: $this->compact([$call->customer_phone, $call->manager_note]),
                time: $call->next_follow_up_at,
                url: TelephonyCallResource::getUrl('view', ['record' => $call]),
                tone: $call->next_follow_up_at?->lt($now) ? 'danger' : 'warning',
                responsible: $call->assignedUser?->name,
                priority: $call->next_follow_up_at?->lt($now) ? 0 : 1,
            )));

        $this->mail($managerId)
            ->with('assignedUser')
            ->whereNotIn('status', ['closed', 'spam'])
            ->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<=', $dayEnd)
            ->oldest('next_follow_up_at')
            ->limit(20)
            ->get()
            ->each(fn (CrmMailThread $thread) => $items->push($this->item(
                key: 'mail:'.$thread->id,
                kind: 'Напомнить по письму',
                title: $thread->subject ?: 'Переписка без темы',
                subtitle: $this->compact([$thread->participant_name ?: $thread->participant_email, $thread->follow_up_note]),
                time: $thread->next_follow_up_at,
                url: CrmMailThreadResource::getUrl('view', ['record' => $thread]),
                tone: $thread->next_follow_up_at?->lt($now) ? 'danger' : 'warning',
                responsible: $thread->assignedUser?->name,
                priority: $thread->next_follow_up_at?->lt($now) ? 0 : 1,
            )));

        $this->calls($managerId)
            ->with('assignedUser')
            ->needsAttention()
            ->oldest('started_at')
            ->limit(20)
            ->get()
            ->each(fn (TelephonyCall $call) => $items->push($this->item(
                key: 'call:'.$call->id,
                kind: $call->status === 'missed' ? 'Пропущенный звонок' : 'Звонок',
                title: $call->contact_name,
                subtitle: $this->compact([$call->customer_phone, TelephonyCall::HANDLING_STATUSES[$call->handling_status] ?? null]),
                time: $call->started_at ?: $call->created_at,
                url: TelephonyCallResource::getUrl('view', ['record' => $call]),
                tone: $call->status === 'missed' ? 'danger' : 'warning',
                responsible: $call->assignedUser?->name,
                priority: 2,
            )));

        $this->requests($managerId)
            ->with(['responsibleUser', 'telephonyCalls.assignedUser'])
            ->whereIn('status', ContactRequest::OPEN_STATUSES)
            ->oldest()
            ->limit(20)
            ->get()
            ->each(fn (ContactRequest $request) => $items->push($this->item(
                key: 'request:'.$request->id,
                kind: $request->next_follow_up_at ? 'Напомнить по заявке' : 'Заявка с сайта',
                title: $request->name,
                subtitle: $this->compact([$request->phone, $request->product_name, $request->message]),
                time: $request->next_follow_up_at ?: $request->created_at,
                url: ContactRequestResource::getUrl('view', ['record' => $request]),
                tone: $request->status === 'new' || $request->next_follow_up_at?->lt($now) ? 'danger' : 'warning',
                responsible: $request->responsibleUser?->name ?: $request->telephonyCalls->first()?->assignedUser?->name,
                priority: $request->next_follow_up_at?->lt($now) ? 0 : 2,
            )));

        $this->installations($managerId)
            ->with('installer')
            ->where('status', 'new')
            ->oldest()
            ->limit(20)
            ->get()
            ->each(fn (InstallRequest $request) => $items->push($this->item(
                key: 'install:'.$request->id,
                kind: 'Заявка на монтаж',
                title: $request->customer_name,
                subtitle: $this->compact([$request->customer_phone, $request->city, $request->description]),
                time: $request->created_at,
                url: InstallRequestResource::getUrl('view', ['record' => $request]),
                tone: 'danger',
                responsible: $request->installer?->name,
                priority: 2,
            )));

        $this->orders($managerId)
            ->with('manager')
            ->whereNull('archived_at')
            ->where('status', 'new')
            ->oldest()
            ->limit(20)
            ->get()
            ->each(fn (Order $order) => $items->push($this->item(
                key: 'order:'.$order->id,
                kind: 'Новый заказ',
                title: $order->number.' · '.$order->customer_name,
                subtitle: $this->compact([$order->customer_phone, number_format((float) $order->total, 2, ',', ' ').' BYN']),
                time: $order->created_at,
                url: OrderResource::getUrl('view', ['record' => $order]),
                tone: 'danger',
                responsible: $this->orderResponsible($order),
                priority: 2,
            )));

        $this->mail($managerId)
            ->with('assignedUser')
            ->needsAttention()
            ->oldest('last_message_at')
            ->limit(20)
            ->get()
            ->each(fn (CrmMailThread $thread) => $items->push($this->item(
                key: 'mail:'.$thread->id,
                kind: 'Нужно ответить',
                title: $thread->subject ?: 'Письмо без темы',
                subtitle: $thread->participant_name ?: $thread->participant_email,
                time: $thread->last_message_at ?: $thread->created_at,
                url: CrmMailThreadResource::getUrl('view', ['record' => $thread]),
                tone: 'danger',
                responsible: $thread->assignedUser?->name,
                priority: 2,
            )));

        $this->chats($managerId)
            ->with('assignedUser')
            ->where('status', 'new')
            ->oldest('last_message_at')
            ->limit(20)
            ->get()
            ->each(fn (SupportConversation $chat) => $items->push($this->item(
                key: 'chat:'.$chat->id,
                kind: 'Онлайн-чат',
                title: $chat->name,
                subtitle: $chat->phone,
                time: $chat->last_message_at ?: $chat->created_at,
                url: SupportConversationResource::getUrl('view', ['record' => $chat]),
                tone: 'danger',
                responsible: $chat->assignedUser?->name,
                priority: 2,
            )));

        return $items
            ->unique('key')
            ->sortBy(fn (array $item): string => sprintf('%02d-%s', $item['priority'], $item['timeSort']))
            ->take(60)
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function activityItems(
        CarbonInterface $rangeStart,
        CarbonInterface $rangeEnd,
        string $status,
        string $sort,
        ?int $managerId,
    ): Collection {
        $items = collect();

        $calls = $this->calls($managerId)
            ->with('assignedUser')
            ->where('direction', 'inbound')
            ->whereBetween('started_at', [$rangeStart, $rangeEnd]);
        $this->applyStatus($calls, $status, 'handling_status', ['handled', 'irrelevant']);
        $calls->latest('started_at')->limit(80)
            ->get()
            ->each(fn (TelephonyCall $call) => $items->push($this->item(
                key: 'activity-call:'.$call->id,
                kind: $call->status === 'missed' ? 'Пропущенный звонок' : 'Входящий звонок',
                title: $call->contact_name,
                subtitle: $call->customer_phone,
                time: $call->started_at,
                url: TelephonyCallResource::getUrl('view', ['record' => $call]),
                tone: $call->status === 'missed' ? 'danger' : 'info',
                closed: in_array($call->handling_status, ['handled', 'irrelevant'], true),
                statusLabel: TelephonyCall::HANDLING_STATUSES[$call->handling_status] ?? $call->handling_status,
                responsible: $call->assignedUser?->name,
            )));

        $requests = $this->requests($managerId)
            ->with(['responsibleUser', 'telephonyCalls.assignedUser'])
            ->whereBetween('created_at', [$rangeStart, $rangeEnd]);
        $this->applyStatus($requests, $status, 'status', ['done', 'cancelled']);
        $requests->latest()->limit(80)->get()
            ->each(fn (ContactRequest $request) => $items->push($this->item(
                key: 'activity-request:'.$request->id,
                kind: 'Заявка с сайта',
                title: $request->name,
                subtitle: $this->compact([$request->phone, $request->product_name]),
                time: $request->created_at,
                url: ContactRequestResource::getUrl('view', ['record' => $request]),
                tone: in_array($request->status, ['done', 'cancelled'], true) ? 'success' : 'info',
                closed: in_array($request->status, ['done', 'cancelled'], true),
                statusLabel: ContactRequest::$statuses[$request->status] ?? $request->status,
                responsible: $request->responsibleUser?->name ?: $request->telephonyCalls->first()?->assignedUser?->name,
            )));

        $installations = $this->installations($managerId)
            ->with('installer')
            ->whereBetween('created_at', [$rangeStart, $rangeEnd]);
        $this->applyStatus($installations, $status, 'status', ['done', 'cancelled']);
        $installations->latest()->limit(80)->get()
            ->each(fn (InstallRequest $request) => $items->push($this->item(
                key: 'activity-install:'.$request->id,
                kind: 'Монтаж',
                title: $request->customer_name,
                subtitle: $this->compact([$request->customer_phone, $request->city]),
                time: $request->created_at,
                url: InstallRequestResource::getUrl('view', ['record' => $request]),
                tone: in_array($request->status, ['done', 'cancelled'], true) ? 'success' : 'info',
                closed: in_array($request->status, ['done', 'cancelled'], true),
                statusLabel: InstallRequest::STATUSES[$request->status] ?? $request->status,
                responsible: $request->installer?->name,
            )));

        $orders = $this->orders($managerId)
            ->with('manager')
            ->whereNull('archived_at')
            ->whereBetween('created_at', [$rangeStart, $rangeEnd]);
        $this->applyStatus($orders, $status, 'status', ['delivered', 'completed', 'cancelled']);
        $orders->latest()->limit(80)->get()
            ->each(fn (Order $order) => $items->push($this->item(
                key: 'activity-order:'.$order->id,
                kind: 'Заказ',
                title: $order->number.' · '.$order->customer_name,
                subtitle: number_format((float) $order->total, 2, ',', ' ').' BYN',
                time: $order->created_at,
                url: OrderResource::getUrl('view', ['record' => $order]),
                tone: $order->status === 'new' ? 'warning' : 'success',
                closed: in_array($order->status, ['delivered', 'completed', 'cancelled'], true),
                statusLabel: Order::STATUSES[$order->status] ?? $order->status,
                responsible: $this->orderResponsible($order),
            )));

        $mail = $this->mail($managerId)
            ->with('assignedUser')
            ->whereHas('messages', fn ($query) => $query
                ->where('direction', 'incoming')
                ->whereBetween('received_at', [$rangeStart, $rangeEnd]));
        $this->applyStatus($mail, $status, 'status', ['closed', 'spam']);
        $mail->latest('last_message_at')->limit(80)
            ->get()
            ->each(fn (CrmMailThread $thread) => $items->push($this->item(
                key: 'activity-mail:'.$thread->id,
                kind: 'Письмо',
                title: $thread->subject ?: 'Письмо без темы',
                subtitle: $thread->participant_name ?: $thread->participant_email,
                time: $thread->last_message_at ?: $thread->created_at,
                url: CrmMailThreadResource::getUrl('view', ['record' => $thread]),
                tone: in_array($thread->status, ['new', 'waiting_manager'], true) ? 'warning' : 'info',
                closed: in_array($thread->status, ['closed', 'spam'], true),
                statusLabel: CrmMailThread::STATUSES[$thread->status] ?? $thread->status,
                responsible: $thread->assignedUser?->name,
            )));

        $chats = $this->chats($managerId)
            ->with('assignedUser')
            ->whereBetween('created_at', [$rangeStart, $rangeEnd]);
        $this->applyStatus($chats, $status, 'status', ['closed']);
        $chats->latest()->limit(80)->get()
            ->each(fn (SupportConversation $chat) => $items->push($this->item(
                key: 'activity-chat:'.$chat->id,
                kind: 'Онлайн-чат',
                title: $chat->name,
                subtitle: $chat->phone,
                time: $chat->last_message_at ?: $chat->created_at,
                url: SupportConversationResource::getUrl('view', ['record' => $chat]),
                tone: $chat->status === 'new' ? 'warning' : 'success',
                closed: $chat->status === 'closed',
                statusLabel: SupportConversation::STATUSES[$chat->status] ?? $chat->status,
                responsible: $chat->assignedUser?->name,
            )));

        return ($sort === 'asc' ? $items->sortBy('timeSort') : $items->sortByDesc('timeSort'))
            ->take(100)
            ->values();
    }

    /** @return array{CarbonInterface, CarbonInterface, string} */
    private function activityRange(CarbonInterface $localNow, string $period): array
    {
        $end = $localNow->copy()->endOfDay();

        [$start, $label] = match ($period) {
            'today' => [$localNow->copy()->startOfDay(), 'Сегодня'],
            'yesterday' => [$localNow->copy()->subDay()->startOfDay(), 'Вчера'],
            'month' => [$localNow->copy()->startOfMonth(), 'Этот месяц'],
            'all' => [$localNow->copy()->subYears(20)->startOfDay(), 'За всё время'],
            default => [$localNow->copy()->startOfWeek(CarbonInterface::MONDAY), 'Эта неделя'],
        };

        if ($period === 'yesterday') {
            $end = $localNow->copy()->subDay()->endOfDay();
        }

        return [$start->utc(), $end->utc(), $label];
    }

    /** @param \Illuminate\Database\Eloquent\Builder<*> $query */
    private function applyStatus($query, string $status, string $column, array $closedStatuses): void
    {
        if ($status === 'open') {
            $query->whereNotIn($column, $closedStatuses);
        } elseif ($status === 'closed') {
            $query->whereIn($column, $closedStatuses);
        }
    }

    /** @return Builder<TelephonyCall> */
    private function calls(?int $managerId): Builder
    {
        return TelephonyCall::query()->when(
            $managerId,
            fn (Builder $query, int $id): Builder => $query->where('assigned_user_id', $id),
        );
    }

    /** @return Builder<Order> */
    private function orders(?int $managerId): Builder
    {
        return Order::query()->when(
            $managerId,
            fn (Builder $query, int $id): Builder => $query->where('manager_id', $id),
        );
    }

    private function orderResponsible(Order $order): ?string
    {
        return $order->manager?->name ?: (filled($order->assigned_to) ? $order->assigned_to : null);
    }

    /** @return Builder<ContactRequest> */
    private function requests(?int $managerId): Builder
    {
        return ContactRequest::query()->when(
            $managerId,
            fn (Builder $query, int $id): Builder => $query->where(function (Builder $query) use ($id): void {
                $query->where('responsible_user_id', $id)
                    ->orWhereExists(
                        TelephonyCall::query()
                            ->selectRaw('1')
                            ->whereColumn('telephony_calls.contact_request_id', 'contact_requests.id')
                            ->where('telephony_calls.assigned_user_id', $id),
                    );
            }),
        );
    }

    /** @return Builder<InstallRequest> */
    private function installations(?int $managerId): Builder
    {
        return InstallRequest::query()->when(
            $managerId,
            fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
        );
    }

    /** @return Builder<CrmMailThread> */
    private function mail(?int $managerId): Builder
    {
        return CrmMailThread::query()->when(
            $managerId,
            fn (Builder $query, int $id): Builder => $query->where('assigned_user_id', $id),
        );
    }

    /** @return Builder<SupportConversation> */
    private function chats(?int $managerId): Builder
    {
        return SupportConversation::query()->when(
            $managerId,
            fn (Builder $query, int $id): Builder => $query->where('assigned_user_id', $id),
        );
    }

    /** @return Collection<int, array<string, mixed>> */
    private function reminders(?User $viewer): Collection
    {
        $now = now();

        return CrmReminder::query()
            ->with(['assignedUser', 'creator'])
            ->whereNull('completed_at')
            ->when(
                $viewer?->isOperationalManager() === true,
                fn (Builder $query): Builder => $query->where('assigned_user_id', $viewer->id),
            )
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(function (CrmReminder $reminder) use ($now): array {
                $dueAt = $reminder->due_at?->copy()->timezone(self::TIMEZONE);

                return [
                    'id' => $reminder->id,
                    'title' => $reminder->title,
                    'note' => $reminder->note,
                    'due' => $dueAt?->format('d.m.Y H:i'),
                    'overdue' => $reminder->due_at?->lt($now) === true,
                    'responsible' => $reminder->assignedUser?->name,
                    'creator' => $reminder->creator?->name,
                ];
            });
    }

    /** @return array<string, mixed> */
    private function item(
        string $key,
        string $kind,
        string $title,
        ?string $subtitle,
        ?CarbonInterface $time,
        string $url,
        string $tone,
        ?string $responsible = null,
        int $priority = 9,
        bool $closed = false,
        ?string $statusLabel = null,
    ): array {
        $time = $time ?: now();
        $localTime = $time->copy()->timezone(self::TIMEZONE);

        return [
            'key' => $key,
            'kind' => $kind,
            'title' => $title,
            'subtitle' => $subtitle,
            'time' => $localTime->isToday() ? $localTime->format('H:i') : $localTime->format('d.m H:i'),
            'timeSort' => $time->utc()->format('Y-m-d H:i:s.u'),
            'url' => $url,
            'tone' => $tone,
            'responsible' => $responsible,
            'priority' => $priority,
            'closed' => $closed,
            'statusLabel' => $statusLabel,
        ];
    }

    /** @param array<int, mixed> $parts */
    private function compact(array $parts): ?string
    {
        $value = collect($parts)
            ->filter(fn ($part): bool => filled($part))
            ->map(fn ($part): string => trim((string) $part))
            ->implode(' · ');

        return $value !== '' ? str($value)->limit(150)->toString() : null;
    }
}
