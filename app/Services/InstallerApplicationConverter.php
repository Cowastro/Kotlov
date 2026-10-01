<?php

namespace App\Services;

use App\Enums\ClientType;
use App\Models\InstallerApplication;
use App\Models\InstallerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InstallerApplicationConverter
{
    private const SPECIALIZATION_MAP = [
        'kotly' => 'heating',
        'teplovye_nasosy' => 'heatpump',
        'kaminy' => 'fireplace',
        'dymohody' => 'chimney',
        'otoplenie' => 'heating',
        'bani' => 'sauna',
    ];

    public function convert(InstallerApplication $application, bool $publish = true): InstallerProfile
    {
        return DB::transaction(function () use ($application, $publish) {
            $application = InstallerApplication::query()
                ->lockForUpdate()
                ->findOrFail($application->getKey());

            if ($application->installer_profile_id) {
                return InstallerProfile::query()->findOrFail($application->installer_profile_id);
            }

            $user = $this->resolveUser($application);
            $profile = $user->installerProfile;

            if (! $profile) {
                $profile = InstallerProfile::query()->create([
                    'user_id' => $user->id,
                    ...$this->profileData($application, $publish),
                ]);
            } else {
                $profile->fill($this->missingProfileData($profile, $application));
                $profile->save();
            }

            $application->update([
                'installer_profile_id' => $profile->id,
                'status' => 'approved',
            ]);

            return $profile->refresh();
        });
    }

    private function resolveUser(InstallerApplication $application): User
    {
        $user = null;

        if ($application->email) {
            $user = User::query()->where('email', $application->email)->first();
        }

        if (! $user && $application->phone) {
            $user = User::query()->where('phone', $application->phone)->first();
        }

        $email = $application->email
            ?: "installer.application.{$application->id}@profiles.kotlov.by";

        if (! $user) {
            $user = User::query()->create([
                'name' => $application->contact_name,
                'email' => $email,
                'password' => Str::random(40),
                'role' => 'installer',
                'client_type' => ClientType::Installer,
                'phone' => $application->phone,
                'is_active' => true,
                'b2b_approved' => true,
                'company_name' => $application->company_name,
                'b2b_comment' => "Создан из заявки монтажника #{$application->id}",
            ]);
        } else {
            $user->update([
                'role' => 'installer',
                'client_type' => ClientType::Installer,
                'phone' => $user->phone ?: $application->phone,
                'is_active' => true,
                'b2b_approved' => true,
                'company_name' => $user->company_name ?: $application->company_name,
            ]);
        }

        return $user;
    }

    private function profileData(InstallerApplication $application, bool $publish): array
    {
        $specializations = $this->mapSpecializations($application->specializations ?? []);
        $experience = (int) ($application->experience_years ?? 0);

        return [
            'contact_name' => $application->contact_name,
            'phone' => $application->phone,
            'email' => $application->email,
            'company_name' => $application->company_name,
            'bio' => $application->message,
            'experience_years' => $experience,
            'city' => $application->city,
            'region' => $application->city === 'Минск' ? 'Минск' : null,
            'work_regions' => $application->city === 'Минск' ? ['Минск'] : null,
            'work_cities' => $application->city ? [$application->city] : null,
            'slug' => $this->uniqueSlug($application),
            'short_description' => $experience > 0
                ? "Монтажник инженерных систем, опыт {$experience} лет"
                : 'Монтажник инженерных систем',
            'specializations' => $specializations,
            'status' => 'active',
            'is_published' => $publish,
            'is_verified' => false,
        ];
    }

    private function missingProfileData(InstallerProfile $profile, InstallerApplication $application): array
    {
        $source = $this->profileData($application, false);
        $data = [];

        foreach ($source as $field => $value) {
            if (in_array($field, ['status', 'is_published', 'is_verified'], true)) {
                continue;
            }

            if (blank($profile->{$field}) && filled($value)) {
                $data[$field] = $value;
            }
        }

        return $data;
    }

    private function mapSpecializations(array $specializations): array
    {
        return collect($specializations)
            ->map(fn (string $specialization) => self::SPECIALIZATION_MAP[$specialization] ?? $specialization)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function uniqueSlug(InstallerApplication $application): string
    {
        $base = Str::slug($application->company_name ?: $application->contact_name)
            ?: "montazhnik-{$application->id}";
        $slug = $base;
        $suffix = 2;

        while (InstallerProfile::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
