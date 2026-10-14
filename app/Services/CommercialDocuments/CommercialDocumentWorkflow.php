<?php

namespace App\Services\CommercialDocuments;

use App\Models\CommercialDocument;
use App\Models\CrmMailbox;
use App\Models\CrmMailThread;
use App\Models\User;
use App\Services\CrmMail\SupplierRequestMailDraftService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CommercialDocumentWorkflow
{
    public function convertToInvoice(CommercialDocument $quote, User $user): CommercialDocument
    {
        if ($quote->type !== 'quote') {
            throw new RuntimeException('В счёт можно преобразовать только коммерческое предложение.');
        }

        return DB::transaction(function () use ($quote, $user): CommercialDocument {
            $quote->loadMissing('items');
            $invoice = $quote->replicate([
                'number', 'type', 'status', 'source_document_id', 'created_by', 'manager_id',
                'crm_mail_thread_id', 'sent_at', 'accepted_at',
            ]);
            $invoice->fill([
                'number' => CommercialDocument::nextNumber('invoice'),
                'type' => 'invoice',
                'status' => 'draft',
                'source_document_id' => $quote->id,
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
