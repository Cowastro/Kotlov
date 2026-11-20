<?php

namespace App\Services\InstallerLeads;

use App\Models\InstallRequest;
use App\Models\InstallRequestLead;
use App\Services\TelegramApi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** An installer takes an offered request: the first three get the client's contacts. */
class InstallerLeadTaker
{
    public const TAKEN = 'taken';

    public const ALREADY_YOURS = 'already_yours';

    public const FULL = 'full';

    public const CLOSED = 'closed';

    public function __construct(private readonly TelegramApi $telegram) {}

    public function state(InstallRequestLead $lead): string
    {
        $request = $lead->installRequest;

        return match (true) {
            $lead->taken_at !== null => self::ALREADY_YOURS,
            in_array($request->status, ['done', 'cancelled'], true) => self::CLOSED,
            $this->takenCount($request) >= $this->maxTakers() => self::FULL,
            default => 'open',
        };
    }

    public function take(InstallRequestLead $lead): string
    {
        $result = DB::transaction(function () use ($lead): string {
            $request = InstallRequest::query()->lockForUpdate()->findOrFail($lead->install_request_id);
            $lead = InstallRequestLead::query()->lockForUpdate()->findOrFail($lead->id);

            if ($lead->taken_at) {
                return self::ALREADY_YOURS;
            }
            if (in_array($request->status, ['done', 'cancelled'], true)) {
                return self::CLOSED;
            }
            if ($this->takenCount($request) >= $this->maxTakers()) {
                return self::FULL;
            }

            $lead->forceFill(['taken_at' => now()])->save();

            return self::TAKEN;
        });

        if ($result === self::TAKEN) {
            $this->tellManagers($lead->fresh(['installRequest', 'installerProfile']));
        }

        return $result;
    }

    public function takenCount(InstallRequest $request): int
    {
        return InstallRequestLead::query()->where('install_request_id', $request->id)->whereNotNull('taken_at')->count();
    }

    public function maxTakers(): int
    {
        return max(1, (int) config('installer_leads.max_takers', 3));
    }

    private function tellManagers(InstallRequestLead $lead): void
    {
        $chatId = config('services.telegram.orders_chat_id');
        if (! config('services.telegram.bot_token') || ! $chatId) {
            return;
        }

        $profile = $lead->installerProfile;
        $request = $lead->installRequest;
        $escape = fn (?string $value): string => str_replace(['_', '*', '`', '['], ['\_', '\*', '\`', '\['], (string) $value);
        $text = implode("\n", array_filter([
            'Заявку на монтаж №'.$request->id.' взял монтажник ('.$this->takenCount($request).' из '.$this->maxTakers().')',
            $escape($profile->display_name),
            $profile->phone ? 'Тел.: '.$escape($profile->phone) : null,
            url('/admin/install-requests/'.$request->id),
        ]));

        try {
            $this->telegram->sendMessage($chatId, $text, ['disable_web_page_preview' => true]);
        } catch (\Throwable $e) {
            Log::warning('Installer lead Telegram notice failed.', ['lead_id' => $lead->id, 'error' => $e->getMessage()]);
        }
    }
}
