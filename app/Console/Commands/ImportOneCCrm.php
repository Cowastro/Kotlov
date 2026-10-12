<?php

namespace App\Console\Commands;

use App\Services\Crm\Imports\OneCCrmImporter;
use Illuminate\Console\Command;

class ImportOneCCrm extends Command
{
    protected $signature = 'crm:import-1c
        {type : trade_contacts или accounting_counterparties}
        {file : Путь к MXL}
        {--commit : Сохранить изменения; без флага выполняется пробный запуск}
        {--user= : ID пользователя, запустившего импорт}';

    protected $description = 'Импортировать контрагентов, телефоны и email из MXL 1С в CRM';

    public function handle(OneCCrmImporter $importer): int
    {
        $summary = $importer->import(
            (string) $this->argument('type'),
            (string) $this->argument('file'),
            ! $this->option('commit'),
            $this->option('user') ? (int) $this->option('user') : null,
        );

        $this->table(['Показатель', 'Количество'], [
            ['Строк', $summary['rows']],
            ['Новых карточек', $summary['created']],
            ['Совпадений', $summary['matched']],
            ['Связей с 1С', $summary['identities']],
            ['Телефонов и email', $summary['contact_points']],
            ['Конфликтов', $summary['conflicts']],
            ['Распознано звонков', $summary['calls_matched']],
        ]);

        $this->line($this->option('commit') ? 'Изменения сохранены.' : 'Пробный запуск: изменения не сохранены.');

        return self::SUCCESS;
    }
}
