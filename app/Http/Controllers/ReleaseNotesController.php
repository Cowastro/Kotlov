<?php

namespace App\Http\Controllers;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Internal release notes for the team. A release opens without login from a signed
 * link that expires after 48 hours (to send to the team chat); signed-in staff open it
 * any time and take a fresh link from «Что нового» in the admin.
 */
class ReleaseNotesController extends Controller
{
    public const LINK_HOURS = 48;

    /** Release date => title and one-line summary; each has a view admin/releases/{date}. */
    public const RELEASES = [
        '2026-10-26' => [
            'title' => 'Быстрый заказ, счёт из заказа и новые бренды «Майтек Групп»',
            'summary' => 'Ручной заказ в одной форме, счёт / КП из заказа, HOTEX, SKAT, Сангай и ТЭНы INNOWARM, цены Greolit PROFI, калькулятор дымохода, WebPay и ЕРИП.',
        ],
    ];

    public function index(Request $request): View|RedirectResponse
    {
        // Staff sign in through the admin, which then brings them back here.
        if (! $request->user()) {
            return redirect()->guest(Filament::getPanel('admin')->getLoginUrl());
        }
        abort_unless($this->isStaff($request->user()), 403);

        $releases = collect(self::RELEASES)->sortKeysDesc()
            ->map(fn (array $release, string $date): array => $release + ['link' => self::temporaryLink($date)])
            ->all();

        return view('admin.releases.index', [
            'releases' => $releases,
            'linkHours' => self::LINK_HOURS,
        ]);
    }

    public function show(Request $request, string $date): View
    {
        abort_unless(isset(self::RELEASES[$date]), 404);
        abort_unless($request->hasValidSignature() || $this->isStaff($request->user()), 403, 'Срок действия ссылки истёк. Попросите новую ссылку у руководителя.');

        return view('admin.releases.'.$date, [
            'canNavigate' => $this->isStaff($request->user()),
        ]);
    }

    public static function temporaryLink(string $date): string
    {
        return URL::temporarySignedRoute('releases.show', now()->addHours(self::LINK_HOURS), ['date' => $date]);
    }

    private function isStaff(mixed $user): bool
    {
        return $user instanceof User && $user->is_active && $user->isManager();
    }
}
