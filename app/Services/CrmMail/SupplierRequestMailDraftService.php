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
        string|array $recipients,
        string $subject,
        string $body,
        ?string $participantName = null,
        array $cc = [],
        array $attachments = [],
        array $attachmentNames = [],
    ): CrmMailThread {
        $recipients = $this->normalizeAddresses($recipients);
        $cc = $this->normalizeAddresses($cc);
        $attachmentMetadata = $this->attachmentMetadata($attachments, $attachmentNames);
        $primaryRecipient = $recipients[0] ?? '';

        if ($primaryRecipient === '') {
            throw new RuntimeException('Укажите хотя бы одного получателя письма.');
        }

        return DB::transaction(function () use ($mailbox, $user, $recipients, $cc, $subject, $body, $participantName, $attachmentMetadata, $primaryRecipient): CrmMailThread {
            $thread = CrmMailThread::query()->create([
                'mailbox_id' => $mailbox->id,
                'assigned_user_id' => $user->id,
                'thread_key' => hash('sha256', 'outgoing-draft|'.Str::uuid()),
                'subject' => trim($subject),
                'participant_name' => filled($participantName) ? trim((string) $participantName) : null,
                'participant_email' => $primaryRecipient,
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
                'to' => $this->addressRecords($recipients, $participantName),
                'cc' => $this->addressRecords($cc),
                'subject' => trim($subject),
                'text_body' => trim($body),
                'attachments_count' => count($attachmentMetadata),
                'is_read' => true,
                'received_at' => now(),
                'metadata' => ['source' => 'manual_draft', 'attachments' => $attachmentMetadata],
            ]);

            return $thread->fresh(['mailbox', 'messages']);
        });
    }

    public function createInThread(
        CrmMailThread $thread,
        User $user,
        string|array $recipients,
        string $subject,
        string $body,
        ?string $participantName = null,
        array $cc = [],
        array $attachments = [],
        array $attachmentNames = [],
    ): CrmMailThread {
        $recipients = $this->normalizeAddresses($recipients);
        $cc = $this->normalizeAddresses($cc);
        $attachmentMetadata = $this->attachmentMetadata($attachments, $attachmentNames);
        $primaryRecipient = $recipients[0] ?? '';

        if ($primaryRecipient === '') {
            throw new RuntimeException('Укажите хотя бы одного получателя письма.');
        }

        return DB::transaction(function () use ($thread, $user, $recipients, $cc, $subject, $body, $participantName, $attachmentMetadata, $primaryRecipient): CrmMailThread {
            $draft = $thread->messages()->where('direction', 'draft')->latest('id')->first()
                ?? new CrmMailMessage(['thread_id' => $thread->id]);

            $draft->fill([
                'mailbox_id' => $thread->mailbox_id,
                'sent_by_user_id' => $user->id,
                'direction' => 'draft',
                'from_name' => $thread->mailbox?->name,
                'from_email' => $thread->mailbox?->email,
                'to' => $this->addressRecords($recipients, $participantName),
                'cc' => $this->addressRecords($cc),
                'subject' => trim($subject),
                'text_body' => trim($body),
                'html_body' => null,
                'attachments_count' => count($attachmentMetadata),
                'is_read' => true,
                'received_at' => now(),
                'metadata' => ['source' => 'commercial_document', 'attachments' => $attachmentMetadata],
            ])->save();

            $thread->forceFill([
                'assigned_user_id' => $thread->assigned_user_id ?: $user->id,
                'subject' => trim($subject),
                'participant_name' => $thread->participant_name ?: $participantName,
                'participant_email' => $primaryRecipient,
                'status' => 'draft',
                'messages_count' => $thread->messages()->count(),
                'last_message_at' => now(),
            ])->save();

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

            if ($recipient === '' && filter_var((string) $thread->participant_email, FILTER_VALIDATE_EMAIL)) {
                $recipient = (string) $thread->participant_email;
            }

            $thread->fill([
                'mailbox_id' => $mailbox->id,
                'crm_counterparty_id' => $request->supplier?->crm_counterparty_id,
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

    public function refreshRecipientFromSupplier(CrmMailThread $thread): CrmMailThread
    {
        if ($thread->status !== 'draft' || ! $thread->supplier_order_request_id) {
            return $thread;
        }

        $thread->loadMissing('supplierOrderRequest.supplier.crmCounterparty.contactPoints');
        $supplier = $thread->supplierOrderRequest?->supplier;
        $recipient = $supplier?->contactEmailAddresses()[0] ?? '';

        if ($supplier?->crm_counterparty_id && $thread->crm_counterparty_id !== $supplier->crm_counterparty_id) {
            $thread->forceFill(['crm_counterparty_id' => $supplier->crm_counterparty_id])->save();
        }

        if ($thread->hasValidRecipient()) {
            return $thread->fresh(['messages', 'supplierOrderRequest.supplier.crmCounterparty.contactPoints']);
        }

        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            return $thread->fresh(['messages', 'supplierOrderRequest.supplier.crmCounterparty.contactPoints']);
        }

        return DB::transaction(function () use ($thread, $recipient): CrmMailThread {
            $thread->forceFill(['participant_email' => $recipient])->save();

            $message = $thread->messages()->where('direction', 'draft')->first();
            if ($message) {
                $recipients = collect($message->to ?? [])
                    ->filter(fn (mixed $address): bool => is_array($address)
                        && filter_var((string) ($address['email'] ?? ''), FILTER_VALIDATE_EMAIL) !== false)
                    ->values();

                if ($recipients->isEmpty()) {
                    $message->forceFill([
                        'to' => [[
                            'name' => $thread->participant_name,
                            'email' => $recipient,
                        ]],
                    ])->save();
                }
            }

            return $thread->fresh(['messages', 'supplierOrderRequest.supplier.crmCounterparty.contactPoints']);
        });
    }

    public function update(
        CrmMailThread $thread,
        string|array|null $recipients,
        string $subject,
        string $body,
        User $user,
        array $cc = [],
        ?array $attachments = null,
        array $attachmentNames = [],
    ): CrmMailThread {
        if ($thread->status !== 'draft') {
            throw new RuntimeException('Редактировать можно только неотправленный черновик.');
        }

        $recipients = $this->normalizeAddresses($recipients ?? []);
        $cc = $this->normalizeAddresses($cc);

        return DB::transaction(function () use ($thread, $recipients, $cc, $subject, $body, $user, $attachments, $attachmentNames): CrmMailThread {
            $thread->forceFill([
                'participant_email' => $recipients[0] ?? '',
                'subject' => trim($subject),
                'assigned_user_id' => $thread->assigned_user_id ?: $user->id,
                'last_message_at' => now(),
            ])->save();

            $message = $thread->messages()->where('direction', 'draft')->firstOrFail();
            $metadata = $message->metadata ?? [];
            if ($attachments !== null) {
                $metadata['attachments'] = $this->attachmentMetadata($attachments, $attachmentNames);
            }

            $message->forceFill([
                'sent_by_user_id' => $user->id,
                'to' => $this->addressRecords($recipients, $thread->participant_name),
                'cc' => $this->addressRecords($cc),
                'subject' => trim($subject),
                'text_body' => trim($body),
                'attachments_count' => count($metadata['attachments'] ?? []),
                'metadata' => $metadata,
                'received_at' => now(),
            ])->save();

            return $thread->fresh(['messages', 'mailbox', 'supplierOrderRequest.order']);
        });
    }

    /** @return list<string> */
    private function normalizeAddresses(string|array $addresses): array
    {
        $addresses = is_array($addresses) ? $addresses : [$addresses];

        return collect($addresses)
            ->map(fn (mixed $address): string => mb_strtolower(trim((string) $address)))
            ->filter(fn (string $address): bool => filter_var($address, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();
    }

    /** @param list<string> $addresses
     * @return list<array{name: string|null, email: string}>
     */
    private function addressRecords(array $addresses, ?string $primaryName = null): array
    {
        return collect($addresses)->values()->map(fn (string $email, int $index): array => [
            'name' => $index === 0 ? $primaryName : null,
            'email' => $email,
        ])->all();
    }

    /** @return list<array{path: string, name: string}> */
    private function attachmentMetadata(array $paths, array $names): array
    {
        return collect($paths)->values()->map(function (mixed $path, int $index) use ($names): array {
            $path = (string) $path;

            return [
                'path' => $path,
                'name' => (string) ($names[$path] ?? $names[$index] ?? basename($path)),
            ];
        })->filter(fn (array $file): bool => str_starts_with($file['path'], 'crm-mail-attachments/'))
            ->values()->all();
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
