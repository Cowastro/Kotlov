<?php

namespace App\Console\Commands;

use App\Services\EcokaminWarehouseStockImporter;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use ZipArchive;

class ImportEcokaminWarehouseStockCommand extends Command
{
    protected $signature = 'supplier:import-ecokamin-moscow-stock
        {file : XLSX/XLS-файл остатков ЭкоКамин или подготовленный JSON (database/data/ecokamin_moscow_stock_*.json)}
        {--show=* : Артикулы, для которых показать, к какой карточке привяжется строка}
        {--link=* : АРТИКУЛ=id или slug карточки: привязать артикул остатков к карточке (без --apply — только показать результат)}
        {--apply : Записать сопоставленные остатки в базу}
        {--date= : Дата остатков YYYY-MM-DD; иначе берётся из имени файла или текущая дата}';

    protected $description = 'Импорт доступного остатка ЭкоКамин со склада Москва (срок поставки 7 дней).';

    public function handle(EcokaminWarehouseStockImporter $importer): int
    {
        $path = $this->resolvePath((string) $this->argument('file'));
        if (! is_file($path)) {
            $this->error('Файл не найден: '.$path);

            return self::FAILURE;
        }

        $payload = str_ends_with(mb_strtolower($path), '.json')
            ? json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)
            : $this->readWorkbook($path);
        if ($payload['items'] === []) {
            $this->error('В файле не найдены товарные строки раздела «ПРОДУКЦИЯ ЭКОКАМИН».');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Найдено строк ЭкоКамин: %d; доступно на складе: %s шт.',
            count($payload['items']),
            number_format((float) collect($payload['items'])->sum('available'), 0, ',', ' '),
        ));

        $links = $this->links();
        if ($links === null) {
            return self::FAILURE;
        }
        $names = collect($payload['items'])->mapWithKeys(fn (array $item) => [(string) $item['article'] => (string) ($item['name'] ?? '')]);
        $applyLinks = function () use ($links, $importer, $names): void {
            foreach ($links as $article => $product) {
                $done = $importer->link($article, $product, $names->get($article, ''));
                $this->line('audit.ecokamin_stock.link='.preg_replace('/[^A-Za-z0-9.\/_-]/', '', $article).':'.$product->id.':'.$done);
            }
        };

        if (! $this->option('apply')) {
            // The links are tried in a transaction that is rolled back: the preview shows the result, nothing is kept.
            \Illuminate\Support\Facades\DB::beginTransaction();
            try {
                $applyLinks();
                $this->printPreview($importer->preview($payload));
            } finally {
                \Illuminate\Support\Facades\DB::rollBack();
            }
            $this->warn('Предпросмотр: база не изменена. Для записи добавьте --apply.');

            return self::SUCCESS;
        }

        \Illuminate\Support\Facades\DB::transaction($applyLinks);
        $result = $importer->importPayload($payload);
        foreach (['matched', 'unmatched', 'zeroed'] as $key) {
            $this->line("metric.ecokamin_stock_apply.{$key}=".$result[$key]);
        }
        $this->table(['Результат', 'Количество'], [
            ['Привязано карточек', $result['matched']],
            ['Не найдено в каталоге', $result['unmatched']],
            ['Обнулено отсутствующих в новом файле', $result['zeroed']],
        ]);

        if ($result['unmatched'] > 0) {
            $this->warn('Не найденные позиции пропущены — товары и привязки автоматически не создавались.');
            $this->table(
                ['Артикул', 'Наименование'],
                collect($result['unmatched_items'])->take(30)->map(
                    fn (array $item): array => [$item['article'], $item['name']],
                )->all(),
            );
        }

        return self::SUCCESS;
    }

    private function readWorkbook(string $path): array
    {
        if (mb_strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'xlsx') {
            return $this->payloadFromRows($this->readXlsxRows($path), $path);
        }

        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = [];
        for ($row = 1; $row <= $sheet->getHighestDataRow(); $row++) {
            foreach (['A', 'C', 'G', 'H', 'I', 'J', 'K'] as $column) {
                $rows[$row][$column] = $sheet->getCell($column.$row)->getCalculatedValue();
            }
        }

        return $this->payloadFromRows($rows, $path);
    }

    private function payloadFromRows(array $rows, string $path): array
    {
        $items = [];
        $insideEcokamin = false;

        foreach ($rows as $row) {
            $article = trim((string) ($row['A'] ?? ''));
            $name = trim((string) ($row['C'] ?? ''));
            $unit = trim((string) ($row['G'] ?? ''));

            if (mb_stripos($article.' '.$name, 'ПРОДУКЦИЯ ЭКОКАМИН') !== false) {
                $insideEcokamin = true;
                continue;
            }
            if (! $insideEcokamin || $article === '' || $name === '' || $unit === '') {
                continue;
            }

            $items[] = [
                'article' => $article,
                'name' => $name,
                'unit' => $unit,
                'total' => $this->quantity($row['H'] ?? 0),
                'reserved' => $this->quantity($row['I'] ?? 0),
                'shipping' => $this->quantity($row['J'] ?? 0),
                'available' => $this->quantity($row['K'] ?? 0),
            ];
        }

        return [
            'as_of_date' => $this->sourceDate(basename($path)),
            'warehouse_code' => 'ecokamin-moscow',
            'warehouse_name' => 'Склад Москва',
            'delivery_days' => 7,
            'source_file' => basename($path),
            'items' => $items,
        ];
    }

    /** Lightweight XLSX reader for inventory exports; avoids platform-specific is_readable() issues. */
    private function readXlsxRows(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Не удалось открыть XLSX: '.$path);
        }

        try {
            $shared = [];
            if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                $document = simplexml_load_string($xml);
                foreach ($document?->si ?? [] as $item) {
                    $parts = [];
                    foreach ($item->xpath('.//*[local-name()="t"]') ?: [] as $text) {
                        $parts[] = (string) $text;
                    }
                    $shared[] = implode('', $parts);
                }
            }

            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheetXml === false) {
                throw new \RuntimeException('В XLSX не найден первый лист.');
            }
            $sheet = simplexml_load_string($sheetXml);
            $cells = $sheet?->xpath('//*[local-name()="c"]') ?: [];
            $rows = [];

            foreach ($cells as $cell) {
                $reference = (string) $cell['r'];
                if (! preg_match('/^([A-Z]+)(\d+)$/', $reference, $match)) {
                    continue;
                }
                [$all, $column, $rowNumber] = $match;
                if (! in_array($column, ['A', 'C', 'G', 'H', 'I', 'J', 'K'], true)) {
                    continue;
                }

                $type = (string) $cell['t'];
                $children = $cell->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $value = isset($children->v) ? (string) $children->v : '';
                if ($type === 's') {
                    $value = $shared[(int) $value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $textNodes = $children->is?->xpath('.//*[local-name()="t"]') ?: [];
                    $value = implode('', array_map(fn ($text): string => (string) $text, $textNodes));
                }
                $rows[(int) $rowNumber][$column] = $value;
            }

            ksort($rows);

            return $rows;
        } finally {
            $zip->close();
        }
    }

    private function quantity(mixed $value): float
    {
        return max(0, (float) str_replace(',', '.', trim((string) $value)));
    }

    /** @return array<string, \App\Models\Product>|null the cards of --link, null when one is not found */
    private function links(): ?array
    {
        $links = [];
        foreach ((array) $this->option('link') as $pair) {
            [$article, $reference] = array_map('trim', explode('=', (string) $pair, 2) + [1 => '']);
            $product = $reference === '' ? null : \App\Models\Product::query()->where(ctype_digit($reference) ? 'id' : 'slug', $reference)->first();
            if ($article === '' || ! $product) {
                $this->error('Не найдена карточка для привязки: '.$pair);

                return null;
            }
            $links[$article] = $product;
        }

        return $links;
    }

    /** Safe lines for the server log: rows without a card, rows that reach several, and the articles asked with --show. */
    private function printPreview(array $rows): void
    {
        $show = collect((array) $this->option('show'))->flatMap(fn ($value) => explode(',', (string) $value))->map(fn ($value) => trim($value))->filter()->all();
        $article = fn (string $value): string => preg_replace('/[^A-Za-z0-9.\/_-]/', '', strtr(mb_strtoupper($value), ['А' => 'A', 'В' => 'B', 'Е' => 'E', 'К' => 'K', 'М' => 'M', 'Н' => 'H', 'О' => 'O', 'Р' => 'P', 'С' => 'C', 'Т' => 'T', 'Х' => 'X'])) ?: '-';
        $this->line('metric.ecokamin_stock.rows='.count($rows));
        $this->line('metric.ecokamin_stock.by_article='.collect($rows)->where('by', 'article')->count());
        $this->line('metric.ecokamin_stock.by_name='.collect($rows)->where('by', 'name')->count());
        $this->line('metric.ecokamin_stock.unmatched='.collect($rows)->whereNull('product_id')->count());
        foreach ($rows as $row) {
            $available = rtrim(rtrim(number_format($row['available'], 3, '.', ''), '0'), '.');
            if ($row['product_id'] === null) {
                $kind = count($row['candidates']) > 1 ? 'ambiguous' : 'unmatched';
                $this->line("audit.ecokamin_stock.{$kind}=".$article($row['article']).':'.$available.($row['candidates'] ? ':'.implode(',', $row['candidates']) : ''));
            } elseif (in_array($row['article'], $show, true)) {
                $this->line('audit.ecokamin_stock.row='.$article($row['article']).':'.$available.':'.$row['product_id'].':'.$row['by']);
            }
        }
    }

    private function sourceDate(string $filename): string
    {
        if ($date = $this->option('date')) {
            return (string) $date;
        }
        if (preg_match('/(?<!\d)(\d{1,2})[.\-_](\d{1,2})(?:[.\-_](\d{2,4}))?/u', $filename, $match)) {
            $year = isset($match[3]) ? (int) $match[3] : (int) now()->format('Y');
            $year = $year < 100 ? 2000 + $year : $year;

            return sprintf('%04d-%02d-%02d', $year, (int) $match[2], (int) $match[1]);
        }

        return now()->toDateString();
    }

    private function resolvePath(string $path): string
    {
        if (preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $path)) {
            return $path;
        }

        return base_path($path);
    }
}
