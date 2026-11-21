<?php

namespace App\Services\Integrations\OneCBridge;

use App\Models\OneCOrderProfile;

/**
 * A scheme of selling in 1C made from a real «Заказ клиента»: every field of its header is kept as the manager
 * filled it in 1C (operation, VAT, payment terms, subdivision, priority…) except the document's own data and the
 * customer's texts, which the site writes for each order.
 */
class OneCOrderProfiles
{
    /** Header fields the admin may change, field => [label, 1C list] */
    public const FIELDS = [
        'Организация_Key' => ['Организация', 'organization'],
        'Склад_Key' => ['Склад', 'warehouse'],
        'Партнер_Key' => ['Клиент (партнёр)', 'partner'],
        'Контрагент_Key' => ['Контрагент', 'counterparty'],
        'Соглашение_Key' => ['Соглашение', 'agreement'],
        'Договор_Key' => ['Договор', 'contract'],
        'Подразделение_Key' => ['Подразделение', 'subdivision'],
        'Менеджер_Key' => ['Менеджер', 'user'],
        'ЗонаДоставки_Key' => ['Зона доставки', 'delivery_zone'],
        'Приоритет_Key' => ['Приоритет', 'priority'],
    ];

    /** «Статус» of «Заказ клиента» */
    public const STATUSES = [
        'НеСогласован' => 'Не согласован',
        'КОбеспечению' => 'К обеспечению',
        'КВыполнению' => 'К выполнению',
    ];

    /** «Способ доставки» */
    public const DELIVERY = [
        'Самовывоз' => 'Самовывоз',
        'ДоКлиента' => 'Наша транспортная служба до клиента',
        'ДоПеревозчика' => 'Наша транспортная служба до перевозчика',
        'СиламиПеревозчика' => 'Силами перевозчика',
        'СиламиПеревозчикаПоАдресу' => 'Силами перевозчика по адресу',
    ];

    /** Fields of the document itself, never copied to another order. */
    private const DOCUMENT_FIELDS = ['Ref_Key', 'DataVersion', 'Number', 'Date', 'Posted', 'DeletionMark', 'СуммаДокумента', 'Автор_Key',
        'Сделка_Key', 'КонтактноеЛицо_Key', 'КартаЛояльности_Key', 'ДокументОснование', 'ДокументОснование_Type', 'НомерПоДаннымКлиента',
        'ДатаПоДаннымКлиента', 'Объект', 'Объект_Type', 'ИдентификаторПлатежа', 'МаксимальныйКодСтроки'];

    /** The only field the customer's address goes to (not the carrier's, not the structured «…Значение»). */
    public const ADDRESS_FIELD = 'АдресДоставки';

    /** Fields of a goods row worth keeping (the rest is the item and its sums). */
    private const ROW_FIELDS = ['ВариантОбеспечения', 'СтавкаНДС_Key', 'ВидЦены_Key', 'Отменено'];

    /** Fields of a payment row worth keeping. */
    private const PAYMENT_FIELDS = ['ВариантОплаты', 'ВариантОтсчета', 'Сдвиг'];

    /** @param array<string, mixed> $order a «Заказ клиента» as 1C gave it */
    public function fromTemplate(array $order, string $name, string $mode): OneCOrderProfile
    {
        $header = [];
        $meta = ['keys' => [], 'address' => [], 'info' => [], 'dates' => []];
        foreach ($order as $key => $value) {
            $key = (string) $key;
            if (is_array($value) || str_contains($key, '@')) {
                continue;
            }
            $meta['keys'][] = $key;
            if (in_array($key, self::DOCUMENT_FIELDS, true)) {
                continue;
            }
            if (preg_match('/^(Дата|ЖелаемаяДата)/u', $key)) {
                $meta['dates'][] = $key;

                continue;
            }
            if (is_string($value) && ! str_ends_with($key, '_Key') && ! str_ends_with($key, '_Type')
                && preg_match('/Адрес|Информация|Телефон|Email|Почт|Контакт|Комментарий|Примечание/u', $key)) {
                // The customer's own texts: the site writes them for each order.
                if ($key === self::ADDRESS_FIELD) {
                    $meta['address'][] = $key;
                } elseif (str_contains($key, 'Информация')) {
                    $meta['info'][] = $key;
                }
                $header[$key] = '';

                continue;
            }
            $header[$key] = $value;
        }

        $row = collect((array) data_get($order, 'Товары.0', []))->only(self::ROW_FIELDS)->all();
        $payment = collect((array) data_get($order, 'ЭтапыГрафикаОплаты.0', []))->only(self::PAYMENT_FIELDS)->all();
        $meta['payment_keys'] = array_keys((array) data_get($order, 'ЭтапыГрафикаОплаты.0', []));
        $meta['row_keys'] = array_keys((array) data_get($order, 'Товары.0', []));

        return OneCOrderProfile::query()->create([
            'name' => $name, 'customer_mode' => $mode, 'is_active' => true,
            'is_default' => ! OneCOrderProfile::query()->where('is_default', true)->exists(),
            'header' => $header, 'row_defaults' => $row, 'payment_stage' => $payment ?: null, 'meta' => $meta,
            'template_number' => (string) ($order['Number'] ?? ''),
        ]);
    }

    /** The scheme for an order: a company order takes the first company scheme, the rest the default one. */
    public function forOrder(bool $company): ?OneCOrderProfile
    {
        $active = OneCOrderProfile::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('sort')->orderBy('id')->get();

        return ($company ? $active->firstWhere('customer_mode', OneCOrderProfile::COMPANY) : null)
            ?? $active->firstWhere('is_default', true) ?? $active->first();
    }
}
