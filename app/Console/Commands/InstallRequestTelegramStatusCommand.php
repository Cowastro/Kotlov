<?php

namespace App\Console\Commands;

use App\Models\InstallRequest;
use Illuminate\Console\Command;

class InstallRequestTelegramStatusCommand extends Command
{
    protected $signature = 'install-requests:telegram-status {--email= : Exact test email to audit}';

    protected $description = 'Show Telegram delivery status for test installation requests without personal data';

    public function handle(): int
    {
        $email = trim((string) $this->option('email'));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('A valid --email is required.');

            return self::FAILURE;
        }

        $requests = InstallRequest::query()
            ->where('customer_email', $email)
            ->latest('id')
            ->limit(20)
            ->get(['id', 'source', 'telegram_message_id', 'telegram_notified_at']);

        $this->table(
            ['ID', 'Source', 'Telegram', 'Message ID', 'Notified at'],
            $requests->map(fn (InstallRequest $request) => [
                $request->id,
                $request->source,
                $request->telegram_notified_at ? 'sent' : 'not_sent',
                $request->telegram_message_id ?? '—',
                $request->telegram_notified_at?->format('Y-m-d H:i:s') ?? '—',
            ])->all(),
        );

        return $requests->isNotEmpty() && $requests->every(
            fn (InstallRequest $request) => $request->telegram_notified_at !== null
        ) ? self::SUCCESS : self::FAILURE;
    }
}
