<?php

namespace App\Http\Middleware;

use App\Models\City;
use Closure;
use Illuminate\Http\Request;

class CitySubdomain
{
    public function handle(Request $request, Closure $next)
    {
        $host = $request->getHost();
        $baseDomain = config('app.base_domain', 'kotlov.by');

        if (str_ends_with($host, '.' . $baseDomain)) {
            $subdomain = str_replace('.' . $baseDomain, '', $host);

            if ($subdomain && $subdomain !== 'www' && $subdomain !== 'new') {
                $city = City::findBySlug($subdomain);

                if ($city) {
                    view()->share('currentCity', $city);
                    view()->share('cityIn', $city->name_in);
                    view()->share('cityTitle', $city->name_title);
                    view()->share('cityName', $city->name);
                }
            }
        }

        // Default to Minsk on main domain (no subdomain)
        if (! view()->shared('currentCity')) {
            $minsk = City::findBySlug('minsk');
            if ($minsk) {
                view()->share('currentCity', $minsk);
                view()->share('cityIn',    $minsk->name_in);
                view()->share('cityTitle', $minsk->name_title);
                view()->share('cityName',  $minsk->name);
            } else {
                view()->share('cityIn',    'в Минске');
                view()->share('cityTitle', 'Минске');
                view()->share('cityName',  'Минск');
            }
        }

        return $next($request);
    }
}
