<?php

namespace App\Services;

use App\Models\City;
use App\Models\CityContactProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class CityContactResolver
{
    /** @return array{city: City|null, city_name: string, contact: array<string, mixed>} */
    public function resolve(Request $request): array
    {
        $city = $this->selectedCity($request->cookie('selected_city'));
        $contact = $this->defaultContact();

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

        $contact['phone_links'] = collect($contact['phones'])
            ->map(fn ($phone): array => [
                'display' => (string) $phone,
                'href' => '+'.preg_replace('/\D+/', '', (string) $phone),
            ])
            ->values()
            ->all();

        return [
            'city' => $city,
            'city_name' => $city?->name ?? 'Минск',
            'contact' => $contact,
        ];
    }

    public function selectableCity(string $slug): ?City
    {
        if (! Schema::hasTable('cities')) {
            return $slug === 'minsk' ? $this->defaultMinsk() : null;
        }

        $query = City::query()->where('slug', $slug)->where('is_active', true);

        if ($slug !== 'minsk' && Schema::hasColumn('cities', 'storefront_enabled')) {
            $query->where('storefront_enabled', true);
        }

        $city = $query->first();

        return $city ?? ($slug === 'minsk' ? $this->defaultMinsk() : null);
    }

    private function selectedCity(?string $slug): ?City
    {
        return $this->selectableCity($slug ?: 'minsk')
            ?? $this->selectableCity('minsk');
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
