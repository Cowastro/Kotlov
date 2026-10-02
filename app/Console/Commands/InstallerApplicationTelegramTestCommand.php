<?php

namespace App\Console\Commands;

use App\Models\InstallerApplication;
use App\Services\InstallerApplicationTelegramNotifier;
use Illuminate\Console\Command;

class InstallerApplicationTelegramTestCommand extends Command
{
    protected $signature = 'installer-applications:telegram-test';

    protected $description = 'Send a test installer application notification to Telegram without writing to the database';

    public function handle(InstallerApplicationTelegramNotifier $notifier): int
    {
        $application = new InstallerApplication([
            'contact_name' => '[ТЕСТ] Проверка уведомлений KOTLOV',
            'phone' => '+375 XX XXX-XX-XX',
            'city' => 'Тестовый город',
            'experience_years' => 10,
            'specializations' => ['kotly', 'dymohody'],
            'message' => 'Это служебная проверка. Заявка в базе не создавалась.',
            'source' => 'outreach-messenger',
        ]);
        $application->created_at = now();

        if (! $notifier->send($application)) {
            $this->error('Telegram test notification was not sent.');

            return self::FAILURE;
        }

        $this->info('Telegram test notification sent successfully.');

        return self::SUCCESS;
    }
}
