<?php

namespace App\Services;

use App\Models\City;
use App\Models\PartnerBranch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CityStorefrontManager
{
    public function update(City $city, array $data): City
    {
        return DB::transaction(function () use ($city, $data): City {
            $city = City::query()->lockForUpdate()->findOrFail($city->id);
            $enabled = (bool) ($data['storefront_enabled'] ?? false);
            $branchId = $data['primary_partner_branch_id'] ?? null;
            if ($city->slug === 'minsk') {
                throw ValidationException::withMessages(['storefront_enabled' => 'Основной сайт управляется отдельно.']);
            }
            $branch = $branchId ? PartnerBranch::query()->visibleIn($city)->find($branchId) : null;
            if ($branchId && ! $branch) {
                throw ValidationException::withMessages(['primary_partner_branch_id' => 'Выберите активный филиал одобренного партнёра в этом городе.']);
            }
            if ($enabled) {
                $branch ??= PartnerBranch::query()->visibleIn($city)->first();
                if (! $city->is_active || ! $branch || ! filled($branch->address) || ! collect($branch->phones)->filter()->count()) {
                    throw ValidationException::withMessages(['storefront_enabled' => 'Для публикации нужен активный город и филиал одобренного партнёра с адресом и телефоном.']);
                }
            }
            $city->update([
                'storefront_enabled' => $enabled,
                'primary_partner_branch_id' => $branch?->id,
            ]);

            return $city->fresh();
        });
    }
}
