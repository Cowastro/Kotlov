<?php

namespace App\Services\CommercialDocuments;

use App\Models\CommercialDocument;

class CommercialDocumentReadiness
{
    /** @return list<string> */
    public function issues(CommercialDocument $document): array
    {
        $issues = [];
        $isOrganization = filled($document->client_company)
            || filled($document->client_legal_name)
            || filled($document->client_unp);

        if (! $document->crm_counterparty_id) {
            $issues[] = 'клиент не связан с карточкой CRM';
        }
        if ($isOrganization && blank($document->client_legal_name) && blank($document->client_company)) {
            $issues[] = 'не указано юридическое название покупателя';
        }
        if ($isOrganization && blank($document->client_unp)) {
            $issues[] = 'не указан УНП / ИНН покупателя';
        }
        if ($isOrganization && blank($document->client_address)) {
            $issues[] = 'не указан юридический адрес покупателя';
        }
        if (! filter_var((string) $document->client_email, FILTER_VALIDATE_EMAIL)) {
            $issues[] = 'не указан корректный email получателя';
        }
        if ($document->items()->count() === 0) {
            $issues[] = 'в документе нет товаров или услуг';
        }
        if ($document->items()->where(fn ($query) => $query
            ->where('quantity', '<=', 0)
            ->orWhere('unit_price', '<=', 0))->exists()) {
            $issues[] = 'есть позиции без количества или цены';
        }
        if (blank($document->payment_terms)) {
            $issues[] = 'не заполнены условия оплаты';
        }
        if (blank($document->delivery_terms)) {
            $issues[] = 'не заполнены условия поставки';
        }
        if ($document->valid_until?->isPast()) {
            $issues[] = 'срок действия документа уже истёк';
        }

        return $issues;
    }

    public function summary(CommercialDocument $document): string
    {
        $issues = $this->issues($document);

        return $issues === []
            ? 'Готов к отправке'
            : 'Нужно исправить: '.implode('; ', $issues);
    }
}
