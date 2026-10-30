<?php

namespace App\Services;

use App\Models\B2bClient;
use App\Models\B2bClientMember;
use App\Models\User;
use Illuminate\Support\Collection;

class B2bCompanyAccess
{
    public const LABELS = [
        'catalog.view' => 'Просмотр доступного B2B-каталога и цен',
        'orders.create' => 'Корзины и оформление заказов',
        'specifications.manage' => 'Загрузка и обновление спецификаций',
        'orders.view' => 'История заказов компании',
        'documents.view' => 'Просмотр КП и счетов',
        'documents.download' => 'Скачивание КП и счетов в PDF',
        'contracts.download' => 'Просмотр и скачивание договоров',
        'finance.view' => 'Сверка и кредитный лимит',
        'profile.manage' => 'Реквизиты компании',
        'team.manage' => 'Сотрудники, роли и журнал доступа',
    ];

    public const PERMISSIONS = [
        'owner' => [
            'catalog.view', 'orders.create', 'specifications.manage', 'orders.view',
            'documents.view', 'documents.download', 'contracts.download', 'finance.view',
            'profile.manage', 'team.manage',
        ],
        'buyer' => [
            'catalog.view', 'orders.create', 'specifications.manage', 'orders.view',
            'documents.view', 'documents.download', 'contracts.download',
        ],
        'accountant' => [
            'orders.view', 'documents.view', 'documents.download', 'contracts.download', 'finance.view',
        ],
        'viewer' => ['orders.view', 'documents.view', 'documents.download', 'contracts.download'],
    ];

    private const LEGACY_ALIASES = [
        'catalog' => 'catalog.view',
        'order' => 'orders.create',
        'specifications' => 'specifications.manage',
        'documents' => 'documents.view',
        'finance' => 'finance.view',
        'profile' => 'profile.manage',
        'team' => 'team.manage',
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
        $permission = self::LEGACY_ALIASES[$permission] ?? $permission;

        return $role !== null && in_array($permission, self::PERMISSIONS[$role] ?? [], true);
    }

    /** @return array<string, string> */
    public function permissionLabels(string $role): array
    {
        return collect(self::PERMISSIONS[$role] ?? [])
            ->mapWithKeys(fn (string $permission): array => [
                $permission => self::LABELS[$permission] ?? $permission,
            ])
            ->all();
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
