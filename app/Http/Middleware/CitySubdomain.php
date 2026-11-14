<?php

namespace App\Http\Middleware;

use App\Models\City;
use App\Services\CityContactResolver;
use App\Support\AdminSurfaceRequest;
use Closure;
use Illuminate\Http\Request;

class CitySubdomain
{
    public function __construct(private CityContactResolver $contactResolver) {}

    public function handle(Request $request, Closure $next)
    {
        if (AdminSurfaceRequest::matches($request) || $request->is('city/select', 'city/select/*', 'catalog-icons/*')) {
            return $next($request);
        }

        $host = $request->getHost();
        $baseDomain = config('app.base_domain', 'kotlov.by');

        if (str_ends_with($host, '.'.$baseDomain)) {
            $subdomain = str_replace('.'.$baseDomain, '', $host);

            if ($subdomain && ! in_array($subdomain, ['www', 'new', 'admin'], true)) {
                $city = City::findBySlug($subdomain);

                if ($city) {
                    view()->share('currentCity', $city);
                    view()->share('cityIn', $city->name_in);
                    view()->share('cityTitle', $city->name_title);
                    view()->share('cityName', $city->name);
                } else {
                    abort(404);
                }
            }
        }

        // Default to Minsk on main domain (no subdomain)
        if (! view()->shared('currentCity')) {
            $minsk = City::findBySlug('minsk');
            if ($minsk) {
                view()->share('currentCity', $minsk);
                view()->share('cityIn', $minsk->name_in);
                view()->share('cityTitle', $minsk->name_title);
                view()->share('cityName', $minsk->name);
            } else {
                view()->share('cityIn', 'в Минске');
                view()->share('cityTitle', 'Минске');
                view()->share('cityName', 'Минск');
            }
        }

        $hostCity = view()->shared('currentCity');
        $selected = $hostCity?->exists && $hostCity->slug !== 'minsk'
            ? [
                'city' => $hostCity,
                'city_name' => $hostCity->name,
                'contact' => $this->contactResolver->contactForCity($hostCity),
                'auto_detected' => false,
            ]
            : $this->contactResolver->resolve($request);
        view()->share('selectedCity', $selected['city']);
        view()->share('selectedCityName', $selected['city_name']);
        view()->share('cityContact', $selected['contact']);

        $response = $next($request);

        if ($selected['auto_detected'] && $selected['city']) {
            $response->headers->setCookie(cookie(
                'selected_city',
                $selected['city']->slug,
                60 * 24,
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                'lax',
            ));
            $response->headers->setCookie(cookie(
                'selected_city_source',
                'auto',
                60 * 24,
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                'lax',
            ));
        }

        return $response;
    }
}
