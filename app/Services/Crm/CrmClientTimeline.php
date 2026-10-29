<?php

namespace App\Services\Crm;

use App\Models\CommercialDocument;
use App\Models\CrmCounterparty;
use App\Models\CrmMailMessage;
use App\Models\CrmMailThread;
use App\Models\Order;
use App\Models\TelephonyCall;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class CrmClientTimeline
{
    /** @return Collection<int, array<string, mixed>> */
    public function for(CrmCounterparty $counterparty, ?User $user): Collection
    {
        $events = collect();
        $emails = $counterparty->loadMissing('contactPoints')->contactPoints
            ->where('type', 'email')
            ->where('is_active', true)
            ->pluck('value')
            ->map(fn (string $email): string => mb_strtolower(trim($email)))
            ->filter()
            ->unique()
            ->values();
        $threadIds = CrmMailThread::query()
            ->visibleTo($user)
            ->where(function ($query) use ($counterparty, $emails): void {
                $query->where('crm_counterparty_id', $counterparty->id);

                if ($emails->isNotEmpty()) {
                    $query->orWhereIn(DB::raw('LOWER(participant_email)'), $emails);
                }
            })
            ->pluck('id');

        if ($threadIds->isNotEmpty()) {
            CrmMailMessage::query()
                ->visibleTo($user)
                ->whereIn('thread_id', $threadIds)
                ->with(['thread:id,subject', 'sentBy:id,name'])
                ->orderByDesc('received_at')
                ->orderByDesc('id')
                ->get()
                ->each(function (CrmMailMessage $message) use ($events): void {
                    $incoming = $message->direction === 'incoming';
                    $directionLabel = match ($message->direction) {
                        'incoming' => 'Входящее письмо: ',
                        'draft' => 'Черновик письма: ',
                        default => 'Исходящее письмо: ',
                    };
                    $events->push([
                        'type' => 'mail',
                        'tone' => $incoming ? 'warning' : 'info',
                        'icon' => $incoming ? 'heroicon-o-arrow-down-tray' : 'heroicon-o-paper-airplane',
                        'date' => $message->received_at ?? $message->sent_at ?? $message->created_at,
                        'title' => $directionLabel.($message->subject ?: $message->thread?->subject ?: 'Без темы'),
                        'summary' => $this->mailPreview($message),
                        'meta' => $incoming
                            ? ($message->from_name ?: $message->from_email)
                            : ($message->sentBy?->name ?: 'Сотрудник'),
                        'url_type' => 'mail',
                        'url_id' => $message->thread_id,
                    ]);
                });

            CrmMailThread::query()
                ->visibleTo($user)
                ->whereIn('id', $threadIds)
                ->where(fn ($query) => $query->whereNotNull('manager_note')->orWhereNotNull('follow_up_note'))
                ->with('assignedUser:id,name')
                ->get()
                ->each(function (CrmMailThread $thread) use ($events): void {
                    $events->push([
                        'type' => 'manager_action',
                        'tone' => 'gray',
                        'icon' => 'heroicon-o-clipboard-document-check',
                        'date' => $thread->updated_at,
                        'title' => 'Действие менеджера по переписке «'.$thread->subject.'»',
                        'summary' => $thread->manager_note ?: $thread->follow_up_note,
                        'meta' => $thread->assignedUser?->name,
                        'url_type' => 'mail',
                        'url_id' => $thread->id,
                    ]);
                });
        }

        $documents = CommercialDocument::query()
            ->where(function ($query) use ($counterparty, $threadIds): void {
                $query->where('crm_counterparty_id', $counterparty->id);

                if ($threadIds->isNotEmpty()) {
                    $query->orWhereIn('crm_mail_thread_id', $threadIds);
                }
            })
            ->with('manager:id,name')
            ->get();

        $documents->each(function (CommercialDocument $document) use ($events): void {
            $events->push([
                'type' => 'document',
                'tone' => in_array($document->status, ['accepted', 'paid'], true) ? 'success' : 'primary',
                'icon' => 'heroicon-o-document-currency-dollar',
                'date' => $document->sent_at ?? $document->issued_at ?? $document->created_at,
                'title' => (CommercialDocument::TYPES[$document->type] ?? 'Документ').' '.$document->reference(true),
                'summary' => (CommercialDocument::STATUSES[$document->status] ?? $document->status)
                    .($document->total ? ' · '.number_format((float) $document->total, 2, ',', ' ').' '.($document->currency ?: 'BYN') : ''),
                'meta' => $document->manager?->name,
                'url_type' => 'document',
                'url_id' => $document->id,
            ]);

            foreach ($document->contact_history ?? [] as $contact) {
                if (! is_array($contact)) {
                    continue;
                }

                $events->push([
                    'type' => 'contact',
                    'tone' => 'info',
                    'icon' => 'heroicon-o-chat-bubble-left-right',
                    'date' => $this->date($contact['contacted_at'] ?? null) ?? $document->updated_at,
                    'title' => 'Контакт по '.$document->reference(true),
                    'summary' => $contact['note'] ?? (CommercialDocument::CONTACT_RESULTS[$contact['result'] ?? ''] ?? 'Результат контакта сохранён'),
                    'meta' => $contact['user_name'] ?? null,
                    'url_type' => 'document',
                    'url_id' => $document->id,
                ]);
            }
        });

        Order::query()
            ->where('crm_counterparty_id', $counterparty->id)
            ->with(['statusHistory.user:id,name'])
            ->get()
            ->each(function (Order $order) use ($events): void {
                $events->push([
                    'type' => 'order',
                    'tone' => $order->status === 'delivered' ? 'success' : 'warning',
                    'icon' => 'heroicon-o-shopping-cart',
                    'date' => $order->created_at,
                    'title' => 'Создан заказ '.$order->number,
                    'summary' => (Order::STATUSES[$order->status] ?? $order->status).' · '.number_format((float) $order->total, 2, ',', ' ').' BYN',
                    'meta' => $order->customer_name,
                    'url_type' => 'order',
                    'url_id' => $order->id,
                ]);

                foreach ($order->statusHistory as $history) {
                    $events->push([
                        'type' => 'order_status',
                        'tone' => 'gray',
                        'icon' => 'heroicon-o-arrow-path',
                        'date' => $history->created_at,
                        'title' => 'Заказ '.$order->number.': '.(Order::STATUSES[$history->status_to] ?? $history->status_to),
                        'summary' => $history->comment,
                        'meta' => $history->user?->name,
                        'url_type' => 'order',
                        'url_id' => $order->id,
                    ]);
                }
            });

        TelephonyCall::query()
            ->where('crm_counterparty_id', $counterparty->id)
            ->with(['assignedUser:id,name', 'handledByUser:id,name'])
            ->get()
            ->each(function (TelephonyCall $call) use ($events): void {
                $events->push([
                    'type' => 'call',
                    'tone' => $call->status === 'answered' ? 'success' : 'danger',
                    'icon' => 'heroicon-o-phone',
                    'date' => $call->started_at ?? $call->created_at,
                    'title' => ($call->direction === 'outbound' ? 'Исходящий звонок' : 'Входящий звонок').' · '.$call->customer_phone,
                    'summary' => collect([
                        TelephonyCall::STATUSES[$call->status] ?? $call->status,
                        TelephonyCall::MANAGER_OUTCOMES[$call->manager_outcome] ?? null,
                        $call->manager_note,
                    ])->filter()->implode(' · '),
                    'meta' => $call->handledByUser?->name ?: $call->assignedUser?->name,
                    'url_type' => 'call',
                    'url_id' => $call->id,
                ]);
            });

        return $events
            ->filter(fn (array $event): bool => $event['date'] !== null)
            ->sortByDesc(fn (array $event): int => $event['date']->timestamp)
            ->values();
    }

    private function mailPreview(CrmMailMessage $message): ?string
    {
        $text = trim((string) ($message->text_body ?: strip_tags((string) $message->html_body)));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return $text !== '' ? Str::limit($text, 260) : null;
    }

    private function date(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
