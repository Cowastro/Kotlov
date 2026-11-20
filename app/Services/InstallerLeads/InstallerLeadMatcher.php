<?php

namespace App\Services\InstallerLeads;

use App\Models\InstallerProfile;
use App\Models\InstallRequest;
use Illuminate\Support\Collection;

/**
 * Which installers a request for installation is offered to: active published profiles that agreed to get
 * requests, have an e-mail, work in the request's region (or across the country) and do this kind of work.
 */
class InstallerLeadMatcher
{
    /** Request kinds any installer of the heating trade may take. */
    private const ANY_KIND = ['', 'other', 'engineering'];

    /** @return Collection<int, InstallerProfile> the best-ranked first */
    public function candidates(InstallRequest $request, ?int $limit = null): Collection
    {
        $limit ??= (int) config('installer_leads.max_recipients', 30);

        return InstallerProfile::query()
            ->with('user:id,email')
            ->where('status', 'active')
            ->where('is_published', true)
            ->where('lead_notifications', true)
            ->when($request->installer_profile_id, fn ($q) => $q->whereKeyNot($request->installer_profile_id))
            ->get()
            ->filter(fn (InstallerProfile $profile): bool => $this->email($profile) !== null
                && $this->worksIn($profile, $request)
                && $this->doesKind($profile, $request))
            ->sortByDesc(fn (InstallerProfile $profile): array => [
                $profile->priority_until?->isFuture() ? 1 : 0,
                $profile->is_verified ? 1 : 0,
                (float) $profile->rating,
                (int) $profile->reviews_count,
            ])
            ->take($limit)
            ->values();
    }

    public function email(InstallerProfile $profile): ?string
    {
        foreach ([$profile->email, $profile->user?->email] as $email) {
            $email = mb_strtolower(trim((string) $email));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return null;
    }

    public function worksIn(InstallerProfile $profile, InstallRequest $request): bool
    {
        $region = $this->regionKey((string) $request->region);
        $city = $this->words((string) $request->city);

        if ($profile->nationwide || ($region === null && $city === '')) {
            return true;
        }

        $places = collect([$profile->region, $profile->city])
            ->merge((array) $profile->work_regions)
            ->merge((array) $profile->work_cities)
            ->map(fn ($place): string => (string) $place)
            ->filter(fn (string $place): bool => trim($place) !== '');

        $profileRegions = $places->map(fn (string $place): ?string => $this->regionKey($place))->filter()->unique();
        if ($region !== null && ($profileRegions->contains($region) || $this->neighbours($region)->intersect($profileRegions)->isNotEmpty())) {
            return true;
        }

        // The city of the request also counts as its region (a request from «Гродно» without a region).
        $cityRegion = $this->regionKey((string) $request->city);
        if ($region === null && $cityRegion !== null && $profileRegions->contains($cityRegion)) {
            return true;
        }

        return $city !== '' && $places->contains(fn (string $place): bool => $this->words($place) === $city);
    }

    public function doesKind(InstallerProfile $profile, InstallRequest $request): bool
    {
        $kind = (string) $request->specialization;
        $skills = array_values(array_filter((array) $profile->specializations));

        return in_array($kind, self::ANY_KIND, true) || $skills === [] || in_array($kind, $skills, true);
    }

    /** One key per oblast; Minsk city is its own key. */
    public function regionKey(string $place): ?string
    {
        $place = mb_strtolower(str_replace('ё', 'е', $place));

        return match (true) {
            str_contains($place, 'гомел') => 'gomel',
            str_contains($place, 'гродн') => 'grodno',
            str_contains($place, 'брест') => 'brest',
            str_contains($place, 'витеб') => 'vitebsk',
            str_contains($place, 'могил') => 'mogilev',
            (bool) preg_match('/минск(ая|ий|ой|ую)/u', $place) => 'minsk-region',
            str_contains($place, 'минск') => 'minsk',
            default => null,
        };
    }

    /** Minsk installers work in the region around it and vice versa. */
    private function neighbours(string $region): Collection
    {
        return collect(match ($region) {
            'minsk' => ['minsk-region'],
            'minsk-region' => ['minsk'],
            default => [],
        });
    }

    private function words(string $place): string
    {
        $place = mb_strtolower(str_replace('ё', 'е', $place));
        $place = preg_replace('/^(г\.|г |город |аг\.|д\.|п\.|пос\.|гп )\s*/u', '', trim($place)) ?? '';

        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $place) ?? '');
    }
}
