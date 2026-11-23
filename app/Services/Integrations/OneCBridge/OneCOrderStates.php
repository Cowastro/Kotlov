<?php

namespace App\Services\Integrations\OneCBridge;

use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The state of a customer order as 1C shows it in its list (register «Состояния заказов клиентов»): «Ожидается
 * обеспечение», «Готов к отгрузке», «Готов к закрытию», «Закрыт»… with the share paid and shipped. Read with every
 * document check; the site stage and «Оплачен» follow it. Read-only for 1C.
 */
class OneCOrderStates
{
    public const ENTITY = 'InformationRegister_СостоянияЗаказовКлиентов';

    private const ORDER_TYPE = 'Document_ЗаказКлиента';

    /** cast(guid'…', 'Document_…') is long once encoded. */
    private const CHUNK = 5;

    private const DAYS = 60;

    /** Linked orders 1C may still move or still get paid. */
    private function waiting(): Builder
    {
        return Order::query()->whereNotNull('onec_order_guid')->where('onec_deleted', false)->where('status', '!=', 'cancelled')
            ->where(fn ($q) => $q->whereNotIn('status', ['delivered', 'completed'])->orWhereNull('payment_status')->orWhere('payment_status', '!=', 'paid'))
            ->where('onec_exported_at', '>', now()->subDays(self::DAYS));
    }

    /** @return array<string, string> */
    public function queries(): array
    {
        $queries = [];
        $keys = $this->waiting()->orderBy('id')->pluck('onec_order_guid')->map(fn ($k): string => strtolower((string) $k))->unique()->values();
        foreach ($keys->chunk(self::CHUNK)->values() as $i => $chunk) {
            $queries['states_'.($i + 1)] = ODataReadTaskHandler::path(self::ENTITY, [
                '$filter' => $chunk->map(fn (string $key): string => "Заказ eq cast(guid'".$key."', '".self::ORDER_TYPE."')")->implode(' or '),
                '$select' => 'Заказ,Состояние,СуммаОплаты,ПроцентОплаты,ПроцентОтгрузки,СуммаДолга',
            ]);
        }

        return $queries;
    }

    /** @return array{0: int, 1: int} orders paid now, stages moved */
    public function apply(array $rows, ?CarbonInterface $observedAt = null, array $cancelledKeys = []): array
    {
        $observedAt ??= now();
        $paid = 0;
        $moved = 0;
        foreach ($rows as $row) {
            [$rowPaid, $rowMoved] = DB::transaction(function () use ($row, $observedAt, $cancelledKeys): array {
                $key = strtolower((string) ($row['Заказ'] ?? ''));
                $order = $key !== '' ? Order::query()->where('onec_order_guid', $key)->lockForUpdate()->first() : null;
                if (! $order || $order->onec_paid_checked_at?->gt($observedAt)) {
                    return [0, 0];
                }
                $paid = 0;
                $order->forceFill([
                    'onec_state' => (string) ($row['Состояние'] ?? '') ?: null,
                    'onec_paid' => (float) ($row['СуммаОплаты'] ?? 0),
                    'onec_paid_percent' => (float) ($row['ПроцентОплаты'] ?? 0),
                    'onec_shipped_percent' => (float) ($row['ПроцентОтгрузки'] ?? 0),
                    'onec_paid_checked_at' => $observedAt,
                ])->save();
                if ((float) ($row['ПроцентОплаты'] ?? 0) >= 100 && $order->payment_status !== 'paid' && $order->status !== 'cancelled') {
                    $order->forceFill(['payment_status' => 'paid'])->save();
                    $order->managerComments()->create(['author_name' => 'Мост 1С',
                        'body' => 'Оплата по 1С: заказ '.$order->onec_order_number.' оплачен полностью ('.OneCOrderPayments::money((float) ($row['СуммаОплаты'] ?? 0)).' BYN).']);
                    $paid++;
                }

                return [$paid, app(OneCOrderStages::class)->apply($order, in_array($key, $cancelledKeys, true)) ? 1 : 0];
            });
            $paid += $rowPaid;
            $moved += $rowMoved;
        }

        return [$paid, $moved];
    }

    /** «ГотовКОтгрузке» → «Готов к отгрузке» */
    public static function label(?string $state): ?string
    {
        if (blank($state)) {
            return null;
        }
        $words = preg_split('/(?<=\p{L})(?=\p{Lu})/u', (string) $state) ?: [(string) $state];

        return mb_strtoupper(mb_substr($words[0], 0, 1)).mb_strtolower(mb_substr(implode(' ', $words), 1));
    }

    /** As 1C has it, short: the sum is the order's own. */
    public static function paymentLabel(?float $percent): ?string
    {
        return match (true) {
            $percent === null => null,
            $percent >= 100 => 'оплачен',
            $percent > 0 => 'оплачен частично',
            default => 'ожидается оплата',
        };
    }
}
