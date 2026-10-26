<?php

namespace App\Console\Commands;

use App\Services\Marketplaces\DealByCatalogAudit;
use Illuminate\Console\Command;
use Throwable;

class AuditDealByCatalogCommand extends Command
{
    protected $signature = 'deal:audit-catalog';

    protected $description = 'Read and compare the Deal.by catalog without changing remote data';

    public function handle(DealByCatalogAudit $audit): int
    {
        $this->info('Загружаем каталог Deal.by только на чтение...');

        try {
            $result = $audit->run();
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Аудит Deal.by не выполнен: '.$exception->getMessage());

            return self::FAILURE;
        }

        $summary = $result['summary'];
        $this->table(['Показатель', 'Количество'], [
            ['Товаров в Deal.by', $summary['remote_products']],
            ['Товаров в локальной базе', $summary['local_products']],
            ['С внешним ID', $summary['remote_with_external_id']],
            ['Связано по external_id', $summary['matched_by_external_id']],
            ['Однозначно найдено по slug', $summary['matched_by_slug']],
            ['Однозначно найдено по SKU', $summary['matched_by_sku']],
            ['Однозначно найдено по названию', $summary['matched_by_name']],
            ['Неоднозначный SKU в KOTLOV.BY', $summary['ambiguous_local_sku']],
            ['Не найдено', $summary['unmatched']],
            ['Повторяющийся SKU в Deal.by', $summary['duplicate_remote_sku']],
            ['Цена отличается', $summary['price_mismatch']],
        ]);
        foreach (['brands' => 'brand', 'categories' => 'category'] as $key => $type) {
            foreach (array_slice($result['groups'][$key], 0, 40) as $group) {
                $name = preg_replace('/[|\r\n]+/u', ' ', (string) $group['name']) ?: 'Без группы';
                $this->line(sprintf(
                    'group.deal_linkability=%s|%s|%d|%d|%d|%d|%d',
                    $type,
                    $name,
                    $group['linked'],
                    $group['auto'],
                    $group['conflicts'],
                    $group['price_mismatch'],
                    $group['total'],
                ));
            }
        }
        $this->line('CSV: '.$result['report_path']);
        $this->info('Аудит завершён. Deal.by не изменялся.');

        return self::SUCCESS;
    }
}
