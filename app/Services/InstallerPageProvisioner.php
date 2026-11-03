<?php

namespace App\Services;

use App\Models\B2bClient;
use App\Models\InstallerProfile;
use Illuminate\Support\Str;

/**
 * An approved B2B client of the "Монтажная организация" type always has an
 * installer page: it is created from the company card and waits for moderation,
 * so the partner only fills it in the cabinet.
 */
class InstallerPageProvisioner
{
    public function ensureFor(B2bClient $client): ?InstallerProfile
    {
        if ($existing = InstallerProfile::query()->where('user_id', $client->user_id)->first()) {
            return $existing;
        }

        $base = Str::slug((string) $client->company_name) ?: 'montazhnik-'.$client->id;
        $slug = $base;
        for ($suffix = 2; InstallerProfile::query()->where('slug', $slug)->exists(); $suffix++) {
            $slug = $base.'-'.$suffix;
        }

        return InstallerProfile::query()->create([
            'user_id' => $client->user_id,
            'company_name' => $client->company_name,
            'unp' => $client->unp,
            'contact_name' => $client->contact_name,
            'phone' => $client->contact_phone,
            'email' => $client->contact_email,
            'city' => $client->default_delivery_city,
            'slug' => $slug,
            'short_description' => 'Монтаж инженерных систем',
            'status' => 'pending',
            'is_published' => false,
            'is_verified' => false,
        ]);
    }

    public static function shouldHavePage(B2bClient $client): bool
    {
        return $client->status === 'approved' && $client->partner_type === 'installer' && $client->user_id;
    }
}
