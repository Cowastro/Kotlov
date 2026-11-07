<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\PartnerBranch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CityStorefrontController extends Controller
{
    public function __invoke(Request $request): array
    {
        /** @var City|null $city */
        $city = view()->shared('currentCity');
        abort_unless($city?->exists && $city->slug !== 'minsk', 404);

        $branches = PartnerBranch::query()
            ->visibleIn($city)
            ->with('client:id,company_name,status')
            ->get();
        abort_if($branches->isEmpty(), 404);

        $primaryBranch = $branches->first();

        $title = $primaryBranch->seo_title
            ?: 'Магазин отопления KOTLOV в '.$city->name_title.' — адрес и каталог';
        $description = $primaryBranch->seo_description
            ?: 'Котлы, печи, камины, дымоходы и тепловые насосы в '.$city->name_title.'. Адрес магазина, телефон, режим работы и каталог KOTLOV.';
        $canonical = 'https://'.$request->getHost().'/';
        $schemaJson = json_encode([
            '@context' => 'https://schema.org',
            '@graph' => $branches->map(fn (PartnerBranch $branch): array => array_filter([
                '@type' => 'Store',
                'name' => $branch->name,
                'url' => $canonical,
                'image' => $branch->hero_image ? url(Storage::url($branch->hero_image)) : null,
                'telephone' => $branch->phoneLinks()[0]['display'] ?? null,
                'email' => $branch->email,
                'hasMap' => $branch->mapLink(),
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => $branch->address,
                    'addressLocality' => $city->name,
                    'addressCountry' => 'BY',
                ],
            ]))->values()->all(),
        ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return compact(
            'city', 'primaryBranch',
            'title', 'description', 'canonical', 'schemaJson'
        );
    }
}
