<?php

namespace App\Http\Controllers;

use App\Models\PartnerBranch;
use App\Services\CityContactResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CitySelectionController extends Controller
{
    public function __invoke(Request $request, CityContactResolver $resolver, ?string $slug = null): RedirectResponse
    {
        $slug ??= (string) $request->input('slug');
        abort_unless(preg_match('/^[a-z0-9-]+$/', $slug) === 1, 404);

        $city = $resolver->selectableCity($slug);
        abort_unless($city, 404);

        $baseDomain = config('app.base_domain', 'kotlov.by');
        $hasStorefront = $city->slug !== 'minsk' && $city->storefront_enabled
            && PartnerBranch::query()->visibleIn($city)->exists();
        if (! $hasStorefront && str_ends_with($request->getHost(), '.'.$baseDomain)) {
            return redirect()->away('https://'.$baseDomain.$request->getRequestUri())
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        $returnTo = (string) $request->input('return', '/');
        if (! str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//') || str_contains($returnTo, '\\')) {
            $returnTo = '/';
        }

        $fromRegionalNews = preg_match('~^/news(?:/[^/]+)?/?$~', explode('?', $returnTo, 2)[0]) === 1;

        $redirect = $hasStorefront
            ? redirect()->away($city->storefrontUrl().($fromRegionalNews ? '/news' : '/'))
            : redirect($fromRegionalNews ? '/regions/news' : $returnTo);

        return $redirect->withCookie(cookie(
            'selected_city',
            $city->slug,
            60 * 24 * 365,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax',
        ))->withCookie(cookie(
            'selected_city_source',
            'manual',
            60 * 24 * 365,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax',
        ))->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
