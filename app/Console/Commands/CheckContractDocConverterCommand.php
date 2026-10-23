<?php

namespace App\Console\Commands;

use App\Services\CommercialContracts\LegacyWordConverter;
use Illuminate\Console\Command;

class CheckContractDocConverterCommand extends Command
{
    protected $signature = 'contracts:check-doc-converter';

    protected $description = 'Check whether legacy Word DOC files can be converted to DOCX';

    public function handle(LegacyWordConverter $converter): int
    {
        if (! $converter->available()) {
            $this->error('Legacy DOC converter is unavailable. Configure LIBREOFFICE_BIN.');

            return self::FAILURE;
        }

        $this->info('Legacy DOC converter is available.');

        return self::SUCCESS;
    }
}
