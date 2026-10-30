<?php

namespace App\Services;

use App\Models\B2bClient;
use App\Models\B2bClientAuditLog;
use App\Models\B2bClientInvitation;
use App\Models\B2bClientMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class B2bTeamService
{
    /** @return array{B2bClientInvitation, string} */
    public function invite(B2bClient $client, User $actor, string $email, string $role): array
    {
        $email = mb_strtolower(trim($email));
        $existingUser = User::query()->whereRaw('lower(email) = ?', [$email])->first();
        $this->assertEligibleUser($client, $existingUser);
        $token = Str::random(64);

        $invitation = DB::transaction(function () use ($client, $actor, $email, $role, $token): B2bClientInvitation {
            $client->invitations()
                ->whereRaw('lower(email) = ?', [$email])
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $invitation = $client->invitations()->create([
                'email' => $email,
                'role' => $role,
                'token_hash' => hash('sha256', $token),
                'invited_by' => $actor->id,
                'expires_at' => now()->addDays(7),
            ]);

            $this->audit($client, 'invitation_created', $actor, null, [
                'invitation_id' => $invitation->id,
                'email' => $email,
                'role' => $role,
            ]);

            return $invitation;
        });

        return [$invitation, $token];
    }

    public function invitation(string $token): B2bClientInvitation
    {
        return B2bClientInvitation::query()
            ->with('client')
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();
    }

    public function accept(B2bClientInvitation $invitation, User $user): B2bClientMember
    {
        if (! $invitation->isAvailable()) {
            throw ValidationException::withMessages(['invitation' => 'Приглашение уже использовано, отозвано или просрочено.']);
        }
        if (mb_strtolower($user->email) !== mb_strtolower($invitation->email)) {
            throw ValidationException::withMessages(['email' => 'Войдите под email, на который отправлено приглашение.']);
        }

        $this->assertEligibleUser($invitation->client, $user);

        return DB::transaction(function () use ($invitation, $user): B2bClientMember {
            $locked = B2bClientInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            if (! $locked->isAvailable()) {
                throw ValidationException::withMessages(['invitation' => 'Приглашение уже использовано, отозвано или просрочено.']);
            }

            $membership = $locked->client->members()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'role' => $locked->role,
                    'is_active' => true,
                    'invited_by' => $locked->invited_by,
                    'joined_at' => now(),
                ],
            );
            $locked->update(['accepted_by' => $user->id, 'accepted_at' => now()]);
            $user->forceFill(['email_verified_at' => $user->email_verified_at ?: now()])->save();
            $this->audit($locked->client, 'invitation_accepted', $user, $user, [
                'invitation_id' => $locked->id,
                'role' => $locked->role,
            ]);

            return $membership;
        });
    }

    public function changeRole(B2bClientMember $member, User $actor, string $role): void
    {
        if ($member->role === 'owner') {
            abort(404);
        }

        DB::transaction(function () use ($member, $actor, $role): void {
            $previousRole = $member->role;
            $member->update(['role' => $role, 'is_active' => true]);
            $this->audit($member->client, 'role_changed', $actor, $member->user, [
                'previous_role' => $previousRole,
                'role' => $role,
            ]);
        });
    }

    public function revokeMember(B2bClientMember $member, User $actor): void
    {
        if ($member->role === 'owner') {
            abort(404);
        }

        DB::transaction(function () use ($member, $actor): void {
            $member->update(['is_active' => false]);
            $member->user->forceFill(['remember_token' => null])->save();
            $this->destroySessions($member->user_id);
            $this->audit($member->client, 'access_revoked', $actor, $member->user, [
                'role' => $member->role,
            ]);
        });
    }

    public function revokeInvitation(B2bClientInvitation $invitation, User $actor): void
    {
        DB::transaction(function () use ($invitation, $actor): void {
            $invitation->update(['revoked_at' => now()]);
            $this->audit($invitation->client, 'invitation_revoked', $actor, null, [
                'invitation_id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role,
            ]);
        });
    }

    private function assertEligibleUser(B2bClient $client, ?User $user): void
    {
        if (! $user) {
            return;
        }
        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => 'Аккаунт сотрудника отключён.']);
        }
        if ($user->isManager() || $user->isSupplier()) {
            throw ValidationException::withMessages(['email' => 'Служебный аккаунт нельзя подключить к кабинету клиента.']);
        }
        if ((int) $user->id === (int) $client->user_id) {
            throw ValidationException::withMessages(['email' => 'Владелец кабинета уже подключён.']);
        }

        $membership = B2bClientMember::query()->where('user_id', $user->id)->first();
        if ($membership && (int) $membership->b2b_client_id !== (int) $client->id) {
            throw ValidationException::withMessages(['email' => 'Этот аккаунт уже связан с другой компанией.']);
        }
    }

    private function audit(B2bClient $client, string $event, ?User $actor, ?User $target, array $metadata): void
    {
        B2bClientAuditLog::query()->create([
            'b2b_client_id' => $client->id,
            'actor_user_id' => $actor?->id,
            'target_user_id' => $target?->id,
            'event' => $event,
            'metadata' => $metadata,
        ]);
    }

    private function destroySessions(int $userId): void
    {
        $table = (string) config('session.table', 'sessions');
        if (Schema::hasTable($table)) {
            DB::table($table)->where('user_id', $userId)->delete();
        }
    }
}
