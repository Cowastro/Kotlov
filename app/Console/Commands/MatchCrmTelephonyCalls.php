<?php

namespace App\Console\Commands;

use App\Models\TelephonyCall;
use App\Services\Crm\CrmContactMatcher;
use Illuminate\Console\Command;

class MatchCrmTelephonyCalls extends Command
{
    protected $signature = 'crm:match-telephony-calls {--all : Повторно проверить уже связанные звонки}';

    protected $description = 'Сопоставить звонки с контактами CRM по нормализованному номеру телефона';

    public function handle(CrmContactMatcher $matcher): int
    {
        $matched = 0;
        $query = TelephonyCall::query()->whereNotNull('customer_phone');

        if (! $this->option('all')) {
            $query->whereNull('crm_contact_id');
        }

        $query->chunkById(500, function ($calls) use ($matcher, &$matched): void {
            foreach ($calls as $call) {
                if ($matcher->attachToCall($call)) {
                    $call->save();
                    $matched++;
                }
            }
        });

        $this->info("Сопоставлено звонков: {$matched}");

        return self::SUCCESS;
    }
}
