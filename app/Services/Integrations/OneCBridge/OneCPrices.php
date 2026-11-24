<?php

namespace App\Services\Integrations\OneCBridge;

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
    /** The records with their date: the register is subordinate to «Установка цен номенклатуры». */
    public const ENTITY = 'InformationRegister_ЦеныНоменклатуры_RecordType';

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
            $filter = "Active eq true and ВидЦены_Key eq guid'".$type."'".($since ? " and Period ge datetime'".$since."'" : '');
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
                foreach ($last[$kind] ?? [] as $key => [$period, $price]) {
                    $value = $price > 0 ? round($price * $vat, 2) : null;
                    $values = [$column => $value] + ($kind === 'retail' ? ['price_retail_at' => substr((string) $period, 0, 10) ?: null] : []);
                    if (OneCItem::query()->where('ref_key', $key)->where(fn ($q) => $q->whereNull($column)->orWhere($column, '!=', $value))->update($values) && $kind === 'retail') {
                        $changed[] = $key;
                    }
                }
            }
        });
        OneCItem::query()->whereIn('ref_key', array_keys(($last['retail'] ?? []) + ($last['wholesale'] ?? [])))->update(['prices_synced_at' => now()]);
        Cache::forever(self::LAST_READ, now()->subDay()->format('Y-m-d\T00:00:00'));
        // every card each time: a good that comes into stock takes its 1C retail price
        [$applied, $released] = $this->applyToCards();

        return 'Цены 1С: розница '.count($last['retail'] ?? []).', опт '.count($last['wholesale'] ?? []).($applied ? ', цен на сайте изменено: '.$applied : '')
            .($released ? ', карточек без наличия отпущено: '.$released : '')
            .($fullPages > 0 && data_get($task->payload, 'since') === null && $fullPages >= self::FULL_PAGES ? ' (прочитаны не все записи — увеличить FULL_PAGES)' : '');
    }

    /** Days a retail price set in 1C leads the site even for a good not in stock (a fresh «Установка цен»). */
    public const FRESH_DAYS = 30;

    /**
     * The 1C retail price leads the card when the good is in stock on the selling warehouse, or it was set in 1C lately.
     * An old 1C price of a good not in stock may be stale: the card keeps its own then.
     */
    public static function leads(OneCItem $item): bool
    {
        if ((float) $item->price_retail <= 0) {
            return false;
        }
        // a retail price below the last purchase is old (Мета-Бел after its new list): the card keeps its own
        if ((float) $item->cost_gross > 0 && (float) $item->price_retail < (float) $item->cost_gross) {
            return false;
        }
        $inStock = OneCStock::fresh($item) && (float) $item->stock_on_hand > 0;

        return $inStock || ($item->price_retail_at !== null && $item->price_retail_at->gte(now()->subDays(self::FRESH_DAYS)->startOfDay()));
    }

    /**
     * One price everywhere, where 1C leads: the card takes the 1C retail price. Where it does not (not in stock, an old
     * price) the card is let go — its price is no more held to the 1C one. @return array{0: int, 1: int} changed, let go
     */
    public function applyToCards(): array
    {
        $changed = 0;
        $released = 0;
        OneCItem::query()->whereNotNull('product_id')->where('is_folder', false)
            ->chunkById(500, function ($items) use (&$changed, &$released): void {
                $cards = Product::query()->whereIn('id', $items->pluck('product_id'))->get()->keyBy('id');
                foreach ($items as $item) {
                    $card = $cards[$item->product_id] ?? null;
                    if (! $card) {
                        continue;
                    }
                    if (! self::leads($item)) {
                        if ($card->onec_retail_byn !== null) {
                            $card->forceFill(['onec_retail_byn' => null, 'onec_retail_at' => null])->save();
                            $released++;
                        }

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

        return [$changed, $released];
    }
}
