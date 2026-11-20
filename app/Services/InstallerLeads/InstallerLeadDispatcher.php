<?php

namespace App\Services\InstallerLeads;

use App\Mail\InstallerLeadMail;
use App\Models\InstallRequest;
use App\Models\InstallRequestLead;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/** Offers new requests for installation to the matching installers by e-mail, once per request. */
class InstallerLeadDispatcher
{
    public function __construct(private readonly InstallerLeadMatcher $matcher) {}

    /** Requests waiting to be offered. */
    public function pending(): Collection
    {
        return InstallRequest::query()
            ->whereNull('leads_sent_at')
            ->whereNull('installer_profile_id')
            ->where('status', 'new')
            ->whereIn('source', (array) config('installer_leads.sources', []))
            ->where('created_at', '>=', now()->subHours((int) config('installer_leads.fresh_hours', 72)))
            ->orderBy('id')
            ->get();
    }

    /**
     * $again offers a request by hand: one already marked as sent (e.g. made before the mailing) or of another source.
     *
     * @return array{matched:int, sent:int, failed:int}
     */
    public function dispatch(InstallRequest $request, bool $again = false): array
    {
        $claimed = InstallRequest::query()->whereKey($request->id)
            ->when(! $again, fn ($query) => $query->whereNull('leads_sent_at'))
            ->update(['leads_sent_at' => now()]);
        if ($claimed === 0) {
            return ['matched' => 0, 'sent' => 0, 'failed' => 0];
        }

        $profiles = $this->matcher->candidates($request);
        $sent = 0;
        $failed = 0;

        foreach ($profiles as $profile) {
            $lead = InstallRequestLead::query()->firstOrCreate([
                'install_request_id' => $request->id,
                'installer_profile_id' => $profile->id,
            ]);
            if ($lead->notified_at) {
                continue;
            }

            try {
                Mail::mailer('transactional')->to($this->matcher->email($profile))
                    ->send(new InstallerLeadMail($lead->setRelations(['installRequest' => $request, 'installerProfile' => $profile]), $this->url($lead)));
                $lead->forceFill(['notified_at' => now()])->save();
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('Installer lead e-mail failed.', ['lead_id' => $lead->id, 'error' => $e->getMessage()]);
            }
        }

        return ['matched' => $profiles->count(), 'sent' => $sent, 'failed' => $failed];
    }

    public function url(InstallRequestLead $lead): string
    {
        return url(URL::temporarySignedRoute('install-leads.show', now()->addDays((int) config('installer_leads.link_days', 7)),
            ['lead' => $lead->id], absolute: false));
    }
}
