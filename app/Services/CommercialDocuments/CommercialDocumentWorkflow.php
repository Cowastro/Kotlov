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
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CommercialDocumentWorkflow
{
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
                'delivery_type' => (float) $locked->delivery_price > 0 ? 'transport' : 'pickup',
                'delivery_price' => $locked->delivery_price,
                'payment_type' => 'invoice',
                'payment_status' => 'pending',
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

    public function expire(CommercialDocument $document): void
    {
        DB::transaction(function () use ($document): void {
            $document->forceFill([
                'status' => 'expired',
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

        $document->forceFill([
            'status' => 'paid',
            'accepted_at' => $document->accepted_at ?: now(),
            'closed_at' => now(),
        ])->save();
    }

    public function createRevision(CommercialDocument $document, User $user): CommercialDocument
    {
        if ($document->type !== 'quote') {
            throw new RuntimeException('Новую редакцию можно создать только для коммерческого предложения.');
        }

        return DB::transaction(function () use ($document, $user): CommercialDocument {
            $document->loadMissing('items');
            $rootId = $document->revision_of_id ?: $document->id;
            $nextRevision = max(
                (int) CommercialDocument::query()->whereKey($rootId)->value('revision_number'),
                (int) CommercialDocument::query()->where('revision_of_id', $rootId)->max('revision_number'),
            ) + 1;

            $revision = $document->replicate([
                'number', 'status', 'revision_of_id', 'revision_number', 'created_by',
                'outcome_reason', 'outcome_note', 'sent_at', 'accepted_at', 'closed_at',
            ]);
            $revision->fill([
                'number' => CommercialDocument::nextNumber('quote'),
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
        if ($document->mailThread?->status === 'draft') {
            return $document->mailThread;
        }

        $mailbox = CrmMailbox::query()->where('is_enabled', true)->first();
        if (! $mailbox) {
            throw new RuntimeException('Нет активного почтового ящика. Проверьте раздел «Настройка почты».');
        }

        $attachment = app(CommercialDocumentPdf::class)->storeForMail($document);
        $title = CommercialDocument::TYPES[$document->type].' '.$document->number;
        $body = implode("\n", array_filter([
            'Здравствуйте'.($document->client_name ? ', '.$document->client_name : '').'!',
            '',
            'Направляем '.$this->accusativeType($document->type).' '.$document->number.' от '.$document->issued_at->format('d.m.Y').'.',
            'Сумма: '.number_format((float) $document->total, 2, ',', ' ').' '.$document->currency.'.',
            $document->valid_until ? 'Документ действителен до '.$document->valid_until->format('d.m.Y').'.' : null,
            '',
            'Документ приложен к письму в формате PDF.',
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
        $thread = $document->mailThread
            ? $drafts->createInThread($document->mailThread, ...$draftArguments)
            : $drafts->create($mailbox, ...$draftArguments);

        $document->forceFill(['crm_mail_thread_id' => $thread->id])->save();

        return $thread;
    }

    private function accusativeType(string $type): string
    {
        return $type === 'invoice' ? 'счёт на оплату' : 'коммерческое предложение';
    }
}
