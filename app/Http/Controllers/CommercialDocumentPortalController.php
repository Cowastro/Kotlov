<?php

namespace App\Http\Controllers;

use App\Models\CommercialDocument;
use App\Models\CommercialDocumentPortalChallenge;
use App\Models\CommercialDocumentPortalLink;
use App\Services\CommercialDocuments\CommercialDocumentPdf;
use App\Services\CommercialDocuments\CommercialDocumentPortal;
use App\Services\CommercialDocuments\CommercialDocumentWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class CommercialDocumentPortalController extends Controller
{
    public function preview(CommercialDocument $commercialDocument, CommercialDocumentPortal $portal)
    {
        $link = $commercialDocument->portalLinks()
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->firstOrFail();

        $portal->ensureSnapshot($commercialDocument);
        $link->loadMissing(['document.items.product', 'document.manager', 'document.order']);

        return response()->view('commercial-portal.show', [
            'link' => $link,
            'document' => $link->document,
            'session' => null,
            'preview' => true,
        ])->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function access(string $token, CommercialDocumentPortal $portal)
    {
        try {
            $link = $portal->resolveToken($token);
        } catch (Throwable $exception) {
            return response()->view('commercial-portal.unavailable', ['message' => $exception->getMessage()], 410)
                ->header('Cache-Control', 'private, no-store, max-age=0')
                ->header('Referrer-Policy', 'no-referrer')
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        return response()->view('commercial-portal.activate', [
            'link' => $link,
            'token' => $token,
            'hasDevices' => $link->sessions()->whereNull('revoked_at')->where('expires_at', '>', now())->exists(),
        ])->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function activate(string $token, Request $request, CommercialDocumentPortal $portal): RedirectResponse
    {
        try {
            $link = $portal->resolveToken($token);
            $existing = $portal->authorizedSession($link, $request);
            if ($existing) {
                return redirect()->route('commercial-portal.show', $link->public_id);
            }

            if ($portal->activateFirstDevice($link, $request)) {
                return redirect()->route('commercial-portal.show', $link->public_id);
            }

            $challenge = $portal->requestCode($link, $request);

            return redirect()->route('commercial-portal.verify-form', $challenge->public_id);
        } catch (Throwable $exception) {
            return back()->withErrors(['access' => $exception->getMessage()]);
        }
    }

    public function verifyForm(CommercialDocumentPortalChallenge $challenge)
    {
        $challenge->loadMissing('link.document');

        return response()->view('commercial-portal.verify', ['challenge' => $challenge])
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function verify(
        CommercialDocumentPortalChallenge $challenge,
        Request $request,
        CommercialDocumentPortal $portal,
    ): RedirectResponse {
        $data = $request->validate(['code' => ['required', 'digits:6']]);

        try {
            $portal->verifyCode($challenge, $data['code'], $request);

            return redirect()->route('commercial-portal.show', $challenge->link->public_id);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['code' => $exception->getMessage()]);
        }
    }

    public function show(CommercialDocumentPortalLink $link, Request $request, CommercialDocumentPortal $portal)
    {
        [$link, $session] = $this->authorize($link, $request, $portal);
        $portal->ensureSnapshot($link->document);
        $link->increment('open_count');
        $link->forceFill(['last_opened_at' => now()])->save();
        $portal->event($link, $session, 'proposal_viewed', $request);

        return response()->view('commercial-portal.show', [
            'link' => $link,
            'document' => $link->document,
            'session' => $session,
            'preview' => false,
        ])->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function pdf(
        CommercialDocumentPortalLink $link,
        Request $request,
        CommercialDocumentPortal $portal,
        CommercialDocumentPdf $pdf,
    ): Response {
        [$link, $session] = $this->authorize($link, $request, $portal);
        $portal->event($link, $session, 'pdf_downloaded', $request);

        return response($pdf->render($link->document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="offer-'.$link->document->number.'.pdf"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function accept(
        CommercialDocumentPortalLink $link,
        Request $request,
        CommercialDocumentPortal $portal,
        CommercialDocumentWorkflow $workflow,
    ): RedirectResponse {
        [$link, $session] = $this->authorize($link, $request, $portal);
        $data = $request->validate([
            'confirmation' => ['accepted'],
            'client_phone' => [blank($link->document->client_phone) ? 'required' : 'nullable', 'string', 'max:64'],
        ]);

        try {
            $order = DB::transaction(function () use ($link, $workflow, $data) {
                $document = CommercialDocument::query()->lockForUpdate()->findOrFail($link->commercial_document_id);
                if ($document->valid_until?->isPast()) {
                    throw new RuntimeException('Срок действия предложения истёк.');
                }
                if (! in_array($document->status, ['sent', 'accepted'], true)) {
                    throw new RuntimeException('Это предложение уже нельзя принять.');
                }
                $manager = $document->manager ?: $document->creator;
                if (! $manager) {
                    throw new RuntimeException('У предложения не назначен менеджер.');
                }
                if (blank($document->client_phone) && filled($data['client_phone'] ?? null)) {
                    $document->forceFill(['client_phone' => trim($data['client_phone'])])->save();
                }
                if ($document->status !== 'accepted') {
                    $workflow->accept($document);
                }

                return $workflow->convertToOrder($document->fresh(), $manager);
            });
            $accepted = $link->document->fresh();
            $portal->event($link, $session, 'proposal_accepted', $request, [
                'order_id' => $order->id,
                'document_number' => $accepted->number,
                'revision_number' => $accepted->revision_number,
                'total' => (string) $accepted->total,
                'currency' => $accepted->currency,
                'client_email' => $accepted->client_email,
            ]);

            return back()->with('portal_success', 'Предложение принято. Заказ '.$order->number.' создан.');
        } catch (Throwable $exception) {
            return back()->withErrors(['action' => $exception->getMessage()]);
        }
    }

    public function requestRevision(
        CommercialDocumentPortalLink $link,
        Request $request,
        CommercialDocumentPortal $portal,
        CommercialDocumentWorkflow $workflow,
    ): RedirectResponse {
        [$link, $session] = $this->authorize($link, $request, $portal);
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);

        try {
            $workflow->requestRevision($link->document, $data['note']);
            $portal->event($link, $session, 'revision_requested', $request, ['note' => $data['note']]);

            return back()->with('portal_success', 'Запрос отправлен менеджеру.');
        } catch (Throwable $exception) {
            return back()->withErrors(['action' => $exception->getMessage()]);
        }
    }

    public function reject(
        CommercialDocumentPortalLink $link,
        Request $request,
        CommercialDocumentPortal $portal,
        CommercialDocumentWorkflow $workflow,
    ): RedirectResponse {
        [$link, $session] = $this->authorize($link, $request, $portal);
        $data = $request->validate([
            'reason' => ['required', 'in:price,delivery_time,competitor,budget,requirements_changed,other'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            if ($link->document->status !== 'sent') {
                throw new RuntimeException('Это предложение уже нельзя отклонить.');
            }
            $workflow->reject($link->document, $data['reason'], $data['note'] ?? null);
            $portal->event($link, $session, 'proposal_rejected', $request, $data);

            return back()->with('portal_success', 'Ответ отправлен менеджеру.');
        } catch (Throwable $exception) {
            return back()->withErrors(['action' => $exception->getMessage()]);
        }
    }

    private function authorize(
        CommercialDocumentPortalLink $link,
        Request $request,
        CommercialDocumentPortal $portal,
    ): array {
        $link->loadMissing(['document.items.product', 'document.manager', 'document.order']);
        if ($link->revoked_at) {
            abort(410, 'Доступ к предложению был отозван.');
        }
        $session = $portal->authorizedSession($link, $request);
        if (! $session) {
            abort(403, 'Это устройство не подтверждено. Откройте исходную ссылку из письма.');
        }

        return [$link, $session];
    }
}
