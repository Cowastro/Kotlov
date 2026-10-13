<?php

namespace App\Services\CrmMail;

use App\Models\CrmMailbox;
use App\Models\CrmMailMessage;
use App\Models\CrmMailThread;
use App\Models\SupplierOrderRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SupplierRequestMailDraftService
{
    public function create(
        CrmMailbox $mailbox,
        User $user,
        string $recipient,
        string $subject,
        string $body,
        ?string $participantName = null,
    ): CrmMailThread {
        return DB::transaction(function () use ($mailbox, $user, $recipient, $subject, $body, $participantName): CrmMailThread {
            $thread = CrmMailThread::query()->create([
                'mailbox_id' => $mailbox->id,
                'assigned_user_id' => $user->id,
                'thread_key' => hash('sha256', 'outgoing-draft|'.Str::uuid()),
                'subject' => trim($subject),
                'participant_name' => filled($participantName) ? trim((string) $participantName) : null,
                'participant_email' => mb_strtolower(trim($recipient)),
                'status' => 'draft',
                'priority' => 'normal',
                'messages_count' => 1,
                'unread_count' => 0,
                'last_message_at' => now(),
            ]);

            $thread->messages()->create([
                'mailbox_id' => $mailbox->id,
                'sent_by_user_id' => $user->id,
                'direction' => 'draft',
                'from_name' => $mailbox->name,
                'from_email' => $mailbox->email,
                'to' => [['name' => $participantName, 'email' => mb_strtolower(trim($recipient))]],
                'subject' => trim($subject),
                'text_body' => trim($body),
                'attachments_count' => 0,
                'is_read' => true,
                'received_at' => now(),
                'metadata' => ['source' => 'manual_draft'],
            ]);

            return $thread->fresh(['mailbox', 'messages']);
        });
    }

    public function sync(SupplierOrderRequest $request, ?User $user): CrmMailThread
    {
        $request->loadMissing(['order', 'items', 'supplier.crmCounterparty.contactPoints']);
        $mailbox = CrmMailbox::query()->where('is_enabled', true)->first()
            ?? CrmMailbox::query()->first();

        if (! $mailbox) {
            throw new RuntimeException('Сначала создайте общий почтовый ящик в разделе «Настройка почты».');
        }

        $recipient = $request->supplier?->contactEmailAddresses()[0] ?? '';
        $subject = 'Заявка '.$request->number.' — заказ '.$request->order->number;
        $body = $this->body($request);

        return DB::transaction(function () use ($request, $user, $mailbox, $recipient, $subject, $body): CrmMailThread {
            $thread = CrmMailThread::query()->firstOrNew([
                'supplier_order_request_id' => $request->id,
            ]);

            if ($thread->exists && $thread->status !== 'draft') {
                return $thread;
            }

            $thread->fill([
                'mailbox_id' => $mailbox->id,
                'assigned_user_id' => $user?->id,
                'thread_key' => hash('sha256', 'supplier-order-request|'.$request->id),
                'subject' => $subject,
                'participant_name' => $request->supplier_name,
                'participant_email' => $recipient,
                'status' => 'draft',
                'priority' => 'normal',
                'messages_count' => 1,
                'unread_count' => 0,
                'last_message_at' => now(),
                'handled_at' => null,
                'manager_note' => 'Черновик создан из заявки поставщику '.$request->number.'.',
            ])->save();

            $message = $thread->messages()->where('direction', 'draft')->first() ?? new CrmMailMessage;
            $message->fill([
                'thread_id' => $thread->id,
                'mailbox_id' => $mailbox->id,
                'sent_by_user_id' => $user?->id,
                'direction' => 'draft',
                'from_name' => $mailbox->name,
                'from_email' => $mailbox->email,
                'to' => $recipient !== '' ? [['name' => $request->supplier_name, 'email' => $recipient]] : [],
                'subject' => $subject,
                'text_body' => $body,
                'html_body' => null,
                'attachments_count' => 0,
                'is_read' => true,
                'received_at' => now(),
                'sent_at' => null,
                'metadata' => [
                    'source' => 'supplier_order_request',
                    'supplier_order_request_id' => $request->id,
                    'order_id' => $request->order_id,
                ],
            ])->save();

            return $thread->fresh(['mailbox', 'messages', 'supplierOrderRequest.order']);
        });
    }

    public function update(CrmMailThread $thread, ?string $recipient, string $subject, string $body, User $user): CrmMailThread
    {
        if ($thread->status !== 'draft') {
            throw new RuntimeException('Редактировать можно только неотправленный черновик.');
        }

        return DB::transaction(function () use ($thread, $recipient, $subject, $body, $user): CrmMailThread {
            $thread->forceFill([
                'participant_email' => trim((string) $recipient),
                'subject' => trim($subject),
                'assigned_user_id' => $thread->assigned_user_id ?: $user->id,
                'last_message_at' => now(),
            ])->save();

            $message = $thread->messages()->where('direction', 'draft')->firstOrFail();
            $message->forceFill([
                'sent_by_user_id' => $user->id,
                'to' => trim((string) $recipient) !== ''
                    ? [['name' => $thread->participant_name, 'email' => trim((string) $recipient)]]
                    : [],
                'subject' => trim($subject),
                'text_body' => trim($body),
                'received_at' => now(),
            ])->save();

            return $thread->fresh(['messages', 'mailbox', 'supplierOrderRequest.order']);
        });
    }

    private function body(SupplierOrderRequest $request): string
    {
        $lines = [
            'Здравствуйте!',
            '',
            'Просим подтвердить возможность поставки по заявке '.$request->number.'.',
            '',
            'Состав заявки:',
        ];

        foreach ($request->items as $item) {
            $line = '• '.$item->product_name;
            if (filled($item->product_sku)) {
                $line .= ' ['.$item->product_sku.']';
            }
            $line .= ' — '.$item->quantity.' шт.';
            $lines[] = $line;
        }

        $lines[] = '';
        $lines[] = 'Пожалуйста, подтвердите наличие, актуальную закупочную цену и срок отгрузки.';
        $lines[] = 'В ответе укажите номер заявки '.$request->number.'.';

        return implode("\n", $lines);
    }
}
