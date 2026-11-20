<?php

namespace App\Http\Controllers;

use App\Models\InstallRequest;
use App\Models\InstallRequestLead;
use App\Services\InstallerLeads\InstallerLeadTaker;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** The installer's page of an offered request, opened from the e-mail by a signed link. */
class InstallerLeadController extends Controller
{
    public function __construct(private readonly InstallerLeadTaker $taker) {}

    public function show(InstallRequestLead $lead, Request $request): Response
    {
        $lead->loadMissing(['installRequest.product', 'installerProfile']);
        if (! $lead->opened_at) {
            $lead->forceFill(['opened_at' => now()])->save();
        }

        $state = $this->taker->state($lead);
        $installRequest = $lead->installRequest;

        return response()->view('pages.installer-lead', [
            'lead' => $lead,
            'installRequest' => $installRequest,
            'state' => $state,
            'kind' => InstallRequest::SPECIALIZATIONS[$installRequest->specialization] ?? 'Монтаж отопления',
            'description' => $state === InstallerLeadTaker::ALREADY_YOURS
                ? (string) $installRequest->description
                : $this->hideContacts((string) $installRequest->description),
            'takenCount' => $this->taker->takenCount($installRequest),
            'maxTakers' => $this->taker->maxTakers(),
            'formUrl' => $request->fullUrl(),
            'subscribed' => (bool) $lead->installerProfile->lead_notifications,
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }

    /** One signed address for the page and its buttons: take the request, or turn the e-mails off and on. */
    public function update(InstallRequestLead $lead, Request $request)
    {
        $action = (string) $request->input('action');

        if ($action === 'subscribe' || $action === 'unsubscribe') {
            $lead->installerProfile->forceFill(['lead_notifications' => $action === 'subscribe'])->save();

            return redirect($request->fullUrl())->with('lead_status', $action === 'subscribe'
                ? 'Заявки снова будут приходить вам на почту.'
                : 'Больше не будем присылать вам заявки. Включить их можно на этой же странице.');
        }

        $result = $this->taker->take($lead);

        return redirect($request->fullUrl())->with('lead_status', match ($result) {
            InstallerLeadTaker::TAKEN => 'Заявка ваша. Свяжитесь с клиентом — контакты ниже.',
            InstallerLeadTaker::FULL => 'Заявку уже взяли другие монтажники.',
            InstallerLeadTaker::CLOSED => 'Заявка уже закрыта.',
            default => null,
        });
    }

    /** Phone numbers and e-mails a client typed into the description stay hidden until the request is taken. */
    private function hideContacts(string $text): string
    {
        $text = preg_replace('/[\w.+-]+@[\w-]+(\.[\w-]+)+/u', '[скрыто]', $text) ?? $text;

        return preg_replace('/\+?\d[\d\s()\-]{5,}\d/u', '[скрыто]', $text) ?? $text;
    }
}
