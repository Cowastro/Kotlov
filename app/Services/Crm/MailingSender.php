<?php

namespace App\Services\Crm;

use App\Models\CrmMailingCampaign;
use App\Models\CrmMailingMessage;
use App\Models\CrmMailingOptout;
use App\Services\SmsByClient;
use App\Services\Telephony\PhoneNumber;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Sends a campaign through SMS.BY one recipient at a time (sendQuickViberMessage with SMS forwarding, or sendQuickSMS),
 * so each phone has its own provider id and status. Only Belarusian mobile numbers are used; phones that answered
 * «СТОП» are skipped.
 */
class MailingSender
{
    /** Viber statuses are checked for this long after sending; then the last known status stays. */
    public const STATUS_DAYS = 3;

    private ?array $senders = null;

    public function __construct(private readonly SmsByClient $sms, private readonly MailingAudience $audience) {}

    /**
     * Mobile phones of the list members, one row each, without opted-out numbers.
     *
     * @return array<int, array{member_id:int,phone:string}>
     */
    public function recipients(CrmMailingCampaign $campaign): array
    {
        $optouts = CrmMailingOptout::query()->pluck('phone')->flip();
        $rows = [];
        foreach ($campaign->mailingList->members()->with(['counterparty', 'b2bClient.user', 'supplier'])->get() as $member) {
            foreach ($this->audience->channels($member)['phones'] as $phone) {
                $digits = self::mobile($phone);
                if ($digits !== null && ! isset($rows[$digits]) && ! isset($optouts[$digits])) {
                    $rows[$digits] = ['member_id' => (int) $member->id, 'phone' => $digits];
                }
            }
        }

        return array_values($rows);
    }

    /** «+375 29 123-45-67» → 375291234567 for A1/MTS/life (25, 29, 33, 44); null for landlines and foreign numbers. */
    public static function mobile(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        $digits = match (true) {
            strlen($digits) === 11 && str_starts_with($digits, '80') => '375'.substr($digits, 2),
            strlen($digits) === 9 => '375'.$digits,
            default => $digits,
        };

        return preg_match('/^375(25|29|33|44)\d{7}$/D', $digits) ? $digits : null;
    }

    /** Checks the senders, fixes the recipient rows and lets the scheduler send them. */
    public function start(CrmMailingCampaign $campaign): int
    {
        if ($campaign->status !== 'draft') {
            throw new RuntimeException('Рассылка уже запускалась.');
        }
        $this->senderIds($campaign);
        $recipients = $this->recipients($campaign);
        if ($recipients === []) {
            throw new RuntimeException('В списке нет мобильных номеров для отправки.');
        }

        DB::transaction(function () use ($campaign, $recipients): void {
            $now = now();
            foreach (array_chunk($recipients, 500) as $chunk) {
                CrmMailingMessage::query()->insertOrIgnore(array_map(fn (array $r): array => [
                    'campaign_id' => $campaign->id, 'member_id' => $r['member_id'], 'phone' => $r['phone'], 'status' => 'pending',
                    'created_at' => $now, 'updated_at' => $now,
                ], $chunk));
            }
            $campaign->forceFill(['status' => 'sending', 'recipients_count' => count($recipients), 'started_at' => $now])->save();
        });

        return count($recipients);
    }

    /** One message right now, for checking how it looks on a phone. */
    public function sendTest(CrmMailingCampaign $campaign, string $phone): string
    {
        $digits = self::mobile($phone) ?? throw new RuntimeException('Нужен белорусский мобильный номер.');

        return $this->deliver($campaign, $digits)['id'];
    }

    /**
     * Sends up to $limit queued messages of running campaigns.
     *
     * @return array{sent:int,failed:int,skipped:int}
     */
    public function sendQueued(int $limit = 200): array
    {
        $stats = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        $optouts = CrmMailingOptout::query()->pluck('phone')->flip();
        $messages = CrmMailingMessage::query()->where('status', 'pending')
            ->whereHas('campaign', fn ($q) => $q->where('status', 'sending'))
            ->with('campaign')->orderBy('id')->limit($limit)->get();

        foreach ($messages as $message) {
            if (isset($optouts[$message->phone])) {
                $message->forceFill(['status' => 'skipped', 'error' => 'Отписался'])->save();
                $stats['skipped']++;

                continue;
            }
            try {
                $sent = $this->deliver($message->campaign, $message->phone);
                $message->forceFill(['status' => 'sent', 'channel' => $sent['channel'], 'provider_message_id' => $sent['id'], 'sent_at' => now(), 'error' => null])->save();
                $stats['sent']++;
            } catch (Throwable $e) {
                $message->forceFill(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 250), 'sent_at' => now()])->save();
                $stats['failed']++;
            }
            // SMS.BY allows 3 requests per second for v1 methods and 10 for v2.
            usleep($message->campaign->usesViber() ? 120_000 : 350_000);
        }
        $this->finishCampaigns();

        return $stats;
    }

    /**
     * Asks SMS.BY for the status of messages that are not final yet.
     *
     * @return array{checked:int,delivered:int,failed:int}
     */
    public function refreshStatuses(int $limit = 200): array
    {
        $stats = ['checked' => 0, 'delivered' => 0, 'failed' => 0];
        $messages = CrmMailingMessage::query()->where('status', 'sent')->whereNotNull('provider_message_id')
            ->where('sent_at', '>=', now()->subDays(self::STATUS_DAYS))
            ->where(fn ($q) => $q->whereNull('status_checked_at')->orWhere('status_checked_at', '<=', now()->subMinutes(10)))
            ->orderBy('status_checked_at')->orderBy('id')->limit($limit)->get();

        foreach ($messages as $message) {
            try {
                $status = $message->channel === 'sms' ? $this->sms->smsStatus($message->provider_message_id) : $this->sms->viberStatus($message->provider_message_id);
            } catch (Throwable) {
                $message->forceFill(['status_checked_at' => now()])->save();

                continue;
            }
            $this->applyStatus($message, $status['raw']);
            $stats['checked']++;
            $stats['delivered'] += (int) in_array($message->status, ['delivered', 'read'], true);
            $stats['failed'] += (int) ($message->status === 'failed');
            usleep(120_000);
        }

        return $stats;
    }

    /** A status from SMS.BY (polling or webhook); «прочитано» is never downgraded to «доставлено». */
    public function applyStatus(CrmMailingMessage $message, string $raw): void
    {
        $status = SmsByClient::mapStatus($raw);
        $rank = ['pending' => 0, 'sent' => 1, 'failed' => 2, 'delivered' => 3, 'read' => 4];
        $message->forceFill([
            'provider_status' => mb_substr($raw, 0, 40) ?: null,
            'status' => ($rank[$status] ?? 1) >= ($rank[$message->status] ?? 0) ? $status : $message->status,
            'status_checked_at' => now(),
        ])->save();
    }

    /** «СТОП» / «STOP» / «отписаться» in a reply. */
    public static function isStopWord(?string $text): bool
    {
        return (bool) preg_match('/^\W*(стоп|stop|отписаться|отпишите|unsubscribe)\b/iu', trim((string) $text));
    }

    public function optOut(string $phone, string $source = 'manual', ?string $note = null): bool
    {
        $digits = (string) PhoneNumber::normalize($phone);
        if (strlen($digits) < 9) {
            return false;
        }
        $optout = CrmMailingOptout::query()->firstOrCreate(['phone' => $digits], ['source' => $source, 'note' => $note]);
        CrmMailingMessage::query()->where('phone', $digits)->where('status', 'pending')->update(['status' => 'skipped', 'error' => 'Отписался']);

        return $optout->wasRecentlyCreated;
    }

    /** @return array{channel:string,id:string} */
    private function deliver(CrmMailingCampaign $campaign, string $phone): array
    {
        $ids = $this->senderIds($campaign);
        if ($campaign->usesViber()) {
            return ['channel' => 'viber', 'id' => $this->sms->sendViber($phone, $ids['viber'], (string) $campaign->text,
                $campaign->button_caption, $campaign->button_link, $campaign->channel === 'viber_sms' ? $ids['sms'] : null)];
        }

        return ['channel' => 'sms', 'id' => $this->sms->sendSms($phone, $ids['sms'], $campaign->smsBody())];
    }

    /** @return array{sms: ?string, viber: ?string} */
    private function senderIds(CrmMailingCampaign $campaign): array
    {
        $ids = $this->senders ??= $this->sms->mailingSenders();
        if ($campaign->usesViber() && $ids['viber'] === null) {
            throw new RuntimeException('Viber-имя KOTLOV.BY ещё не активно в SMS.BY. Пока можно отправлять только SMS.');
        }
        if ($campaign->channel !== 'viber' && $ids['sms'] === null) {
            throw new RuntimeException('SMS-отправитель KOTLOV.BY не найден в SMS.BY.');
        }

        return $ids;
    }

    private function finishCampaigns(): void
    {
        CrmMailingCampaign::query()->where('status', 'sending')
            ->whereDoesntHave('messages', fn ($q) => $q->where('status', 'pending'))
            ->update(['status' => 'done', 'finished_at' => now()]);
    }
}
