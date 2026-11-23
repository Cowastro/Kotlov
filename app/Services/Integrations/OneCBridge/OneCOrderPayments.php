<?php

namespace App\Services\Integrations\OneCBridge;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Payment of a customer order, read from 1C (register «Расчёты с клиентами»). Every 1C order has a settlement object
 * (ОбъектРасчетов): the order plans what is to be paid on it («КОплате», recorded by the order itself) and the
 * payments (bank, cash, card) reduce the debt («Сумма», expense). Two steps inside the 30-minute document check:
 * first the settlement object of the order (records of the order itself), then the records of that object.
 * When 1C has the whole amount paid, the site order becomes «Оплачен». Read-only for 1C.
 */
class OneCOrderPayments
{
    public const REGISTER = 'AccumulationRegister_РасчетыСКлиентами_RecordType';

    public const OBJECTS = 'Catalog_ОбъектыРасчетов';

    private const ORDER_TYPE = 'Document_ЗаказКлиента';

    /** cast(guid'…', 'Document_…') is long once encoded: fewer keys per request than plain keys. */
    private const KEYS_CHUNK = 5;

    private const CHUNK = 15;

    private const DAYS = 60;

    /** Orders whose payment 1C may still change: linked, not cancelled, not paid yet. */
    private function waiting(): Builder
    {
        return Order::query()->whereNotNull('onec_order_guid')->where('onec_deleted', false)->where('onec_posted', true)
            ->where('status', '!=', 'cancelled')
            // until it is paid and delivered: «готов к закрытию» needs the shipment too
            ->where(fn ($q) => $q->whereNull('payment_status')->orWhere('payment_status', '!=', 'paid')->orWhereNotIn('status', ['delivered', 'completed']))
            ->where('onec_exported_at', '>', now()->subDays(self::DAYS));
    }

    /** @return array<string, string> read queries for the document check */
    public function queries(): array
    {
        $queries = [];
        $find = $this->waiting()->whereNull('onec_settlement_key')->orderBy('id')->pluck('onec_order_guid')->unique()->values();
        foreach ($find->chunk(self::KEYS_CHUNK)->values() as $i => $chunk) {
            // Only an object of the order itself: settlements «по договору» are shared by every order of the customer
            // (ВАМэкспресс, the retail customer) — their records run to megabytes and say nothing of one order.
            $queries['pay_keys_'.($i + 1)] = ODataReadTaskHandler::path(self::OBJECTS, ['$filter' => $chunk
                ->map(fn (string $key): string => "Объект eq cast(guid'".$key."', '".self::ORDER_TYPE."')")->implode(' or '),
                '$select' => 'Ref_Key,Объект,Объект_Type']);
        }
        $objects = $this->waiting()->whereNotNull('onec_settlement_key')->orderBy('id')->pluck('onec_settlement_key')->unique()->values();
        foreach ($objects->chunk(self::CHUNK)->values() as $i => $chunk) {
            $queries['pay_'.($i + 1)] = ODataReadTaskHandler::path(self::REGISTER, ['$filter' => $chunk
                ->map(fn (string $key): string => "ОбъектРасчетов_Key eq guid'".$key."'")->implode(' or '),
                '$select' => 'ОбъектРасчетов_Key,Recorder_Type,RecordType,Active,КОплате,Сумма']);
        }

        return $queries;
    }

    /** Step 1: the settlement object whose «Объект» is the order itself. @return int orders keyed */
    public function rememberObjects(array $rows): int
    {
        $n = 0;
        foreach (collect($rows)->filter(fn (array $r): bool => str_ends_with((string) ($r['Объект_Type'] ?? ''), self::ORDER_TYPE))
            ->groupBy(fn (array $r): string => strtolower((string) ($r['Объект'] ?? ''))) as $orderKey => $records) {
            $object = strtolower((string) ($records->pluck('Ref_Key')->filter(fn ($k): bool => self::guid($k))->first() ?? ''));
            if ($orderKey === '' || $object === '') {
                continue;
            }
            $n += Order::query()->where('onec_order_guid', $orderKey)->whereNull('onec_settlement_key')->update(['onec_settlement_key' => $object]);
        }

        return $n;
    }

    /** Step 2: what is to be paid and what is paid per settlement object. @return int orders that became paid */
    public function apply(array $rows): int
    {
        $paidNow = 0;
        foreach (collect($rows)->filter(fn (array $r): bool => ($r['Active'] ?? true) !== false)
            ->groupBy(fn (array $r): string => strtolower((string) ($r['ОбъектРасчетов_Key'] ?? ''))) as $object => $records) {
            if ($object === '') {
                continue;
            }
            [$toPay, $paid, $shipped] = self::sums($records);
            foreach (Order::query()->where('onec_settlement_key', $object)->get() as $order) {
                $due = $toPay > 0 ? $toPay : (float) $order->total;
                $order->forceFill(['onec_to_pay' => $due, 'onec_paid' => $paid, 'onec_shipped' => $shipped, 'onec_paid_checked_at' => now()])->save();
                if ($due > 0 && $paid >= $due - 0.01 && $order->payment_status !== 'paid' && $order->status !== 'cancelled') {
                    $order->forceFill(['payment_status' => 'paid'])->save();
                    $order->managerComments()->create(['author_name' => 'Мост 1С',
                        'body' => 'Оплата по 1С: заказ '.$order->onec_order_number.' оплачен полностью ('.self::money($paid).' BYN).']);
                    $paidNow++;
                }
                // «Готов к закрытию» in 1C: shipped and paid — the site order is delivered.
                app(OneCOrderStages::class)->apply($order);
            }
        }

        return $paidNow;
    }

    /**
     * To pay: what the order planned («КОплате» it recorded itself). Paid: what payments took off the debt — «Сумма»
     * expense of every document other than the order (bank, cash, card, offset), less what was returned.
     *
     * Shipped: what sales put on the debt — «Сумма» receipt of every document other than the order.
     *
     * @return array{0: float, 1: float, 2: float}
     */
    public static function sums(Collection $records): array
    {
        $byOrder = fn (array $r): bool => str_ends_with((string) ($r['Recorder_Type'] ?? ''), self::ORDER_TYPE);
        $sign = fn (array $r): int => ($r['RecordType'] ?? '') === 'Expense' ? 1 : -1;
        $toPay = $records->filter($byOrder)->sum(fn (array $r): float => -$sign($r) * (float) ($r['КОплате'] ?? 0));
        $paid = $records->reject($byOrder)->filter(fn (array $r): bool => ($r['RecordType'] ?? '') === 'Expense')
            ->sum(fn (array $r): float => (float) ($r['Сумма'] ?? 0));

        $shipped = $records->reject($byOrder)->filter(fn (array $r): bool => ($r['RecordType'] ?? '') === 'Receipt')
            ->sum(fn (array $r): float => (float) ($r['Сумма'] ?? 0));

        return [round(max(0, $toPay), 2), round(max(0, $paid), 2), round(max(0, $shipped), 2)];
    }

    private static function guid(mixed $key): bool
    {
        return is_string($key) && preg_match('/^[0-9a-f-]{36}$/i', $key) && $key !== '00000000-0000-0000-0000-000000000000';
    }

    /** «Готов к закрытию» as 1C shows it: all shipped and all paid. */
    public static function readyToClose(Order $order): bool
    {
        $due = (float) $order->onec_to_pay;

        return $due > 0 && (float) $order->onec_paid >= $due - 0.01 && (float) $order->onec_shipped >= $due - 0.01;
    }

    /** As 1C puts it, short: the sum is the order's own. */
    public static function label(float $paid, float $toPay): string
    {
        return match (true) {
            $toPay > 0 && $paid >= $toPay - 0.01 => 'оплачен',
            $paid > 0 => 'оплачен частично',
            default => 'ожидается оплата',
        };
    }

    public static function money(float $sum): string
    {
        return number_format($sum, 2, ',', ' ');
    }
}
