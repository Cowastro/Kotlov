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

        view()->share('currentCity', view()->shared('currentCity', null));
        view()->share('cityIn', view()->shared('cityIn', null));

        return $next($request);
    }
}
