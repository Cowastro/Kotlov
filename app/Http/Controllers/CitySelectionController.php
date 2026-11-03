<?php

namespace App\Http\Controllers;

use App\Services\CityContactResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CitySelectionController extends Controller
{
    public function __invoke(Request $request, string $slug, CityContactResolver $resolver): RedirectResponse
    {
        $city = $resolver->selectableCity($slug);
        abort_unless($city, 404);

        $returnTo = (string) $request->query('return', '/');
        if (! str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')) {
            $returnTo = '/';
        }

        return redirect($returnTo)->withCookie(cookie(
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
