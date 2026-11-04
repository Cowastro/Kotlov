<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\City;
use App\Models\PartnerBranch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CityStorefrontController extends Controller
{
    private const LANDING_CATEGORIES = [
        'kotly' => 'Котлы отопления',
        'teplovyie-nasosyi' => 'Тепловые насосы',
        'pechki' => 'Печи и камины',
        'dymohody' => 'Дымоходы',
        'vodonagrevateli' => 'Водонагреватели',
        'bani-i-sauny' => 'Бани и сауны',
    ];

    public function __invoke(Request $request)
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
        $categories = Category::query()
            ->active()
            ->whereIn('slug', array_keys(self::LANDING_CATEGORIES))
            ->get()
            ->sortBy(fn (Category $category): int => array_search($category->slug, array_keys(self::LANDING_CATEGORIES), true))
            ->values()
            ->map(function (Category $category): Category {
                $category->setAttribute('storefront_name', self::LANDING_CATEGORIES[$category->slug] ?? $category->name);

                return $category;
            });

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
                'telephone' => $branch->phones[0] ?? null,
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

        return view('pages.city-storefront', compact(
            'city', 'branches', 'primaryBranch', 'categories',
            'title', 'description', 'canonical', 'schemaJson'
        ));
    }
}
