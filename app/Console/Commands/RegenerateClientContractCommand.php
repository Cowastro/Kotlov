<?php

namespace App\Console\Commands;

use App\Models\CommercialContract;
use App\Services\CommercialContracts\ClientContractTemplateGenerator;
use Illuminate\Console\Command;

class RegenerateClientContractCommand extends Command
{
    protected $signature = 'contracts:regenerate-client {contract : ID договора} {--apply : Перезаписать рабочий DOCX}';

    protected $description = 'Повторно собирает договор по сохранённой форме клиента';

    public function handle(ClientContractTemplateGenerator $generator): int
    {
        $contract = CommercialContract::query()->findOrFail((int) $this->argument('contract'));
        if (! $this->option('apply')) {
            $this->info(sprintf(
                'Договор %s готов к повторной сборке. Добавьте --apply для перезаписи DOCX.',
                $contract->number,
            ));

            return self::SUCCESS;
        }

        $contract = $generator->regenerate($contract);
        $this->info(sprintf('Договор %s пересобран: %s', $contract->number, $contract->generated_docx_path));

        return self::SUCCESS;
    }
}
