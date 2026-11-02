<?php

namespace App\Support;

use App\Models\CrmMailbox;
use Illuminate\Mail\MailManager;

/**
 * Every automatic letter (account, orders, B2B, invitations) leaves through
 * the service mailbox no-reply@kotlov.by kept in CRM mailboxes. The
 * "transactional" mailer and its sender are taken from that mailbox whenever
 * the mail manager is built, including inside the queue worker.
 */
final class TransactionalMailbox
{
    public const EMAIL = 'no-reply@kotlov.by';

    public static function configure(?MailManager $manager = null): bool
    {
        try {
            $mailbox = CrmMailbox::query()->where('email', self::EMAIL)->first();
        } catch (\Throwable) {
            // No database yet (install, package discovery): keep the .env mailer.
            return false;
        }
        if (! $mailbox || blank($mailbox->password)) {
            return false;
        }

        config()->set('mail.mailers.transactional', [
            'transport' => 'smtp',
            'scheme' => $mailbox->smtp_encryption === 'ssl' ? 'smtps' : null,
            'host' => $mailbox->smtp_host,
            'port' => $mailbox->smtp_port,
            'username' => $mailbox->username,
            'password' => $mailbox->password,
            'timeout' => null,
            'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST),
        ]);
        config()->set('mail.transactional_from', [
            'address' => $mailbox->email,
            'name' => $mailbox->name,
        ]);
        ($manager ?? app('mail.manager'))->purge('transactional');

        return true;
    }
}
