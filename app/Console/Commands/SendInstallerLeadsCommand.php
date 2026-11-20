<?php

namespace App\Console\Commands;

use App\Models\InstallerProfile;
use App\Models\InstallRequest;
use App\Services\InstallerLeads\InstallerLeadDispatcher;
use App\Services\InstallerLeads\InstallerLeadMatcher;
use Illuminate\Console\Command;

class SendInstallerLeadsCommand extends Command
{
    protected $signature = 'installers:send-leads
        {--audit : Only count the installers who can get requests and the recent requests by source}
        {--preview= : Only show how many installers a request ID would be offered to}
        {--send= : Offer this request ID by hand (made before the mailing or from another form); a preview without --apply}
        {--apply : With --send, really send the e-mails}
        {--recent=0 : List the last N requests (id, source, kind, date, offered/taken)}';

    protected $description = 'Offer new requests for installation to the matching installers by e-mail';

    public function handle(InstallerLeadDispatcher $dispatcher, InstallerLeadMatcher $matcher): int
    {
        if ($this->option('audit')) {
            return $this->audit($matcher);
        }

        if ($this->option('preview')) {
            $request = InstallRequest::query()->findOrFail((int) $this->option('preview'));
            $profiles = $matcher->candidates($request);
            $this->line('metric.installer_leads.preview_recipients='.$profiles->count());
            $this->line('audit.installer_leads.preview='.$request->id.':'.$profiles->pluck('id')->implode(','));

            return self::SUCCESS;
        }

        if ((int) $this->option('recent') > 0) {
            InstallRequest::query()->withCount(['leads', 'leads as taken_count' => fn ($q) => $q->whereNotNull('taken_at')])
                ->latest('id')->limit((int) $this->option('recent'))->get()
                ->each(fn (InstallRequest $r) => $this->line('audit.installer_leads.recent='.$r->id.':'.preg_replace('/[^a-z0-9_]/', '_', (string) ($r->source ?: 'none'))
                    .':'.preg_replace('/[^a-z0-9_]/', '_', (string) ($r->specialization ?: 'none')).':'.$r->created_at?->format('YmdHi').':'.$r->status
                    .':offered='.$r->leads_count.':taken='.$r->taken_count));

            return self::SUCCESS;
        }

        if ($this->option('send')) {
            $request = InstallRequest::query()->findOrFail((int) $this->option('send'));
            if (! $this->option('apply')) {
                $profiles = $matcher->candidates($request);
                $this->line('metric.installer_leads.preview_recipients='.$profiles->count());
                $this->line('audit.installer_leads.preview='.$request->id.':'.$profiles->pluck('id')->implode(','));

                return self::SUCCESS;
            }
            $result = $dispatcher->dispatch($request, again: true);
            $this->line('audit.installer_leads.request='.$request->id.':matched='.$result['matched'].':sent='.$result['sent'].':failed='.$result['failed']);

            return self::SUCCESS;
        }

        if (! config('installer_leads.enabled')) {
            return self::SUCCESS;
        }

        foreach ($dispatcher->pending() as $request) {
            $result = $dispatcher->dispatch($request);
            $this->line('audit.installer_leads.request='.$request->id.':matched='.$result['matched'].':sent='.$result['sent'].':failed='.$result['failed']);
        }

        return self::SUCCESS;
    }

    private function audit(InstallerLeadMatcher $matcher): int
    {
        $profiles = InstallerProfile::query()->with('user:id,email')->get();
        $active = $profiles->filter(fn (InstallerProfile $p): bool => $p->status === 'active' && $p->is_published);
        $reachable = $active->filter(fn (InstallerProfile $p): bool => $p->lead_notifications && $matcher->email($p) !== null);

        $this->line('metric.installer_leads.profiles='.$profiles->count());
        $this->line('metric.installer_leads.active_published='.$active->count());
        $this->line('metric.installer_leads.with_email='.$reachable->count());
        $this->line('metric.installer_leads.nationwide='.$reachable->where('nationwide', true)->count());
        $this->line('metric.installer_leads.without_region='.$reachable->filter(fn (InstallerProfile $p): bool => ! $p->nationwide
            && collect([$p->region, $p->city])->merge((array) $p->work_regions)->merge((array) $p->work_cities)->filter()->isEmpty())->count());
        $this->line('metric.installer_leads.without_specializations='.$reachable->filter(fn (InstallerProfile $p): bool => array_filter((array) $p->specializations) === [])->count());

        foreach (['minsk', 'minsk-region', 'brest', 'vitebsk', 'gomel', 'grodno', 'mogilev'] as $region) {
            $count = $reachable->filter(fn (InstallerProfile $p): bool => $p->nationwide || collect([$p->region, $p->city])
                ->merge((array) $p->work_regions)->contains(fn ($place): bool => $matcher->regionKey((string) $place) === $region))->count();
            $this->line('metric.installer_leads.region_'.str_replace('-', '_', $region).'='.$count);
        }

        InstallRequest::query()->where('created_at', '>=', now()->subDays(90))
            ->selectRaw('source, count(*) as total')->groupBy('source')->pluck('total', 'source')
            ->each(fn ($total, $source) => $this->line('metric.installer_leads.requests_90d_'.preg_replace('/[^a-z0-9_]/', '_', (string) ($source ?: 'none')).'='.$total));

        return self::SUCCESS;
    }
}
