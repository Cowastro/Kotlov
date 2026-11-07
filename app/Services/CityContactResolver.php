<?php

namespace App\Services;

use App\Models\City;
use App\Models\CityContactProfile;
use App\Models\PartnerBranch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CityContactResolver
{
    /** @return array{city: City|null, city_name: string, contact: array<string, mixed>, auto_detected: bool} */
    public function resolve(Request $request): array
    {
        $selectedSlug = $request->cookie('selected_city');
        $selectionSource = $request->cookie('selected_city_source');
        // Cookies created before source tracking are treated as an explicit choice.
        // This prevents a deployment from unexpectedly changing a visitor's city.
        $manualSelection = $selectedSlug && $selectionSource !== 'auto';
        $city = $manualSelection ? $this->selectableCity($selectedSlug) : null;
        $autoDetected = false;

        if (! $manualSelection) {
            $city = $this->detectedCity($request);
            $autoDetected = $city !== null;

            if (! $city && $selectedSlug) {
                $city = $this->selectableCity($selectedSlug);
            }
        }

        $city ??= $this->selectableCity('minsk');
        $contact = $this->contactForCity($city);

        return [
            'city' => $city,
            'city_name' => $city?->name ?? 'Минск',
            'contact' => $contact,
            'auto_detected' => $autoDetected,
        ];
    }

    /** @return array<string, mixed> */
    public function contactForCity(?City $city): array
    {
        $contact = $this->defaultContact();
        $branch = null;

        if ($city?->exists
            && Schema::hasTable('partner_branches')
            && Schema::hasTable('b2b_clients')) {
            $branch = PartnerBranch::query()->visibleIn($city)
                ->when($city->slug === 'minsk', fn ($query) => $query->whereHas('client', fn ($client) => $client->where('unp', '193265863')))
                ->first();

            if ($branch && $city->slug !== 'minsk') {
                foreach ([
                    'company_name' => $branch->name,
                    'phones' => $branch->phones,
                    'email' => $branch->email,
                    'address' => $branch->address,
                    'map_url' => $branch->mapLink(),
                    'map_embed_url' => $branch->mapEmbedLink(),
                ] as $key => $value) {
                    if ($value !== null && $value !== '' && $value !== []) {
                        $contact[$key] = $value;
                    }
                }
            }
        }

        if ($city?->exists && Schema::hasTable('city_contact_profiles')) {
            $profile = CityContactProfile::query()
                ->where('city_id', $city->id)
                ->where('is_active', true)
                ->first();

            if ($profile) {
                foreach (['company_name', 'phones', 'email', 'address', 'map_url', 'map_embed_url'] as $key) {
                    $value = $profile->{$key};

                    if ($value !== null && $value !== '' && $value !== []) {
                        $contact[$key] = $value;
                    }
                }
            }
        }

        $contact['online_only'] = $branch?->isOnlineOnly() ?? false;
        $contact['working_hours'] = $branch?->working_hours;
        if ($contact['online_only']) {
            $contact['phones'] = [];
        }

        $contact['phone_links'] = collect($contact['phones'])
            ->map(fn ($phone): array => [
                'display' => (string) $phone,
                'href' => '+'.preg_replace('/\D+/', '', (string) $phone),
            ])
            ->values()
            ->all();

        return $contact;
    }

    public function selectableCity(string $slug): ?City
    {
        if (! Schema::hasTable('cities')) {
            return $slug === 'minsk' ? $this->defaultMinsk() : null;
        }

        $query = City::query()->where('slug', $slug)->where('is_active', true);

        $city = $query->first();

        return $city ?? ($slug === 'minsk' ? $this->defaultMinsk() : null);
    }

    private function detectedCity(Request $request): ?City
    {
        $country = $this->firstHeader($request, [
            'CF-IPCountry',
            'CloudFront-Viewer-Country',
            'X-Vercel-IP-Country',
        ]);
        $cityName = $this->firstHeader($request, [
            'CF-IPCity',
            'CloudFront-Viewer-City',
            'X-Vercel-IP-City',
            'X-Geo-City',
        ]);

        if (strtoupper($country) !== 'BY' || $cityName === '') {
            return null;
        }

        $normalized = Str::slug(rawurldecode($cityName));
        $aliases = [
            'barysaw' => 'borisov',
            'barysau' => 'borisov',
            'homiel' => 'gomel',
            'hrodna' => 'grodno',
            'mahilyow' => 'mogilev',
            'mahiliou' => 'mogilev',
            'mahilou' => 'mogilev',
            'viciebsk' => 'vitebsk',
            'baranavichy' => 'baranovichi',
            'babruysk' => 'bobruysk',
        ];
        $slug = $aliases[$normalized] ?? $normalized;

        return $slug !== '' ? $this->selectableCity($slug) : null;
    }

    /** @param array<int, string> $names */
    private function firstHeader(Request $request, array $names): string
    {
        foreach ($names as $name) {
            $value = trim((string) $request->header($name));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function defaultMinsk(): City
    {
        return new City([
            'slug' => 'minsk',
            'name' => 'Минск',
            'name_in' => 'в Минске',
            'name_title' => 'Минске',
            'region' => 'Минск',
            'is_active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function defaultContact(): array
    {
        $seller = config('legal.seller', []);

        return [
            'company_name' => $seller['short_name'] ?? $seller['name'] ?? 'KOTLOV.BY',
            'phones' => $seller['phones'] ?? ['+375 (29) 377-70-92', '+375 (33) 377-70-93'],
            'email' => $seller['email'] ?? 'info@kotlov.by',
            'address' => 'Беларусь, г. Минск, ул. Селицкого, 39Б, каб. 23',
            'map_url' => 'https://www.google.com/maps?q=Минск,+ул.+Селицкого,+39Б',
            'map_embed_url' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2350.8!2d27.5615!3d53.9065!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x46dbcfd35b1e6ad3%3A0xb97b72b3def99842!2z0YPQuy4g0KHQtdC70LjRhtC60L7Qs9C-LCAzOdCkLCDQnNC40L3RgdC6!5e0!3m2!1sru!2sby!4v1700000000000',
        ];
    }
}
