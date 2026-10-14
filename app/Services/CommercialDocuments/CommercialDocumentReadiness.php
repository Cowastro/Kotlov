<?php

namespace App\Services\CommercialDocuments;

use App\Models\CommercialDocument;

class CommercialDocumentReadiness
{
    public function __construct(private readonly CommercialOfferCopilotService $pricing) {}

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
        $document->loadMissing('items.product');
        foreach ($document->items as $item) {
            if (! $item->product) {
                continue;
            }

            if ($item->product->requires_price_confirmation) {
                $issues[] = 'нужно подтвердить актуальность цены: '.$item->name;

                continue;
            }

            $quantity = max(0.001, (float) $item->quantity);
            $netUnitPrice = round((float) $item->line_total / $quantity, 2);
            $pricing = $this->pricing->pricing((int) $item->product_id, $netUnitPrice);
            if ($pricing['purchase_price'] === null) {
                $issues[] = 'не удалось проверить закупочную цену и маржу: '.$item->name;

                continue;
            }

            if ((float) $pricing['margin_percent'] < (float) $pricing['minimum_margin_percent']) {
                $issues[] = 'маржа ниже допустимой: '.$item->name
                    .' ('.number_format((float) $pricing['margin_percent'], 1, ',', ' ').'%)';
            }
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

        return array_values(array_unique($issues));
    }

    public function summary(CommercialDocument $document): string
    {
        $issues = $this->issues($document);

        return $issues === []
            ? 'Готов к отправке'
            : 'Нужно исправить: '.implode('; ', $issues);
    }
}
