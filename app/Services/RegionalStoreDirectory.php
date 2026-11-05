<?php

namespace App\Services;

use App\Models\City;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class RegionalStoreDirectory
{
    public function stores(): Collection
    {
        $publicBranches = fn ($query) => $query->where('is_active', true)
            ->whereHas('client', fn ($client) => $client->where('status', 'approved'));

        return City::query()->where('is_active', true)->where('storefront_enabled', true)
            ->where('slug', '!=', 'minsk')
            ->whereHas('partnerBranches', $publicBranches)
            ->with(['partnerBranches' => fn ($query) => $publicBranches($query)->orderBy('sort_order')->orderBy('name')])
            ->orderBy('name')->get()->map(function (City $city): array {
                $branch = $city->partnerBranches->firstWhere('id', $city->primary_partner_branch_id)
                    ?? $city->partnerBranches->first();

                return [
                    'city' => $city->name, 'slug' => $city->slug, 'url' => $city->storefrontUrl().'/',
                    'name' => $branch->name, 'address' => $branch->address,
                    'hours' => $branch->working_hours,
                    'image' => $branch->hero_image ? Storage::url($branch->hero_image) : null,
                ];
            });
    }
}
