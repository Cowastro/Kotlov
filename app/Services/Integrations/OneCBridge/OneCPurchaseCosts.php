<?php

namespace App\Services\Integrations\OneCBridge;

use App\Models\IntegrationProduct;
use App\Models\OneCAgentTask;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The cost of a card from 1C: the price of its last posted «Приобретение товаров и услуг» (this 1C keeps no purchase
 * price type, and its cost register is kept by accounting analytics, not by item). Read every morning for the last
 * days, once for a year back; only BYN purchases count.
 */
class OneCPurchaseCosts
{
    public const ENTITY = 'Document_ПриобретениеТоваровУслуг';

    public const BYN = '11867b41-09b8-11e9-bad6-1c1b0d1de7c2';

    public const RUB = '11867b44-09b8-11e9-bad6-1c1b0d1de7c2';

    /** Days of purchases per request: an answer (whole documents with their lines) stays far below 8 MB. */
    private const DAYS_PER_PART = 7;

    public function __construct(private readonly OneCAgentTasks $tasks) {}

    public function enqueue(?User $user, int $days = 10): OneCAgentTask
    {
        $queries = [];
        $to = now('Europe/Minsk')->addDay()->startOfDay();
        for ($i = 1; $i <= (int) ceil(max(1, $days) / self::DAYS_PER_PART); $i++) {
            $from = $to->copy()->subDays(self::DAYS_PER_PART);
            $queries['purchases_'.$i] = ODataReadTaskHandler::path(self::ENTITY, [
                '$filter' => "Date ge datetime'".$from->format('Y-m-d\TH:i:s')."' and Date lt datetime'".$to->format('Y-m-d\TH:i:s')."' and Posted eq true",
                '$orderby' => 'Date']);
            $to = $from;
        }

        return $this->tasks->enqueue(ODataReadTaskHandler::TYPE, null, 'Закупки 1С за '.$days.' дн.', $user, ['queries' => $queries, 'purchases' => true]);
    }

    /** Keeps the purchase lines 1C gave and updates the cost of the cards they belong to. */
    public function import(OneCAgentTask $task): string
    {
        $lines = 0;
        $docs = 0;
        $items = [];
        $now = now();
        foreach (array_keys((array) data_get($task->payload, 'queries', [])) as $name) {
            $part = ODataReadTaskHandler::part($task, $name);
            if ($part['code'] !== 200) {
                continue;
            }
            foreach ((array) data_get($part['json'], 'value', []) as $doc) {
                $key = strtolower((string) ($doc['Ref_Key'] ?? ''));
                if (! preg_match('/^[0-9a-f-]{36}$/', $key) || ! ($doc['Posted'] ?? false) || ($doc['DeletionMark'] ?? false)) {
                    continue;
                }
                $docs++;
                $rows = [];
                foreach ((array) ($doc['Товары'] ?? []) as $row) {
                    $item = strtolower((string) ($row['Номенклатура_Key'] ?? ''));
                    $qty = (float) ($row['Количество'] ?? 0);
                    if (! preg_match('/^[0-9a-f-]{36}$/', $item) || $qty <= 0) {
                        continue;
                    }
                    $gross = (float) ($row['СуммаСНДС'] ?? 0);
                    $vat = (float) ($row['СуммаНДС'] ?? 0);
                    $net = $gross > 0 ? $gross - $vat : (float) ($row['Сумма'] ?? 0);
                    if ($gross <= 0) {
                        $gross = $net + $vat;
                    }
                    $rows[] = ['doc_key' => $key, 'line_number' => (int) ($row['LineNumber'] ?? 0), 'doc_number' => mb_substr((string) ($doc['Number'] ?? ''), 0, 50),
                        'doc_date' => substr((string) ($doc['Date'] ?? ''), 0, 19), 'item_key' => $item,
                        'characteristic_key' => strtolower((string) ($row['Характеристика_Key'] ?? '')) ?: null, 'quantity' => $qty,
                        'price_net' => round($net / $qty, 4), 'price_gross' => round($gross / $qty, 4),
                        'currency_key' => strtolower((string) ($doc['Валюта_Key'] ?? '')) ?: null,
                        'partner_key' => strtolower((string) ($doc['Партнер_Key'] ?? '')) ?: null,
                        'organization_key' => strtolower((string) ($doc['Организация_Key'] ?? '')) ?: null, 'synced_at' => $now];
                    $items[$item] = true;
                }
                DB::transaction(function () use ($key, $rows): void {
                    DB::table('onec_purchase_lines')->where('doc_key', $key)->delete();
                    foreach (array_chunk($rows, 200) as $chunk) {
                        DB::table('onec_purchase_lines')->insert($chunk);
                    }
                });
                $lines += count($rows);
            }
        }
        $cards = $this->refreshCosts(array_keys($items));

        return 'Поступлений: '.$docs.', строк: '.$lines.', себестоимость обновлена у карточек: '.$cards;
    }

    /** @param list<string> $itemKeys 1C items whose cards get the price of their last BYN purchase */
    public function refreshCosts(array $itemKeys): int
    {
        $updated = 0;
        foreach (array_chunk($itemKeys, 200) as $chunk) {
            $last = DB::table('onec_purchase_lines')->whereIn('item_key', $chunk)->whereIn('currency_key', [self::BYN, self::RUB])
                ->orderBy('doc_date')->orderBy('id')->get()->keyBy('item_key');
            $links = IntegrationProduct::query()->whereHas('source', fn ($q) => $q->where('code', 'onec'))->whereNotNull('product_id')
                ->where('match_status', 'matched')
                ->where(function ($q) use ($chunk): void {
                    foreach ($chunk as $key) {
                        $q->orWhere('external_id', $key)->orWhere('external_id', 'like', $key.'#%');
                    }
                })->get(['external_id', 'product_id']);
            foreach ($links as $link) {
                $line = $last->get(strtolower(explode('#', (string) $link->external_id)[0]));
                if (! $line) {
                    continue;
                }
                $line = HistoricalPurchaseRate::inByn($line);
                $updated += Product::query()->whereKey($link->product_id)
                    ->where(fn ($q) => $q->whereNull('onec_cost_date')->orWhere('onec_cost_date', '<=', substr((string) $line->doc_date, 0, 10)))
                    ->update(['onec_cost_byn' => round((float) $line->price_net, 2), 'onec_cost_vat_byn' => round((float) $line->price_gross, 2),
                        'onec_cost_date' => substr((string) $line->doc_date, 0, 10), 'onec_cost_doc' => $line->doc_number]);
            }
        }
        // the cost of the 1C items too: the price list works from it
        app(OneCItemCosts::class)->refresh($itemKeys);

        return $updated;
    }
}
