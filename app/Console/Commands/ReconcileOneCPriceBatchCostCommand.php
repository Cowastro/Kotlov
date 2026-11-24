<?php

namespace App\Console\Commands;

use App\Models\OneCPriceBatch;
use App\Services\Integrations\OneCBridge\OneCPurchaseCosts;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileOneCPriceBatchCostCommand extends Command
{
    protected $signature = 'onec:reconcile-batch-cost {batch} {receipt} {date} {--code=*} {--apply}';

    protected $description = 'Сверить закупки выбранных строк пакета с проведённым поступлением 1С';

    public function handle(): int
    {
        $codes = array_values(array_unique($this->option('code')));
        if ($codes === []) {
            $this->error('Укажите коды строк пакета через --code.');

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($codes): void {
                $batch = OneCPriceBatch::query()->lockForUpdate()->findOrFail($this->argument('batch'));
                $items = $batch->items()->whereIn('code', $codes)->lockForUpdate()->get();
                if ($items->count() !== count($codes) || $items->pluck('code')->unique()->count() !== count($codes)) {
                    throw new DomainException('Не все выбранные коды однозначно найдены в пакете.');
                }

                $lines = DB::table('onec_purchase_lines')->where('doc_number', $this->argument('receipt'))
                    ->whereDate('doc_date', $this->argument('date'))->where('currency_key', OneCPurchaseCosts::BYN)
                    ->lockForUpdate()->get();
                if ($lines->pluck('doc_key')->unique()->count() !== 1) {
                    throw new DomainException('Поступление в BYN не найдено или номер неоднозначен.');
                }
                foreach ($items as $item) {
                    $matching = $lines->filter(fn ($line) => $line->item_key === $item->item_key
                        && ($line->characteristic_key ?: '00000000-0000-0000-0000-000000000000')
                            === ($item->characteristic_key ?: '00000000-0000-0000-0000-000000000000'));
                    if ($matching->count() !== 1) {
                        throw new DomainException('Проверьте строки поступления для '.$item->code);
                    }
                    $line = $matching->first();
                    if ($line->price_net <= 0 || $line->price_gross < $line->price_net) {
                        throw new DomainException('Некорректная себестоимость в поступлении.');
                    }
                    $item->fill([
                        'purchase_net' => round((float) $line->price_net, 2),
                        'purchase' => round((float) $line->price_gross, 2),
                        'note' => 'Закупка по поступлению 1С '.$line->doc_number.' от '.substr($line->doc_date, 0, 10),
                    ]);
                    if ($this->option('apply') && $item->isDirty()) {
                        $item->save();
                    }
                }
            });
        } catch (DomainException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        $this->info('Сверено позиций: '.count($codes));

        return self::SUCCESS;
    }
}
