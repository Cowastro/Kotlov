<?php

namespace App\Services;

use App\Mail\InstallerB2bInvitationMail;
use App\Models\B2bClient;
use App\Models\B2bClientAuditLog;
use App\Models\InstallerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

/**
 * Turns an existing installer profile into a B2B partner on the same account:
 * the public installer page stays, the B2B cabinet opens with the company data
 * already filled in, and the installer gets a letter with a link to set a
 * password — no new registration.
 */
class InstallerB2bConversion
{
    private const PLACEHOLDER_DOMAIN = '@profiles.kotlov.by';

    public function __construct(private readonly B2bClientWorkflow $b2bClients) {}

    /** active | invited | no_email | none */
    public function state(InstallerProfile $profile): string
    {
        $client = $profile->user?->b2bClient;
        if ($client?->status === 'approved') {
            return $profile->b2b_invited_at ? 'invited' : 'active';
        }

        return $this->email($profile) ? 'none' : 'no_email';
    }

    public function email(InstallerProfile $profile): ?string
    {
        foreach ([$profile->user?->email, $profile->email] as $candidate) {
            $candidate = mb_strtolower(trim((string) $candidate));
            if (filter_var($candidate, FILTER_VALIDATE_EMAIL) && ! str_ends_with($candidate, self::PLACEHOLDER_DOMAIN)) {
                return $candidate;
            }
        }

        return null;
    }

    public function convert(InstallerProfile $profile, ?User $actor = null): B2bClient
    {
        $client = DB::transaction(function () use ($profile, $actor): B2bClient {
            $profile = InstallerProfile::query()->lockForUpdate()->with('user.b2bClient')->findOrFail($profile->id);
            $user = $profile->user;
            $email = $this->email($profile);
            if (! $user || ! $email) {
                throw ValidationException::withMessages(['email' => 'У монтажника «'.$profile->display_name.'» нет e-mail для входа.']);
            }
            if (mb_strtolower((string) $user->email) !== $email) {
                if (User::query()->where('email', $email)->whereKeyNot($user->id)->exists()) {
                    throw ValidationException::withMessages(['email' => 'E-mail '.$email.' уже занят другим аккаунтом.']);
                }
                $user->forceFill(['email' => $email, 'email_verified_at' => null]);
            }
            $user->forceFill(['b2b_approved' => true, 'is_active' => true])->save();

            $client = $user->b2bClient ?? new B2bClient(['user_id' => $user->id]);
            if (in_array($client->status, ['paused', 'rejected'], true)) {
                throw ValidationException::withMessages(['status' => 'B2B-доступ «'.$client->company_name.'» приостановлен или отклонён — включите его в карточке B2B-клиента.']);
            }
            if ($client->status !== 'approved') {
                $client->fill([
                    'status' => 'pending',
                    'partner_type' => 'installer',
                    'company_name' => $profile->company_name ?: ($profile->legal_name ?: ($profile->contact_name ?: $user->name)),
                    'unp' => $client->unp ?: $profile->unp,
                    'contact_name' => $profile->contact_name ?: $user->name,
                    'contact_email' => $email,
                    'contact_phone' => $profile->phone ?: $user->phone,
                    'application_comment' => 'Переведён из профиля монтажника #'.$profile->id.'.',
                ])->save();
                $client = $this->b2bClients->approve($client, $actor);
            }

            B2bClientAuditLog::query()->create([
                'b2b_client_id' => $client->id,
                'actor_user_id' => $actor?->id,
                'target_user_id' => $user->id,
                'event' => 'installer_converted',
                'metadata' => ['installer_profile_id' => $profile->id, 'email' => $email],
            ]);

            return $client;
        });

        $this->invite($profile->refresh());

        return $client;
    }

    /** Changes once the password is set, so the activation link works only once. */
    public static function linkVersion(User $user): string
    {
        return substr(hash('sha256', $user->id.'|'.$user->getAuthPassword()), 0, 16);
    }

    /** Letter with a 14-day, one-time link to set a password; sent right away, not queued. */
    public function invite(InstallerProfile $profile): void
    {
        $user = $profile->user()->firstOrFail();
        $url = URL::temporarySignedRoute('b2b.activate', now()->addDays(14), [
            'user' => $user->id,
            'v' => self::linkVersion($user),
        ]);

        Mail::mailer('transactional')->to($user->email)->send(new InstallerB2bInvitationMail($profile, $url));
        $profile->forceFill(['b2b_invited_at' => now()])->saveQuietly();
    }
}
