<?php

namespace App\Services\Integrations\OneCBridge;

use App\Models\OneCAgentTask;
use App\Models\OneCOrderProfile;
use App\Models\OneCReference;
use App\Models\OneCSupplierOrder;
use App\Models\Order;
use App\Models\Supplier;
use App\Models\User;
use DomainException;
use Illuminate\Support\Str;

/**
 * «Заказ поставщику» in 1C for the lines of a customer order bought from a supplier: one per supplier, made after a
 * real 1C supplier order (organization, warehouse, VAT, payment terms), the supplier and its contract found in 1C by
 * УНП, the lines at the purchase prices of the order. Created unposted; 1C items are never created from here.
 */
class OneCSupplierOrderExport
{
    public const ENTITY = 'Document_ЗаказПоставщику';

    /** Routes of order lines bought from a supplier. */
    public const ROUTES = ['supplier_purchase', 'direct_supplier'];

    public function __construct(
        private readonly OneCOrderExport $orders,
        private readonly OneCReferences $refs,
        private readonly OneCAgentTasks $tasks,
    ) {}

    /**
     * @return array<int, array{supplier: Supplier, lines: list<array<string, mixed>>, total: float, tax_id: string,
     *     counterparty: ?OneCReference, record: ?OneCSupplierOrder}> supplier id => what goes to that supplier
     */
    public function groups(Order $order): array
    {
        $order->loadMissing('items.fulfillmentSupplier.crmCounterparty');
        $lines = collect($this->orders->lines($order))->keyBy(fn (array $l): int => $l['item']->id);
        $groups = [];
        foreach ($order->items as $item) {
            if (! in_array($item->fulfillment_route, self::ROUTES, true) || ! $item->fulfillment_supplier_id || ! $item->fulfillmentSupplier) {
                continue;
            }
            $line = $lines->get($item->id);
            if (! $line) {
                continue;
            }
            $price = (float) ($item->fulfillment_purchase_price ?: $item->supply_purchase_price);
            $line['purchase_price'] = $price > 0 ? $price : null;
            if ($line['purchase_price'] === null) {
                $line['problem'] ??= 'нет входной цены';
            }
            $id = (int) $item->fulfillment_supplier_id;
            $groups[$id] ??= ['supplier' => $item->fulfillmentSupplier, 'lines' => [], 'total' => 0.0,
                'tax_id' => trim((string) $item->fulfillmentSupplier->crmCounterparty?->unp), 'counterparty' => null, 'record' => null];
            $groups[$id]['lines'][] = $line;
            $groups[$id]['total'] += (float) $line['purchase_price'] * $line['qty'];
        }
        foreach ($groups as $id => &$group) {
            $group['counterparty'] = $group['tax_id'] !== '' ? $this->orders->counterpartyByTaxId($group['tax_id']) : null;
            $group['record'] = OneCSupplierOrder::query()->where('order_id', $order->id)->where('supplier_id', $id)->first();
        }

        return $groups;
    }

    /** @return array<int, string> */
    public function profiles(): array
    {
        return OneCOrderProfile::query()->where('document', OneCOrderProfile::SUPPLIER_ORDER)->where('is_active', true)
            ->orderByDesc('is_default')->orderBy('sort')->orderBy('id')->pluck('name', 'id')->all();
    }

    /** Already in 1C (or on its way there): this supplier is not ordered from again for this order. */
    public static function sent(?OneCSupplierOrder $record): bool
    {
        return $record !== null && ($record->status === OneCSupplierOrder::QUEUED || ($record->onec_order_guid && ! $record->onec_deleted));
    }

    /** @return list<string> */
    public function problems(array $group, ?OneCOrderProfile $profile, ?string $counterparty): array
    {
        $problems = [];
        if (! $profile) {
            $problems[] = 'нет схемы заказа поставщику — создайте её в «Схемы заказов 1С» по образцу заказа поставщику из 1С';
        }
        if (! $counterparty) {
            $problems[] = 'поставщик не найден в 1С'.($group['tax_id'] !== '' ? ' по УНП '.$group['tax_id'] : ' — у поставщика на сайте нет УНП').': выберите контрагента';
        }
        foreach ($group['lines'] as $line) {
            if ($line['problem']) {
                $problems[] = '«'.$line['name'].'»: '.$line['problem'];
            }
        }
        if (self::sent($group['record'])) {
            $problems[] = 'заказ поставщику уже '.($group['record']->onec_order_number ? 'есть в 1С: '.$group['record']->onec_order_number : 'отправлен в 1С');
        }

        return $problems;
    }

    /** @return array<string, mixed> the «Заказ поставщику» for OData */
    public function body(Order $order, array $group, OneCOrderProfile $profile, string $counterparty, ?string $contract, bool $pricesWithVat): array
    {
        $meta = $profile->meta ?? [];
        $has = fn (string $key): bool => in_array($key, (array) ($meta['keys'] ?? []), true);
        $now = now('Europe/Minsk')->format('Y-m-d\TH:i:s');
        $doc = collect($profile->header)->except(['ИдентификаторПлатежа', 'МаксимальныйКодСтроки', 'Назначение_Key'])->all();
        $doc['Date'] = $now;
        $doc['Контрагент_Key'] = $counterparty;
        $doc['Партнер_Key'] = $this->refs->find('counterparty', $counterparty)?->owner_key ?: ($doc['Партнер_Key'] ?? OneCReferences::ZERO);
        $doc['Договор_Key'] = $contract ?: OneCReferences::ZERO;
        // When the goods come: today + the longest delivery time of the lines (3 days when unknown). 1C lists supplier
        // orders by it; an empty one puts the order among old ones and leaves «Желаемая дата поступления» blank.
        $days = (int) collect($group['lines'])->map(fn (array $l): int => (int) $l['item']->supply_delivery_days)->max();
        $arrive = now('Europe/Minsk')->startOfDay()->addDays($days > 0 ? $days : 3)->format('Y-m-d\TH:i:s');
        foreach (['ЖелаемаяДатаПоступления', 'ДатаПоступления'] as $key) {
            if ($has($key)) {
                $doc[$key] = $arrive;
            }
        }
        if (in_array('ДатаСогласования', (array) ($meta['dates'] ?? []), true)) {
            $doc['ДатаСогласования'] = $now;
        }
        $doc['Комментарий'] = 'Под заказ клиента kotlov.by №'.$order->number.($order->onec_order_number ? ' (1С: '.$order->onec_order_number.')' : '');

        $withVat = (bool) ($doc['ЦенаВключаетНДС'] ?? false);
        $rate = $this->refs->vatRate($profile->row_defaults['СтавкаНДС_Key'] ?? null);
        $rowKeys = (array) ($meta['row_keys'] ?? []);
        $rows = [];
        $grossTotal = 0.0;
        $vatTotal = 0.0;
        foreach (array_values($group['lines']) as $i => $line) {
            $n = $i + 1;
            $gross = round((float) $line['purchase_price'] * $line['qty'] * ($pricesWithVat ? 1 : 1 + $rate), 2);
            $vat = round($gross * $rate / (1 + $rate), 2);
            $sum = $withVat ? $gross : round($gross - $vat, 2);
            $row = array_merge((array) $profile->row_defaults, [
                'LineNumber' => (string) $n, 'Номенклатура_Key' => $line['key'],
                'Характеристика_Key' => $line['characteristic'] ?: OneCReferences::ZERO,
                'КоличествоУпаковок' => $line['qty'], 'Количество' => $line['qty'],
                'Цена' => round($sum / max($line['qty'], 0.001), 2), 'Сумма' => $sum, 'СуммаНДС' => $vat, 'СуммаСНДС' => $gross,
                'Склад_Key' => $doc['Склад_Key'] ?? OneCReferences::ZERO, 'Отменено' => false,
            ]);
            foreach (['КодСтроки' => $n, 'ИдентификаторСтроки' => (string) Str::uuid(), 'ДатаПоступления' => $arrive] as $key => $value) {
                if (in_array($key, $rowKeys, true)) {
                    $row[$key] = $value;
                }
            }
            $rows[] = $row;
            $grossTotal += $gross;
            $vatTotal += $vat;
        }
        $doc['Товары'] = $rows;
        $doc['СуммаДокумента'] = round($grossTotal, 2);
        if ($has('МаксимальныйКодСтроки')) {
            $doc['МаксимальныйКодСтроки'] = count($rows);
        }
        if ($profile->payment_stage) {
            $stage = array_merge($profile->payment_stage, ['LineNumber' => '1']);
            foreach (['ДатаПлатежа' => $now, 'ПроцентПлатежа' => 100, 'СуммаПлатежа' => round($grossTotal, 2), 'СуммаНДС' => round($vatTotal, 2)] as $key => $value) {
                if (in_array($key, (array) ($meta['payment_keys'] ?? []), true)) {
                    $stage[$key] = $value;
                }
            }
            $doc['ЭтапыГрафикаОплаты'] = [$stage];
        }

        return $doc;
    }

    /** @param array{profile_id: mixed, counterparty: ?string, contract: ?string, prices_with_vat: bool} $form */
    public function enqueue(Order $order, int $supplierId, array $form, ?User $user): OneCAgentTask
    {
        $order = $order->fresh();
        if (! $order->onec_order_guid && ($reason = app(\App\Services\Orders\OrderDeliveryPreparation::class)->blocker($order))) {
            throw new DomainException($reason);
        }
        $group = $this->groups($order)[$supplierId] ?? throw new DomainException('У поставщика нет позиций в этом заказе.');
        $profile = OneCOrderProfile::query()->where('document', OneCOrderProfile::SUPPLIER_ORDER)->where('is_active', true)->find($form['profile_id'] ?? null);
        $counterparty = strtolower((string) ($form['counterparty'] ?? '')) ?: $group['counterparty']?->ref_key;
        $problems = $this->problems($group, $profile, $counterparty);
        if ($problems !== []) {
            throw new DomainException($group['supplier']->name.': '.implode('; ', $problems));
        }
        $body = $this->body($order, $group, $profile, $counterparty, $form['contract'] ?? null, (bool) ($form['prices_with_vat'] ?? true));
        $record = OneCSupplierOrder::query()->updateOrCreate(['order_id' => $order->id, 'supplier_id' => $supplierId], [
            'profile_id' => $profile->id, 'status' => OneCSupplierOrder::QUEUED, 'counterparty_key' => $counterparty, 'total' => $body['СуммаДокумента'],
            'onec_order_guid' => null, 'onec_order_number' => null, 'onec_posted' => null, 'onec_deleted' => false, 'onec_doc_status' => null,
        ]);

        return $this->tasks->enqueue(SupplierOrderTaskHandler::TYPE, $record, 'Заказ поставщику: '.Str::limit($group['supplier']->name, 60)
            .' (заказ №'.$order->number.')', $user, ['body' => $body]);
    }
}
