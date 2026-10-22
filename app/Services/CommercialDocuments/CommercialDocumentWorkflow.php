<?php

namespace App\Services\CommercialDocuments;

use App\Events\NewOrderCreated;
use App\Models\CommercialDocument;
use App\Models\CrmMailbox;
use App\Models\CrmMailThread;
use App\Models\IntegrationProduct;
use App\Models\Order;
use App\Models\User;
use App\Services\CrmMail\SupplierRequestMailDraftService;
use App\Services\Orders\OrderEconomicSnapshotRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CommercialDocumentWorkflow
{
    public function recordContact(
        CommercialDocument $document,
        User $user,
        string $stage,
        string $result,
        string $note,
        mixed $nextFollowUpAt = null,
        ?string $lossReason = null,
    ): void {
        if (! array_key_exists($stage, CommercialDocument::FOLLOW_UP_STAGES)) {
            throw new RuntimeException('Выберите допустимую стадию работы.');
        }
        if (! array_key_exists($result, CommercialDocument::CONTACT_RESULTS)) {
            throw new RuntimeException('Выберите допустимый результат контакта.');
        }
        if (! in_array($result, ['accepted', 'rejected', 'needs_revision'], true)
            && in_array($stage, ['won', 'lost', 'needs_revision'], true)) {
            throw new RuntimeException('Закрывающая стадия должна соответствовать результату контакта.');
        }

        $note = trim($note);
        if (trim($note, " \t\n\r\0\x0B,.;:–—-") === '') {
            throw new RuntimeException('Кратко запишите осмысленный результат разговора, а не знак препинания.');
        }

        $nextContact = filled($nextFollowUpAt) ? Carbon::parse($nextFollowUpAt) : null;
        if (in_array($result, ['no_answer', 'callback_later', 'decision_pending', 'negotiation'], true) && ! $nextContact) {
            throw new RuntimeException('Укажите дату следующего контакта.');
        }

        DB::transaction(function () use ($document, $user, $stage, $result, $note, $nextContact, $lossReason): void {
            $document = CommercialDocument::query()
                ->lockForUpdate()
                ->findOrFail($document->getKey());

            if ($document->type !== 'quote' || ! in_array($document->status, ['sent', 'accepted', 'needs_revision'], true)) {
                throw new RuntimeException('Результат контакта уже зафиксирован другим менеджером или КП закрыто. Обновите карточку.');
            }

            $lastContact = collect($document->contact_history ?? [])->last();
            if (in_array($result, ['accepted', 'rejected'], true)
                && is_array($lastContact)
                && ($lastContact['result'] ?? null) === $result) {
                throw new RuntimeException('Результат контакта уже зафиксирован другим менеджером или КП закрыто. Обновите карточку.');
            }

            if ($result === 'accepted') {
                $this->accept($document);
                $stage = 'won';
                $nextContact = null;
            } elseif ($result === 'rejected') {
                $this->reject($document, (string) $lossReason, $note);
                $stage = 'lost';
                $nextContact = null;
            } elseif ($result === 'needs_revision') {
                $this->requestRevision($document, $note);
                $stage = 'needs_revision';
                $nextContact = null;
            }

            $history = $document->fresh()->contact_history ?? [];
            $history[] = [
                'contacted_at' => now()->toIso8601String(),
                'user_id' => $user->id,
                'user_name' => $user->name,
                'stage' => $stage,
                'result' => $result,
                'note' => $note,
                'next_follow_up_at' => $nextContact?->toIso8601String(),
            ];

            $document->forceFill([
                'follow_up_stage' => $stage,
                'last_contact_result' => $result,
                'last_contacted_at' => now(),
                'next_follow_up_at' => $nextContact,
                'follow_up_note' => $note,
                'contact_history' => array_slice($history, -100),
                'last_control_notified_at' => null,
            ])->save();

            $document->mailThread?->forceFill([
                'assigned_user_id' => $document->mailThread->assigned_user_id ?: $user->id,
                'sales_stage' => match ($stage) {
                    'won' => 'won', 'lost' => 'lost',
                    'contact_planned', 'decision_pending', 'negotiation', 'needs_revision' => $stage,
                    default => 'proposal_sent',
                },
                'status' => match ($stage) {
                    'won', 'lost' => 'closed',
                    'needs_revision' => 'waiting_manager',
                    default => 'waiting_customer',
                },
                'next_follow_up_at' => $nextContact,
                'follow_up_note' => $note,
            ])->save();
        });
    }

    public function convertToOrder(CommercialDocument $document, User $user): Order
    {
        if ($document->type !== 'quote' || $document->status !== 'accepted') {
            throw new RuntimeException('Заказ можно создать только из согласованного коммерческого предложения.');
        }

        if (blank($document->client_phone)) {
            throw new RuntimeException('Перед созданием заказа укажите телефон клиента в коммерческом предложении.');
        }

        if (blank($document->client_name) && blank($document->client_company) && blank($document->client_legal_name)) {
            throw new RuntimeException('Перед созданием заказа укажите клиента или организацию.');
        }

        $document->loadMissing('items');
        if ($document->items->isEmpty()) {
            throw new RuntimeException('В коммерческом предложении нет товарных позиций.');
        }

        foreach ($document->items as $item) {
            if ((float) $item->quantity <= 0 || floor((float) $item->quantity) !== (float) $item->quantity) {
                throw new RuntimeException('Для создания заказа количество каждой позиции должно быть целым и больше нуля.');
            }
        }

        $created = false;
        $order = DB::transaction(function () use ($document, $user, &$created): Order {
            $locked = CommercialDocument::query()->lockForUpdate()->findOrFail($document->id);
            $existing = Order::query()->where('commercial_document_id', $locked->id)->first();
            if ($existing) {
                return $existing;
            }

            $locked->load('items');
            $isPaid = CommercialDocument::query()
                ->where('source_document_id', $locked->id)
                ->where('type', 'invoice')
                ->where('status', 'paid')
                ->exists();
            $order = Order::query()->create([
                'commercial_document_id' => $locked->id,
                'crm_counterparty_id' => $locked->crm_counterparty_id,
                'crm_contact_id' => $locked->crm_contact_id,
                'number' => Order::generateNumber(),
                'status' => 'new',
                'customer_name' => $locked->client_name ?: $locked->client_company ?: $locked->client_legal_name,
                'customer_phone' => $locked->client_phone,
                'customer_email' => $locked->client_email,
                'company_name' => $locked->client_legal_name ?: $locked->client_company,
                'company_unp' => $locked->client_unp,
                'company_address' => $locked->client_address,
                'company_email' => $locked->client_email,
                'delivery_type' => $locked->delivery_to_client || (float) $locked->delivery_price > 0 ? 'transport' : 'pickup',
                'delivery_price' => $locked->delivery_price,
                'payment_type' => 'invoice',
                'payment_status' => $isPaid ? 'paid' : 'pending',
                'discount' => $locked->discount_total,
                'subtotal' => $locked->subtotal,
                'total' => $locked->total,
                'comment' => $locked->notes,
                'admin_comment' => implode("\n", array_filter([
                    'Создан из согласованного КП '.$locked->number.'.',
                    $locked->payment_terms ? 'Условия оплаты: '.$locked->payment_terms : null,
                    $locked->delivery_terms ? 'Условия поставки: '.$locked->delivery_terms : null,
                ])),
                'manager_id' => $user->id,
            ]);

            foreach ($locked->items as $item) {
                $quantity = (int) $item->quantity;
                $netPrice = round((float) $item->line_total / $quantity, 2);
                $oneCProduct = $item->product_id
                    ? IntegrationProduct::query()
                        ->where('product_id', $item->product_id)
                        ->where('match_status', 'matched')
                        ->whereHas('source', fn ($source) => $source->where('code', 'onec')->where('is_active', true))
                        ->latest('matched_at')
                        ->latest('id')
                        ->first()
                    : null;
                $order->items()->create([
                    'product_id' => $item->product_id,
                    'product_name' => $item->name,
                    'product_sku' => $item->sku,
                    'price' => $netPrice,
                    'quantity' => $quantity,
                    'total' => $item->line_total,
                    'pricing_type' => 'b2b',
                    'price_tax_mode' => $oneCProduct?->price_tax_mode,
                    'integration_product_id' => $oneCProduct?->id,
                ]);
            }

            app(OrderEconomicSnapshotRecorder::class)->capturePlaced($order->load('items'));
            $created = true;

            return $order;
        });

        if ($created) {
            NewOrderCreated::dispatch($order->load('items'));
        }

        return $order;
    }

    public function accept(CommercialDocument $document): void
    {
        DB::transaction(function () use ($document): void {
            $document->forceFill([
                'status' => 'accepted',
                'follow_up_stage' => 'won',
                'next_follow_up_at' => null,
                'accepted_at' => now(),
                'outcome_reason' => null,
                'outcome_note' => null,
                'closed_at' => null,
            ])->save();

            $document->mailThread?->forceFill([
                'sales_stage' => 'won',
                'status' => 'closed',
                'next_follow_up_at' => null,
                'follow_up_note' => 'Коммерческое предложение согласовано клиентом.',
            ])->save();
        });
    }

    public function reject(CommercialDocument $document, string $reason, ?string $note = null): void
    {
        if (! array_key_exists($reason, CommercialDocument::OUTCOME_REASONS)) {
            throw new RuntimeException('Выберите допустимую причину отказа.');
        }

        DB::transaction(function () use ($document, $reason, $note): void {
            $document->forceFill([
                'status' => 'rejected',
                'follow_up_stage' => 'lost',
                'next_follow_up_at' => null,
                'outcome_reason' => $reason,
                'outcome_note' => $note,
                'closed_at' => now(),
            ])->save();

            $document->mailThread?->forceFill([
                'sales_stage' => 'lost',
                'status' => 'closed',
                'next_follow_up_at' => null,
                'follow_up_note' => 'Отказ: '.CommercialDocument::OUTCOME_REASONS[$reason].($note ? '. '.$note : ''),
            ])->save();
        });
    }

    public function requestRevision(CommercialDocument $document, string $note): void
    {
        if ($document->type !== 'quote' || ! in_array($document->status, ['sent', 'accepted', 'needs_revision'], true)) {
            throw new RuntimeException('Корректировку можно запросить только для отправленного или согласованного КП.');
        }

        $note = trim($note);
        if ($note === '') {
            throw new RuntimeException('Укажите, что именно требуется скорректировать.');
        }

        DB::transaction(function () use ($document, $note): void {
            $document->forceFill([
                'status' => 'needs_revision',
                'follow_up_stage' => 'needs_revision',
                'next_follow_up_at' => null,
                'accepted_at' => null,
                'outcome_reason' => null,
                'outcome_note' => $note,
                'closed_at' => null,
                'last_control_notified_at' => null,
            ])->save();

            $document->mailThread?->forceFill([
                'sales_stage' => 'negotiation',
                'status' => 'waiting_manager',
                'next_follow_up_at' => now(),
                'follow_up_note' => 'Подготовить новую редакцию КП: '.$note,
            ])->save();
        });
    }

    public function expire(CommercialDocument $document): void
    {
        DB::transaction(function () use ($document): void {
            $document->forceFill([
                'status' => 'expired',
                'follow_up_stage' => 'lost',
                'next_follow_up_at' => null,
                'outcome_reason' => 'no_response',
                'outcome_note' => 'Срок действия предложения истёк.',
                'closed_at' => now(),
            ])->save();

            $document->mailThread?->forceFill([
                'sales_stage' => 'lost',
                'status' => 'closed',
                'next_follow_up_at' => null,
                'follow_up_note' => 'Срок действия коммерческого предложения истёк.',
            ])->save();
        });
    }

    public function markPaid(CommercialDocument $document): void
    {
        if ($document->type !== 'invoice') {
            throw new RuntimeException('Оплату можно отметить только для счёта.');
        }

        DB::transaction(function () use ($document): void {
            $invoice = CommercialDocument::query()
                ->lockForUpdate()
                ->with(['sourceDocument.order', 'sourceDocument.mailThread'])
                ->findOrFail($document->id);

            $invoice->forceFill([
                'status' => 'paid',
                'accepted_at' => $invoice->accepted_at ?: now(),
                'closed_at' => now(),
            ])->save();

            $quote = $invoice->sourceDocument;
            if (! $quote || $quote->type !== 'quote') {
                return;
            }

            $quote->forceFill([
                'status' => 'accepted',
                'accepted_at' => $quote->accepted_at ?: now(),
                'outcome_reason' => null,
                'outcome_note' => null,
                'closed_at' => null,
            ])->save();

            $quote->mailThread?->forceFill([
                'sales_stage' => 'won',
                'status' => 'closed',
                'next_follow_up_at' => null,
                'follow_up_note' => 'Счёт по коммерческому предложению оплачен.',
            ])->save();

            $quote->order?->forceFill(['payment_status' => 'paid'])->save();
        });
    }

    public function createRevision(CommercialDocument $document, User $user): CommercialDocument
    {
        if ($document->type !== 'quote') {
            throw new RuntimeException('Новую редакцию можно создать только для коммерческого предложения.');
        }

        return DB::transaction(function () use ($document, $user): CommercialDocument {
            $document->loadMissing('items');
            $rootId = $document->revision_of_id ?: $document->id;
            $root = CommercialDocument::query()->lockForUpdate()->findOrFail($rootId);
            $nextRevision = max(
                (int) $root->revision_number,
                (int) CommercialDocument::query()->where('revision_of_id', $rootId)->max('revision_number'),
            ) + 1;

            $revision = $document->replicate([
                'number', 'status', 'revision_of_id', 'revision_number', 'created_by',
                'outcome_reason', 'outcome_note', 'sent_at', 'accepted_at', 'closed_at',
            ]);
            $revision->fill([
                'number' => $root->number,
                'status' => 'draft',
                'revision_of_id' => $rootId,
                'revision_number' => $nextRevision,
                'created_by' => $user->id,
                'manager_id' => $user->id,
                'issued_at' => today(),
                'valid_until' => today()->addDays(10),
            ])->save();

            foreach ($document->items as $item) {
                $revision->items()->create($item->only([
                    'product_id', 'name', 'sku', 'unit', 'quantity', 'unit_price',
                    'discount_percent', 'vat_rate', 'line_total', 'sort_order',
                ]));
            }
            $revision->recalculate();

            return $revision->fresh('items');
        });
    }

    public function convertToInvoice(CommercialDocument $quote, User $user): CommercialDocument
    {
        if ($quote->type !== 'quote') {
            throw new RuntimeException('В счёт можно преобразовать только коммерческое предложение.');
        }

        return DB::transaction(function () use ($quote, $user): CommercialDocument {
            $quote->loadMissing('items');
            $invoice = $quote->replicate([
                'number', 'type', 'status', 'source_document_id', 'created_by', 'manager_id',
                'crm_mail_thread_id', 'revision_of_id', 'revision_number', 'outcome_reason', 'outcome_note',
                'sent_at', 'accepted_at', 'closed_at',
            ]);
            $invoice->fill([
                'number' => CommercialDocument::nextNumber('invoice'),
                'type' => 'invoice',
                'status' => 'draft',
                'source_document_id' => $quote->id,
                'revision_number' => 1,
                'created_by' => $user->id,
                'manager_id' => $user->id,
                'valid_until' => today()->addDays(5),
            ]);
            $invoice->save();

            foreach ($quote->items as $item) {
                $invoice->items()->create($item->only([
                    'product_id', 'name', 'sku', 'unit', 'quantity', 'unit_price',
                    'discount_percent', 'vat_rate', 'line_total', 'sort_order',
                ]));
            }
            $invoice->recalculate();

            return $invoice->fresh('items');
        });
    }

    public function createMailDraft(CommercialDocument $document, User $user): CrmMailThread
    {
        if (! filter_var((string) $document->client_email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Сначала укажите email клиента в карточке документа.');
        }

        // The create form persists relationship repeaters after the parent
        // document. Always calculate the current item totals immediately
        // before composing the message so its amount and PDF cannot contain
        // the parent's initial zero values. Re-running this method refreshes
        // an existing unsent draft instead of returning stale contents.
        $document->recalculate();
        $document->refresh();

        $mailbox = CrmMailbox::query()
            ->visibleTo($user)
            ->manualSending()
            ->where('is_enabled', true)
            ->first();
        if (! $mailbox) {
            throw new RuntimeException('Нет доступного почтового ящика для отправки. Проверьте доступ менеджера в разделе «Настройка почты».');
        }

        $portalLink = $document->type === 'quote'
            ? app(CommercialDocumentPortal::class)->publish($document, $user)
            : null;
        $attachment = app(CommercialDocumentPdf::class)->storeForMail($document);
        $title = CommercialDocument::TYPES[$document->type].' '.$document->reference();
        $body = implode("\n", array_filter([
            'Здравствуйте'.($document->client_name ? ', '.$document->client_name : '').'!',
            '',
            'Направляем '.$this->accusativeType($document->type).' '.$document->reference().' от '.$document->issued_at->format('d.m.Y').'.',
            'Сумма: '.number_format((float) $document->total, 2, ',', ' ').' '.$document->currency.'.',
            $document->valid_until ? 'Документ действителен до '.$document->valid_until->format('d.m.Y').'.' : null,
            '',
            'Документ приложен к письму в формате PDF.',
            $portalLink ? '' : null,
            $portalLink ? 'Открыть предложение онлайн: '.app(CommercialDocumentPortal::class)->url($portalLink) : null,
            $portalLink ? 'Регистрация и пароль не требуются. Ссылка действует до '.$portalLink->expires_at->format('d.m.Y H:i').' и защищена подтверждением устройства.' : null,
        ], fn ($line): bool => $line !== null));

        $drafts = app(SupplierRequestMailDraftService::class);
        $draftArguments = [
            $user,
            [$document->client_email],
            $title,
            $body,
            $document->client_company ?: $document->client_name,
            [],
            [$attachment['path']],
            [$attachment['path'] => $attachment['name']],
        ];
        $existingThread = $document->mailThread;
        $thread = $existingThread?->mailbox?->isVisibleTo($user)
            ? $drafts->createInThread($existingThread, ...$draftArguments)
            : $drafts->create($mailbox, ...$draftArguments);

        $document->forceFill(['crm_mail_thread_id' => $thread->id])->save();

        return $thread;
    }

    private function accusativeType(string $type): string
    {
        return $type === 'invoice' ? 'счёт на оплату' : 'коммерческое предложение';
    }
}
