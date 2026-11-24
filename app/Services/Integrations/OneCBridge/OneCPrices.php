<?php

namespace App\Services\Integrations\OneCBridge;

use App\Models\IntegrationProduct;
use App\Models\OneCAgentTask;
use App\Models\OneCItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Retail and wholesale prices of every 1C item (register «Цены номенклатуры», the manual types «Розничный тип цен» and
 * «Оптовая цена», without VAT; with VAT = × the VAT rate, as 1C's «Розничная с НДС» / «Оптовая с НДС»). The register
 * has no last-price slice in this OData publication: its records are read — all once, then those since the last read
 * — and the last one per item wins. One price everywhere: a 1C retail price is the price of the linked site card.
 */
class OneCPrices
{
    public const ENTITY = 'InformationRegister_ЦеныНоменклатуры';

    /** «Розничный тип цен» and «Оптовая цена» (manual, without VAT). */
    public const RETAIL = '7b0fbdc2-1ffa-11e9-bad6-1c1b0d1de7c2';

    public const WHOLESALE = 'de262f38-66a1-11e9-9b7c-1c1b0d1de7c2';

    /** Records per request (an answer stays far below 8 MB) and requests per type for the first, whole read. */
    private const PAGE = 5000;

    private const FULL_PAGES = 12;

    private const LAST_READ = 'onec_prices_last_read';

    public function __construct(private readonly OneCAgentTasks $tasks) {}

    public function enqueue(?User $user, bool $full = false): OneCAgentTask
    {
        $since = $full ? null : Cache::get(self::LAST_READ);
        $queries = [];
        foreach (['retail' => self::RETAIL, 'wholesale' => self::WHOLESALE] as $kind => $type) {
            $filter = "ВидЦены_Key eq guid'".$type."'".($since ? " and Period ge datetime'".$since."'" : '');
            for ($page = 0; $page < ($since ? 2 : self::FULL_PAGES); $page++) {
                $queries[$kind.'_'.($page + 1)] = ODataReadTaskHandler::path(self::ENTITY, ['$filter' => $filter,
                    '$select' => 'Period,Номенклатура_Key,Цена', '$orderby' => 'Period desc', '$top' => (string) self::PAGE, '$skip' => (string) ($page * self::PAGE)]);
            }
        }

        return $this->tasks->enqueue(ODataReadTaskHandler::TYPE, null, 'Цены 1С'.($since ? ' с '.substr($since, 0, 10) : ' — все'), $user,
            ['queries' => $queries, 'prices' => true, 'since' => $since]);
    }

    public function import(OneCAgentTask $task): string
    {
        $last = [];
        $read = 0;
        $fullPages = 0;
        foreach (array_keys((array) data_get($task->payload, 'queries', [])) as $name) {
            $part = ODataReadTaskHandler::part($task, $name);
            if ($part['code'] !== 200) {
                continue;
            }
            $kind = str_starts_with($name, 'retail_') ? 'retail' : 'wholesale';
            $rows = (array) data_get($part['json'], 'value', []);
            $fullPages += count($rows) >= self::PAGE ? 1 : 0;
            foreach ($rows as $row) {
                $key = strtolower((string) ($row['Номенклатура_Key'] ?? ''));
                $period = (string) ($row['Period'] ?? '');
                // newest first; the same moment twice (two characteristics) — the later row
                if ($key === '' || (isset($last[$kind][$key]) && $last[$kind][$key][0] > $period)) {
                    continue;
                }
                $last[$kind][$key] = [$period, (float) ($row['Цена'] ?? 0)];
                $read++;
            }
        }
        if ($read === 0 && data_get($task->payload, 'since') === null) {
            return 'Цены 1С не прочитаны';
        }
        $vat = (float) config('onec.price_documents.vat_rate', 1.2);
        $changed = [];
        DB::transaction(function () use ($last, $vat, &$changed): void {
            foreach (['retail' => 'price_retail', 'wholesale' => 'price_wholesale'] as $kind => $column) {
                foreach ($last[$kind] ?? [] as $key => [, $price]) {
                    $value = $price > 0 ? round($price * $vat, 2) : null;
                    if (OneCItem::query()->where('ref_key', $key)->where(fn ($q) => $q->whereNull($column)->orWhere($column, '!=', $value))->update([$column => $value]) && $kind === 'retail') {
                        $changed[] = $key;
                    }
                }
            }
        });
        OneCItem::query()->whereIn('ref_key', array_keys(($last['retail'] ?? []) + ($last['wholesale'] ?? [])))->update(['prices_synced_at' => now()]);
        Cache::forever(self::LAST_READ, now()->subDay()->format('Y-m-d\T00:00:00'));
        // a first read covers every card; then only those whose 1C retail price changed
        $applied = $this->applyToCards(data_get($task->payload, 'since') === null ? null : $changed);

        return 'Цены 1С: розница '.count($last['retail'] ?? []).', опт '.count($last['wholesale'] ?? []).($applied ? ', цен на сайте изменено: '.$applied : '')
            .($fullPages > 0 && data_get($task->payload, 'since') === null && $fullPages >= self::FULL_PAGES ? ' (прочитаны не все записи — увеличить FULL_PAGES)' : '');
    }

    /** The 1C retail price becomes the price of every linked card; a card without one keeps its price. @return int cards changed */
    /** @param list<string>|null $keys only these 1C items; null — every one with a retail price */
    public function applyToCards(?array $keys = null): int
    {
        if ($keys === []) {
            return 0;
        }
        $changed = 0;
        OneCItem::query()->whereNotNull('price_retail')->where('price_retail', '>', 0)->where('is_folder', false)
            ->when($keys !== null, fn ($q) => $q->whereIn('ref_key', $keys))
            ->chunkById(500, function ($items) use (&$changed): void {
                $links = IntegrationProduct::query()->whereHas('source', fn ($q) => $q->where('code', 'onec'))->whereNotNull('product_id')
                    ->where(fn ($q) => $q->whereNull('match_status')->orWhere('match_status', '!=', 'ignored'))
                    ->where(function ($q) use ($items): void {
                        foreach ($items as $item) {
                            $q->orWhere('external_id', $item->ref_key)->orWhere('external_id', 'like', $item->ref_key.'#%');
                        }
                    })->get(['external_id', 'product_id']);
                $byKey = $items->keyBy('ref_key');
                foreach ($links as $link) {
                    $item = $byKey[strtolower(explode('#', (string) $link->external_id)[0])] ?? null;
                    $card = $item ? Product::query()->find($link->product_id) : null;
                    if (! $card) {
                        continue;
                    }
                    $price = (float) $item->price_retail;
                    if (abs((float) $card->onec_retail_byn - $price) < 0.005 && abs((float) $card->price - $price) < 0.005) {
                        continue;
                    }
                    $card->forceFill(['onec_retail_byn' => $price, 'onec_retail_at' => now()]);
                    if (! $card->onSaleNow()) {
                        $card->forceFill(['price' => $price, 'requires_price_confirmation' => false]);
                    }
                    $card->save();
                    $changed++;
                }
            });

        return $changed;
    }
}
