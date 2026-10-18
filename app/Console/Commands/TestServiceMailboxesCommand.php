<?php

namespace App\Console\Commands;

use App\Services\ServiceMailboxPreviewSender;
use Illuminate\Console\Command;
use Throwable;

class TestServiceMailboxesCommand extends Command
{
    protected $signature = 'mail:test-service-mailboxes {--to= : Email получателя тестовых писем}';

    protected $description = 'Отправить системные предпросмотры из служебных ящиков order и no-reply';

    public function handle(ServiceMailboxPreviewSender $sender): int
    {
        $recipient = trim((string) $this->option('to'));

        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Укажите корректный email через --to=.');

            return self::FAILURE;
        }

        try {
            $sent = $sender->sendAll($recipient);
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($sent as $mailbox) {
            $this->info("Отправлено: {$mailbox}");
        }
        $this->info('Получатель: '.$recipient);

        return self::SUCCESS;
    }
}
