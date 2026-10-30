<?php

namespace App\Services;

use App\Models\B2bClient;
use App\Models\B2bClientMember;
use App\Models\User;
use Illuminate\Support\Collection;

class B2bCompanyAccess
{
    public const PERMISSIONS = [
        'owner' => ['catalog', 'order', 'specifications', 'documents', 'finance', 'profile', 'team'],
        'buyer' => ['catalog', 'order', 'specifications', 'documents'],
        'accountant' => ['documents', 'finance'],
        'viewer' => ['documents'],
    ];

    public function role(User $user, ?B2bClient $client = null): ?string
    {
        $client ??= $user->activeB2bClient();
        if (! $client) {
            return $user->client_type?->value !== 'retail' && $user->b2b_approved
                ? 'owner'
                : null;
        }
        if ((int) $client->user_id === (int) $user->id) {
            return 'owner';
        }

        return B2bClientMember::query()
            ->where('b2b_client_id', $client->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->value('role');
    }

    public function allows(User $user, string $permission, ?B2bClient $client = null): bool
    {
        $role = $this->role($user, $client);

        return $role !== null && in_array($permission, self::PERMISSIONS[$role] ?? [], true);
    }

    /** @return Collection<int, int> */
    public function userIds(B2bClient $client): Collection
    {
        return $client->members()
            ->where('is_active', true)
            ->pluck('user_id')
            ->push((int) $client->user_id)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    /** @return Collection<int, int> */
    public function allUserIds(B2bClient $client): Collection
    {
        return $client->members()
            ->pluck('user_id')
            ->push((int) $client->user_id)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }
}
