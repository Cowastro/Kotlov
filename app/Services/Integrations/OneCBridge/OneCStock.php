<?php

namespace App\Services\Integrations\OneCBridge;

use App\Models\OneCAgentTask;
use App\Models\OneCItem;
use App\Models\OneCReference;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Stock of 1C items on the warehouses the site sells from (register «Товары на складах», balance): in stock and
 * promised to orders («к отгрузке», the reserve). Free = in stock − reserve. Read-only for 1C, every 30 minutes.
 */
class OneCStock
{
    public const REGISTER = 'AccumulationRegister_ТоварыНаСкладах/Balance';

    /** The warehouses the site sells from, by their 1C name. */
    public const WAREHOUSES = ['Основной'];

    /** Older than this, the 1C stock is not trusted (the agent may be off): the exchange stock is used. */
    private const FRESH_HOURS = 3;

    public function __construct(private readonly OneCAgentTasks $tasks) {}

    /** @return list<string> */
    public function warehouseKeys(): array
    {
        return OneCReference::query()->where('kind', 'warehouse')->where('is_folder', false)->where('deletion_mark', false)
            ->whereIn('description', self::WAREHOUSES)->pluck('ref_key')->map(fn ($k): string => strtolower((string) $k))->values()->all();
    }

    public function enqueue(?User $user): OneCAgentTask
    {
        $keys = $this->warehouseKeys();
        if ($keys === []) {
            throw new DomainException('Склад «'.implode('», «', self::WAREHOUSES).'» не найден: обновите справочники 1С.');
        }
        $query = ODataReadTaskHandler::path(self::REGISTER, [
            '$filter' => collect($keys)->map(fn (string $k): string => "Склад_Key eq guid'".$k."'")->implode(' or '),
            '$select' => 'Номенклатура_Key,ВНаличииBalance,КОтгрузкеBalance',
        ]);

        return $this->tasks->enqueue(ODataReadTaskHandler::TYPE, null, 'Остатки 1С: '.implode(', ', self::WAREHOUSES), $user,
            ['queries' => ['stock' => $query], 'stock' => true]);
    }

    /** Every 1C item gets its stock; one not in the answer has none. */
    public function import(OneCAgentTask $task): string
    {
        $part = ODataReadTaskHandler::part($task, 'stock');
        if ($part['code'] !== 200) {
            return 'Остатки не прочитаны: 1С ответила '.$part['code'];
        }
        $stock = [];
        foreach ((array) data_get($part['json'], 'value', []) as $row) {
            $key = strtolower((string) ($row['Номенклатура_Key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $stock[$key] ??= [0.0, 0.0];
            $stock[$key][0] += (float) ($row['ВНаличииBalance'] ?? 0);
            $stock[$key][1] += (float) ($row['КОтгрузкеBalance'] ?? 0);
        }
        $now = now();
        DB::transaction(function () use ($stock, $now): void {
            OneCItem::query()->where(fn ($q) => $q->where('stock_on_hand', '!=', 0)->orWhere('stock_reserved', '!=', 0))
                ->update(['stock_on_hand' => 0, 'stock_reserved' => 0]);
            foreach ($stock as $key => [$onHand, $reserved]) {
                OneCItem::query()->where('ref_key', $key)->update(['stock_on_hand' => round($onHand, 3), 'stock_reserved' => round($reserved, 3)]);
            }
            OneCItem::query()->update(['stock_synced_at' => $now]);
        });
        $free = collect($stock)->filter(fn (array $s): bool => $s[0] - $s[1] > 0)->count();

        return 'Остатки 1С ('.implode(', ', self::WAREHOUSES).'): товаров в наличии '.collect($stock)->filter(fn (array $s): bool => $s[0] > 0)->count()
            .', свободно '.$free.', в резерве '.collect($stock)->filter(fn (array $s): bool => $s[1] > 0)->count();
    }

    public static function fresh(?OneCItem $item): bool
    {
        return $item?->stock_synced_at !== null && $item->stock_synced_at->gt(now()->subHours(self::FRESH_HOURS));
    }

    public static function free(OneCItem $item): float
    {
        return max(0.0, (float) $item->stock_on_hand - (float) $item->stock_reserved);
    }

    /** «своб. 2 (в нал. 3, резерв 1)» */
    public static function label(OneCItem $item): string
    {
        $n = fn (float $v): string => rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');

        return 'своб. '.$n(self::free($item)).((float) $item->stock_reserved > 0
            ? ' (в нал. '.$n((float) $item->stock_on_hand).', резерв '.$n((float) $item->stock_reserved).')' : '');
    }
}
