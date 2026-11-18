<?php

namespace App\Console\Commands;

use App\Services\Marketplaces\DealByClient;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

/**
 * Adds the cards missing on sbg.by through the deal.by API: the import file made by
 * deal:sync-brand-prices --export-missing is handed to the shop import by its public address.
 * Items not in the file stay as they are; items of the file already on the shop only get the price.
 * The «Идентификатор_товара» kotlov-<id> then links each new item to its card in the hourly price sync.
 */
class ImportDealByFileCommand extends Command
{
    protected $signature = 'deal:import-file
        {file : The import xlsx under public/exports, e.g. metabel-dlya-sbg}
        {--apply : Start the import on deal.by}
        {--status= : Only read the state of an import started before}
        {--wait=300 : Seconds to wait for the import to finish}';

    protected $description = 'Add cards missing on sbg.by by the deal.by import of an exported file';

    public function handle(DealByClient $client): int
    {
        try {
            if (filled($this->option('status'))) {
                $this->reportStatus($client->importStatus((string) $this->option('status')));

                return self::SUCCESS;
            }

            $name = Str::slug(pathinfo((string) $this->argument('file'), PATHINFO_FILENAME));
            $path = public_path('exports/'.$name.'.xlsx');
            if ($name === '' || ! is_file($path)) {
                throw new RuntimeException('Файл импорта не найден в public/exports.');
            }

            $cardIds = $this->cardIds($path);
            if ($cardIds === []) {
                throw new RuntimeException('В файле нет строк с идентификатором kotlov-<id>.');
            }
            $onShop = $this->cardsOnShop($client);
            $new = array_values(array_diff($cardIds, $onShop));
            foreach ($cardIds as $id) {
                $this->line('audit.deal_import.row='.$id.':'.(in_array($id, $onShop, true) ? 'on_shop' : 'new'));
            }
            $this->line('metric.deal_import.rows='.count($cardIds));
            $this->line('metric.deal_import.new='.count($new));
            $this->line('metric.deal_import.already_on_shop='.(count($cardIds) - count($new)));

            if (! $this->option('apply')) {
                $this->info('Проверка завершена. Deal.by не изменялся.');

                return self::SUCCESS;
            }
            if ($new === []) {
                $this->info('Все карточки файла уже есть в магазине.');

                return self::SUCCESS;
            }

            $started = $client->importUrl(url('/exports/'.$name.'.xlsx'));
            $importId = (string) ($started['id'] ?? $started['import_id'] ?? '');
            if (filled($started['error'] ?? null) || $importId === '') {
                $this->line('audit.deal_import.rejected='.(Str::slug((string) ($started['error'] ?? $started['message'] ?? 'no-id')) ?: 'no-id'));
                throw new RuntimeException('Deal.by не принял импорт.');
            }
            $this->line('audit.deal_import.id='.Str::slug($importId));

            $deadline = time() + max(0, (int) $this->option('wait'));
            while (! $this->finished($status = $client->importStatus($importId)) && time() < $deadline) {
                sleep(10);
            }
            $this->reportStatus($status);

            $missing = array_values(array_diff($new, $this->cardsOnShop($client)));
            $this->line('metric.deal_import.added='.(count($new) - count($missing)));
            $this->line('metric.deal_import.still_missing='.count($missing));
            foreach ($missing as $id) {
                $this->line('audit.deal_import.missing='.$id);
            }

            return $missing === [] ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Импорт в Deal.by остановлен: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    /** @return list<int> card ids of the «Идентификатор_товара» column */
    private function cardIds(string $path): array
    {
        $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, false, false);
        $column = array_search('Идентификатор_товара', array_map(fn ($cell) => trim((string) $cell), $rows[0] ?? []), true);
        if ($column === false) {
            throw new RuntimeException('В файле нет колонки «Идентификатор_товара».');
        }
        $ids = [];
        foreach (array_slice($rows, 1) as $row) {
            if (preg_match('/^kotlov-(\d+)$/i', trim((string) ($row[$column] ?? '')), $match)) {
                $ids[] = (int) $match[1];
            }
        }

        return array_values(array_unique($ids));
    }

    /** @return list<int> card ids the shop already carries as external_id kotlov-<id> */
    private function cardsOnShop(DealByClient $client): array
    {
        $ids = [];
        $lastId = null;
        for ($page = 0; $page < 500; $page++) {
            $items = collect($client->listProducts(100, $lastId)['products'] ?? [])->filter(fn ($item) => is_array($item))->values();
            foreach ($items as $item) {
                if (preg_match('/^kotlov-(\d+)$/i', trim((string) ($item['external_id'] ?? '')), $match)) {
                    $ids[] = (int) $match[1];
                }
            }
            if ($items->count() < 100) {
                return array_values(array_unique($ids));
            }
            $next = (int) data_get($items->last(), 'id');
            if ($next <= 0 || $next === $lastId) {
                throw new RuntimeException('Deal.by вернул некорректный курсор пагинации товаров.');
            }
            $lastId = $next;
        }

        throw new RuntimeException('Превышен безопасный предел каталога Deal.by.');
    }

    /** @param array<string, mixed> $status */
    private function finished(array $status): bool
    {
        $state = strtolower((string) ($status['status'] ?? ''));

        return $state !== '' && ! in_array($state, ['pending', 'processing', 'in_progress', 'new', 'queued', 'started', 'running'], true);
    }

    /** @param array<string, mixed> $status */
    private function reportStatus(array $status): void
    {
        $this->line('audit.deal_import.status='.(Str::slug((string) ($status['status'] ?? 'none')) ?: 'none'));
        foreach ($status as $key => $value) {
            if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                $this->line('metric.deal_import.'.Str::snake(Str::slug((string) $key, '_')).'='.(int) $value);
            } elseif (is_array($value)) {
                $this->line('metric.deal_import.'.Str::snake(Str::slug((string) $key, '_')).'_count='.count($value));
            }
        }
        foreach (array_slice((array) ($status['errors'] ?? []), 0, 20) as $error) {
            $text = is_array($error) ? (string) ($error['message'] ?? $error['error'] ?? json_encode($error)) : (string) $error;
            $this->line('audit.deal_import.error='.(Str::slug(Str::limit($text, 80, '')) ?: 'unknown'));
        }
    }
}
