<?php

namespace App\Services;

use App\Models\B2bClient;
use App\Models\PartnerBranch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PartnerBranchModeration
{
    public const FIELDS = ['city_id', 'name', 'address', 'phones', 'email', 'working_hours', 'telegram_username', 'viber_phone', 'instagram_username', 'map_url', 'storefront_title', 'storefront_intro', 'storefront_promo', 'hero_image', 'gallery'];

    public function submit(B2bClient $client, User $actor, array $data, ?PartnerBranch $branch = null): PartnerBranch
    {
        abort_unless($client->status === 'approved' && $client->partner_type !== 'installer'
            && app(B2bCompanyAccess::class)->allows($actor, 'profile.manage', $client), 403);
        abort_if($branch && (int) $branch->b2b_client_id !== (int) $client->id, 403);

        return DB::transaction(function () use ($client, $actor, $data, $branch): PartnerBranch {
            $changes = array_intersect_key($data, array_flip(self::FIELDS));
            if ($branch) {
                $branch = PartnerBranch::query()->lockForUpdate()->findOrFail($branch->id);
                $changes = array_replace($branch->only(self::FIELDS), $branch->pending_changes ?? [], $changes);
            } else {
                $branch = $client->branches()->create(['city_id' => $changes['city_id'], 'name' => $changes['name'], 'phones' => [], 'is_active' => false, 'sort_order' => 100]);
            }
            $branch->forceFill(['pending_changes' => $changes, 'changes_submitted_at' => now(), 'changes_rejection_reason' => null])->save();
            $client->auditLogs()->create(['actor_user_id' => $actor->id, 'event' => 'branch_submitted', 'metadata' => ['branch_id' => $branch->id]]);

            return $branch->fresh();
        });
    }

    public function approve(PartnerBranch $branch, User $actor): void
    {
        abort_unless($actor->canManageB2bOrders(), 403);
        DB::transaction(function () use ($branch, $actor): void {
            $branch = PartnerBranch::query()->lockForUpdate()->findOrFail($branch->id);
            if (! $branch->changes_submitted_at || ! $branch->pending_changes || $branch->client?->status !== 'approved') {
                throw ValidationException::withMessages(['branch' => 'Нет заявки на проверку или доступ партнёра не одобрен.']);
            }
            $data = array_intersect_key($branch->pending_changes, array_flip(self::FIELDS));
            if (! filled($data['address'] ?? null) || ! filled($data['working_hours'] ?? null) || ! collect($data['phones'] ?? [])->filter()->count()) {
                throw ValidationException::withMessages(['branch' => 'Заполните адрес, телефон и режим работы магазина.']);
            }
            if (! \App\Models\City::whereKey($data['city_id'] ?? null)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['branch' => 'Город отключён.']);
            }
            if ((int) $branch->city_id !== (int) $data['city_id']) {
                \App\Models\City::where('primary_partner_branch_id', $branch->id)->update(['primary_partner_branch_id' => null]);
            }
            $branch->forceFill($data + ['is_active' => true, 'pending_changes' => null, 'changes_submitted_at' => null, 'changes_rejection_reason' => null])->save();
            $branch->client->auditLogs()->create(['actor_user_id' => $actor->id, 'event' => 'branch_approved', 'metadata' => ['branch_id' => $branch->id]]);
        });
    }
}
