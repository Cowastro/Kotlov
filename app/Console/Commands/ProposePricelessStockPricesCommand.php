<?php

namespace App\Console\Commands;

use App\Models\IntegrationSource;
use App\Services\Integrations\PricelessStockPriceProposal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * The 1C «Установка цен номенклатуры» file for goods in stock without a price: the load sheet in the layout 1C
 * exports (price types with their columns, then the goods by their 1C ids) holds only the rows whose retail
 * passed the check; the analysis sheet explains every row. Saved for managers under storage, never public.
 */
class ProposePricelessStockPricesCommand extends Command
{
    protected $signature = 'integration:propose-priceless-prices
        {--source=onec : Integration source code}
        {--file= : File name without extension (default: ceny-dlia-1c-<date>)}';

    protected $description = 'Propose wholesale and retail prices for 1C goods in stock without a price and build the 1C price file';

    /** The price types of 1C as its export names them, with their ids and columns. */
    private const PRICE_TYPES = [
        ['7b0fbdc2-1ffa-11e9-bad6-1c1b0d1de7c2', 'Розничный тип цен', 'retail', false],
        ['de262f38-66a1-11e9-9b7c-1c1b0d1de7c2', 'Оптовая цена', 'wholesale', false],
        ['a56e299c-d38a-11e9-a218-1c1b0d1de7c2', 'Оптовая с НДС', 'wholesale', true],
        ['a56e299d-d38a-11e9-a218-1c1b0d1de7c2', 'Розничная с НДС', 'retail', true],
    ];

    private const LOADED = ['ok', 'computed', 'raised'];

    private const VERDICTS = [
        'ok' => 'Розница сайта адекватна',
        'computed' => 'Розница рассчитана от закупки',
        'raised' => 'Розница поднята (низкая наценка)',
        'check' => 'Проверить вручную',
        'need_cost' => 'Нужна себестоимость',
    ];

    public function handle(PricelessStockPriceProposal $proposal): int
    {
        $source = IntegrationSource::query()->where('code', $this->option('source'))->first();
        if (! $source) {
            $this->line('metric.price_proposal.source=0');

            return self::FAILURE;
        }

        $rows = $proposal->propose($source);
        $book = new Spreadsheet;
        $this->typesSheet($book->getActiveSheet()->setTitle('Лист1'));
        $loaded = $this->loadSheet($book->createSheet()->setTitle('Лист_1'), $rows->filter(fn (array $r): bool => in_array($r['verdict'], self::LOADED, true)
            && $this->guid($r['row']->external_id) !== null)->values());
        $this->analysisSheet($book->createSheet()->setTitle('Анализ'), $rows);

        $name = Str::slug((string) ($this->option('file') ?: 'ceny-dlia-1c-'.now()->format('Y-m-d'))) ?: 'ceny-dlia-1c';
        $path = 'price-proposals/'.$name.'.xlsx';
        $tmp = tempnam(sys_get_temp_dir(), 'price');
        (new Xlsx($book))->save($tmp);
        Storage::disk('local')->put($path, file_get_contents($tmp));
        @unlink($tmp);

        foreach (array_keys(self::VERDICTS) as $verdict) {
            $this->line('metric.price_proposal.'.$verdict.'='.$rows->where('verdict', $verdict)->count());
        }
        $this->line('metric.price_proposal.rows='.$rows->count());
        $this->line('metric.price_proposal.loaded='.$loaded);
        $this->line('audit.price_proposal.file='.$name.'.xlsx');

        return self::SUCCESS;
    }

    private function typesSheet($sheet): void
    {
        $sheet->fromArray([['Уникальный идентификатор', 'Вид цены', 'Номер колонки "Старая цена"', 'Номер колонки "Процент изменения"',
            'Номер колонки "Цена"', 'Номер колонки "Единица измерения"', 'Номер колонки "Уникальный идентификатор"']]);
        foreach (self::PRICE_TYPES as $i => [$id, $name]) {
            $first = 6 + $i * 6;
            $sheet->fromArray([[$id, $name, $first, $first + 2, $first + 3, $first + 4, $first + 5]], null, 'A'.($i + 2));
        }
    }

    private function loadSheet($sheet, $rows): int
    {
        $header = ['№', 'Артикул', 'Уникальный идентификатор (Номенклатура)', 'Уникальный идентификатор (Характеристика)', 'Товар'];
        $sub = ['', '', '', '', ''];
        foreach (self::PRICE_TYPES as [, $name]) {
            array_push($header, $name, '', '', '', '', '');
            array_push($sub, 'Старая цена', 'Изменение', '%', 'Цена', 'Ед. изм.', 'Уникальный идентификатор (Единица измерения)');
        }
        $sheet->fromArray([$header, $sub]);
        $zero = '00000000-0000-0000-0000-000000000000';
        $n = 0;
        foreach ($rows as $r) {
            [$item, $characteristic] = $this->guid($r['row']->external_id);
            $line = [++$n, (string) $r['row']->external_sku, $item, $characteristic ?? $zero, (string) $r['row']->name];
            foreach (self::PRICE_TYPES as [, , $kind, $withVat]) {
                $price = (float) $r[$kind];
                $price = round($withVat ? $price : $price / PricelessStockPriceProposal::VAT, 2);
                array_push($line, 0, $price, '', $price, 'шт', $zero);
            }
            $sheet->fromArray([$line], null, 'A'.($n + 2));
        }

        return $n;
    }

    private function analysisSheet($sheet, $rows): void
    {
        $sheet->fromArray([['Код 1С', 'Артикул', 'Товар', 'Остаток', 'Карточка сайта', 'Розница на сайте', 'Закупка (мин.)', 'Рынок, медиана',
            'Наценка розницы, %', 'Розница с НДС (предлагается)', 'Опт с НДС (предлагается)', 'Решение', 'Пояснение', 'В файле загрузки']]);
        $i = 2;
        foreach ($rows->sortBy(fn (array $r): string => array_search($r['verdict'], array_keys(self::VERDICTS), true).'|'.$r['row']->name) as $r) {
            $margin = $r['retail'] && $r['purchase'] ? round(($r['retail'] / $r['purchase'] - 1) * 100) : null;
            $sheet->fromArray([[(string) $r['row']->external_code, (string) $r['row']->external_sku, (string) $r['row']->name,
                (float) $r['row']->stock_quantity, $r['card'] ? 'https://kotlov.by/admin/products/'.$r['card']->id.'/edit' : 'нет',
                $r['retail_site'], $r['purchase'], $r['market'], $margin, $r['retail'], $r['wholesale'],
                self::VERDICTS[$r['verdict']], $r['note'],
                in_array($r['verdict'], self::LOADED, true) && $this->guid($r['row']->external_id) ? 'да' : 'нет']], null, 'A'.$i++);
        }
    }

    /** @return array{0: string, 1: ?string}|null the 1C item id and the characteristic id of «item#characteristic» */
    private function guid(?string $externalId): ?array
    {
        [$item, $characteristic] = array_pad(explode('#', (string) $externalId, 2), 2, null);
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        if (! preg_match($pattern, (string) $item)) {
            return null;
        }

        return [$item, $characteristic && preg_match($pattern, $characteristic) ? $characteristic : null];
    }
}
