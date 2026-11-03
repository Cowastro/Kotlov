<?php

namespace App\Services;

use App\Filament\Resources\PartnerOnboardings\PartnerOnboardingResource;
use App\Mail\PartnerOnboardingApprovedMail;
use App\Mail\PartnerOnboardingInviteMail;
use App\Models\B2bClient;
use App\Models\CrmCounterparty;
use App\Models\PartnerBranch;
use App\Models\PartnerOnboarding;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Partners from the existing base: invite by a personal link, collect the
 * questionnaire, and on approval create the B2B client with its sales points.
 */
class PartnerOnboardingService
{
    public function __construct(private readonly B2bClientWorkflow $b2bClients) {}

    public function invite(string $email, ?string $company, ?string $unp, ?User $actor, ?CrmCounterparty $counterparty = null): PartnerOnboarding
    {
        $email = mb_strtolower(trim($email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => 'Некорректный e-mail: '.$email]);
        }

        $onboarding = PartnerOnboarding::query()->where('email', $email)->whereIn('status', ['invited', 'opened', 'submitted'])->first()
            ?? PartnerOnboarding::query()->create([
                'token' => Str::random(48),
                'email' => $email,
                'company_name' => $company,
                'unp' => $unp,
                'crm_counterparty_id' => $counterparty?->id,
                'status' => 'invited',
                'created_by' => $actor?->id,
            ]);

        $onboarding->forceFill(['invited_at' => now()])->save();
        Mail::mailer('transactional')->to($email)->queue(new PartnerOnboardingInviteMail($onboarding));

        return $onboarding;
    }

    /** Invites a CRM counterparty by its first e-mail; null when it has no e-mail or is already a B2B client. */
    public function inviteCounterparty(CrmCounterparty $counterparty, ?User $actor): ?PartnerOnboarding
    {
        $email = $counterparty->contactPoints()->where('type', 'email')->where('is_active', true)
            ->orderByDesc('is_primary')->value('value');
        if (! $email || B2bClient::query()->where('counterparty_id', $counterparty->id)->where('status', 'approved')->exists()) {
            return null;
        }

        return $this->invite($email, $counterparty->legal_name ?: $counterparty->name, $counterparty->unp, $actor, $counterparty);
    }

    public function markOpened(PartnerOnboarding $onboarding): void
    {
        if ($onboarding->status === 'invited') {
            $onboarding->forceFill(['status' => 'opened', 'opened_at' => now()])->save();
        }
    }

    /** @param array<string, mixed> $data */
    public function submit(PartnerOnboarding $onboarding, array $data): PartnerOnboarding
    {
        abort_unless($onboarding->isEditableByPartner(), 410);
        $firstTime = $onboarding->status !== 'submitted';
        $onboarding->forceFill([
            'status' => 'submitted',
            'data' => $data,
            'company_name' => $data['company_name'],
            'unp' => $data['unp'] ?? $onboarding->unp,
            'submitted_at' => now(),
        ])->save();

        if ($firstTime) {
            $this->notifyManagers($onboarding);
        }

        return $onboarding;
    }

    public function approve(PartnerOnboarding $onboarding, User $actor): B2bClient
    {
        if ($onboarding->status !== 'submitted') {
            throw ValidationException::withMessages(['status' => 'Одобрить можно только заполненную анкету.']);
        }

        $data = $onboarding->data ?? [];
        $email = mb_strtolower((string) ($data['email'] ?? $onboarding->email));
        $type = ($data['partner_type'] ?? 'wholesale') === 'installer' ? 'installer' : 'wholesale';
        $created = false;

        $client = DB::transaction(function () use ($onboarding, $data, $email, $type, $actor, &$created): B2bClient {
            $user = User::query()->where('email', $email)->first();
            if (! $user) {
                $user = User::query()->create([
                    'name' => $data['contact_name'] ?: $data['company_name'],
                    'email' => $email,
                    'password' => Str::random(40),
                    'phone' => $data['phone'] ?? null,
                    'client_type' => $type,
                    'company_name' => $data['company_name'],
                    'company_inn' => $data['unp'] ?? null,
                    'is_active' => true,
                ]);
                $created = true;
            }

            $client = $user->b2bClient ?? new B2bClient(['user_id' => $user->id]);
            if (in_array($client->status, ['paused', 'rejected'], true)) {
                throw ValidationException::withMessages(['status' => 'B2B-доступ «'.$client->company_name.'» приостановлен или отклонён — включите его в карточке B2B-клиента.']);
            }
            $client->fill([
                'status' => $client->status === 'approved' ? 'approved' : 'pending',
                'partner_type' => $type,
                'company_name' => $data['company_name'],
                'unp' => $data['unp'] ?? $client->unp,
                'counterparty_id' => $client->counterparty_id ?: $onboarding->crm_counterparty_id,
                'contact_name' => $data['contact_name'] ?? null,
                'contact_email' => $email,
                'contact_phone' => $data['phone'] ?? null,
                'application_comment' => 'Подключён по анкете партнёра #'.$onboarding->id.'.',
            ])->save();
            if ($client->status !== 'approved') {
                $client = $this->b2bClients->approve($client, $actor);
            }

            foreach ($data['points'] ?? [] as $point) {
                PartnerBranch::query()->firstOrCreate(
                    ['b2b_client_id' => $client->id, 'city_id' => (int) $point['city_id'], 'address' => $point['address'] ?? null],
                    ['name' => $point['name'] ?: $data['company_name'], 'phones' => $point['phones'],
                        'working_hours' => $point['working_hours'] ?? null, 'email' => $email, 'is_active' => true],
                );
            }

            $onboarding->forceFill([
                'status' => 'approved', 'b2b_client_id' => $client->id,
                'decided_by' => $actor->id, 'decided_at' => now(),
            ])->save();

            return $client;
        });

        $loginUrl = $created
            ? URL::temporarySignedRoute('b2b.activate', now()->addDays(14), [
                'user' => $client->user_id,
                'v' => InstallerB2bConversion::linkVersion($client->user()->firstOrFail()),
            ])
            : route('b2b.cabinet');
        Mail::mailer('transactional')->to($email)->queue(new PartnerOnboardingApprovedMail($client, $loginUrl, $created));

        return $client;
    }

    public function reject(PartnerOnboarding $onboarding, User $actor, string $reason): void
    {
        $onboarding->forceFill([
            'status' => 'rejected', 'decided_by' => $actor->id, 'decided_at' => now(), 'decision_note' => $reason,
        ])->save();
    }

    private function notifyManagers(PartnerOnboarding $onboarding): void
    {
        $managers = User::query()->where('is_active', true)
            ->where(fn ($query) => $query->where('role', 'admin')->orWhere('can_manage_b2b_orders', true))
            ->get()->filter(fn (User $user): bool => $user->canManageB2bOrders());
        if ($managers->isEmpty()) {
            return;
        }

        try {
            Notification::make()
                ->title('Анкета партнёра заполнена')
                ->body($onboarding->company_name.' — проверьте и подключите.')
                ->actions([Action::make('open')->label('Открыть анкеты')->url(PartnerOnboardingResource::getUrl('index', panel: 'admin'))->markAsRead()])
                ->sendToDatabase($managers);
        } catch (\Throwable $exception) {
            Log::error('Partner onboarding notification failed.', ['onboarding_id' => $onboarding->id, 'exception' => $exception->getMessage()]);
        }
    }
}
