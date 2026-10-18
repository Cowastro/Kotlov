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
use App\Models\InstallRequest;
use App\Models\Order;
use App\Models\SupportConversation;
use App\Models\TelephonyCall;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class AdminTodayDashboard
{
    private const TIMEZONE = 'Europe/Minsk';

    /** @return array<string, mixed> */
    public function data(): array
    {
        $now = now();
        $localNow = $now->copy()->timezone(self::TIMEZONE);
        $dayStart = $localNow->copy()->startOfDay()->utc();
        $dayEnd = $localNow->copy()->endOfDay()->utc();

        $todayCounts = [
            'calls' => TelephonyCall::query()
                ->where('direction', 'inbound')
                ->whereBetween('started_at', [$dayStart, $dayEnd])
                ->count(),
            'orders' => Order::query()->whereBetween('created_at', [$dayStart, $dayEnd])->count(),
            'requests' => ContactRequest::query()->whereBetween('created_at', [$dayStart, $dayEnd])->count(),
            'installations' => InstallRequest::query()->whereBetween('created_at', [$dayStart, $dayEnd])->count(),
            'mail' => CrmMailThread::query()
                ->whereHas('messages', fn ($query) => $query
                    ->where('direction', 'incoming')
                    ->whereBetween('received_at', [$dayStart, $dayEnd]))
                ->count(),
            'chats' => SupportConversation::query()->whereBetween('created_at', [$dayStart, $dayEnd])->count(),
        ];

        $attentionCounts = [
            'calls' => TelephonyCall::query()->needsAttention()->count(),
            'orders' => Order::query()->whereNull('archived_at')->where('status', 'new')->count(),
            'requests' => ContactRequest::query()->whereIn('status', ['new', 'read'])->count(),
            'installations' => InstallRequest::query()->where('status', 'new')->count(),
            'mail' => CrmMailThread::query()->needsAttention()->count(),
            'chats' => SupportConversation::query()->where('status', 'new')->count(),
        ];

        $dueCalls = TelephonyCall::query()
            ->whereIn('handling_status', ['unprocessed', 'callback_required', 'in_progress'])
            ->whereNotNull('next_follow_up_at');
        $dueMail = CrmMailThread::query()
            ->whereNotIn('status', ['closed', 'spam'])
            ->whereNotNull('next_follow_up_at');

        return [
            'dateLabel' => $localNow->translatedFormat('j F, l'),
            'updatedAt' => $localNow->format('H:i'),
            'summary' => [
                'today' => array_sum($todayCounts),
                'attention' => array_sum($attentionCounts),
                'due' => (clone $dueCalls)->where('next_follow_up_at', '<=', $dayEnd)->count()
                    + (clone $dueMail)->where('next_follow_up_at', '<=', $dayEnd)->count(),
                'overdue' => (clone $dueCalls)->where('next_follow_up_at', '<', $now)->count()
                    + (clone $dueMail)->where('next_follow_up_at', '<', $now)->count(),
            ],
            'todayCounts' => $todayCounts,
            'attentionItems' => $this->attentionItems($now, $dayEnd),
            'todayItems' => $this->todayItems($dayStart, $dayEnd),
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
    private function attentionItems(CarbonInterface $now, CarbonInterface $dayEnd): Collection
    {
        $items = collect();

        TelephonyCall::query()
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

        CrmMailThread::query()
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

        TelephonyCall::query()
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

        ContactRequest::query()
            ->whereIn('status', ['new', 'read'])
            ->oldest()
            ->limit(20)
            ->get()
            ->each(fn (ContactRequest $request) => $items->push($this->item(
                key: 'request:'.$request->id,
                kind: 'Заявка с сайта',
                title: $request->name,
                subtitle: $this->compact([$request->phone, $request->product_name, $request->message]),
                time: $request->created_at,
                url: ContactRequestResource::getUrl('view', ['record' => $request]),
                tone: $request->status === 'new' ? 'danger' : 'warning',
                priority: 2,
            )));

        InstallRequest::query()
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
                priority: 2,
            )));

        Order::query()
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
                priority: 2,
            )));

        CrmMailThread::query()
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

        SupportConversation::query()
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
            ->take(14)
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function todayItems(CarbonInterface $dayStart, CarbonInterface $dayEnd): Collection
    {
        $items = collect();

        TelephonyCall::query()
            ->where('direction', 'inbound')
            ->whereBetween('started_at', [$dayStart, $dayEnd])
            ->latest('started_at')
            ->limit(24)
            ->get()
            ->each(fn (TelephonyCall $call) => $items->push($this->item(
                key: 'today-call:'.$call->id,
                kind: $call->status === 'missed' ? 'Пропущенный звонок' : 'Входящий звонок',
                title: $call->contact_name,
                subtitle: $call->customer_phone,
                time: $call->started_at,
                url: TelephonyCallResource::getUrl('view', ['record' => $call]),
                tone: $call->status === 'missed' ? 'danger' : 'info',
            )));

        ContactRequest::query()->whereBetween('created_at', [$dayStart, $dayEnd])->latest()->limit(24)->get()
            ->each(fn (ContactRequest $request) => $items->push($this->item(
                key: 'today-request:'.$request->id,
                kind: 'Заявка с сайта',
                title: $request->name,
                subtitle: $this->compact([$request->phone, $request->product_name]),
                time: $request->created_at,
                url: ContactRequestResource::getUrl('view', ['record' => $request]),
                tone: $request->status === 'done' ? 'success' : 'info',
            )));

        InstallRequest::query()->whereBetween('created_at', [$dayStart, $dayEnd])->latest()->limit(24)->get()
            ->each(fn (InstallRequest $request) => $items->push($this->item(
                key: 'today-install:'.$request->id,
                kind: 'Монтаж',
                title: $request->customer_name,
                subtitle: $this->compact([$request->customer_phone, $request->city]),
                time: $request->created_at,
                url: InstallRequestResource::getUrl('view', ['record' => $request]),
                tone: 'info',
            )));

        Order::query()->whereBetween('created_at', [$dayStart, $dayEnd])->latest()->limit(24)->get()
            ->each(fn (Order $order) => $items->push($this->item(
                key: 'today-order:'.$order->id,
                kind: 'Заказ',
                title: $order->number.' · '.$order->customer_name,
                subtitle: number_format((float) $order->total, 2, ',', ' ').' BYN',
                time: $order->created_at,
                url: OrderResource::getUrl('view', ['record' => $order]),
                tone: $order->status === 'new' ? 'warning' : 'success',
            )));

        CrmMailThread::query()
            ->whereHas('messages', fn ($query) => $query
                ->where('direction', 'incoming')
                ->whereBetween('received_at', [$dayStart, $dayEnd]))
            ->latest('last_message_at')
            ->limit(24)
            ->get()
            ->each(fn (CrmMailThread $thread) => $items->push($this->item(
                key: 'today-mail:'.$thread->id,
                kind: 'Письмо',
                title: $thread->subject ?: 'Письмо без темы',
                subtitle: $thread->participant_name ?: $thread->participant_email,
                time: $thread->last_message_at ?: $thread->created_at,
                url: CrmMailThreadResource::getUrl('view', ['record' => $thread]),
                tone: in_array($thread->status, ['new', 'waiting_manager'], true) ? 'warning' : 'info',
            )));

        SupportConversation::query()->whereBetween('created_at', [$dayStart, $dayEnd])->latest()->limit(24)->get()
            ->each(fn (SupportConversation $chat) => $items->push($this->item(
                key: 'today-chat:'.$chat->id,
                kind: 'Онлайн-чат',
                title: $chat->name,
                subtitle: $chat->phone,
                time: $chat->last_message_at ?: $chat->created_at,
                url: SupportConversationResource::getUrl('view', ['record' => $chat]),
                tone: $chat->status === 'new' ? 'warning' : 'success',
            )));

        return $items->sortByDesc('timeSort')->take(16)->values();
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
