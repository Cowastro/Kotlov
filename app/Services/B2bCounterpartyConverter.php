<?php

namespace App\Services;

use App\Enums\ClientType;
use App\Models\B2bClient;
use App\Models\CrmContact;
use App\Models\CrmCounterparty;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class B2bCounterpartyConverter
{
    public function suggestedEmail(CrmCounterparty $counterparty): ?string
    {
        $contact = $this->primaryContact($counterparty);

        return $this->firstContactPoint($contact, $counterparty, 'email')
            ?? $contact?->user?->email;
    }

    public function convert(CrmCounterparty $counterparty, string $email): B2bClient
    {
        $email = mb_strtolower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw ValidationException::withMessages(['email' => 'Укажите корректный email для входа партнёра.']);
        }

        return DB::transaction(function () use ($counterparty, $email): B2bClient {
            $counterparty->loadMissing(['contacts.contactPoints', 'contacts.user', 'contactPoints', 'b2bClient']);
            $contact = $this->primaryContact($counterparty);
            $contactName = $contact?->full_name ?: $counterparty->name;
            $phone = $this->firstContactPoint($contact, $counterparty, 'phone');

            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
            if (! $user) {
                $user = User::query()->create([
                    'name' => $contactName,
                    'email' => $email,
                    'phone' => $phone,
                    'password' => Str::password(32),
                    'role' => 'client',
                    'client_type' => ClientType::Wholesale,
                    'is_active' => true,
                    'b2b_approved' => false,
                ]);
            }

            $profile = B2bClient::query()
                ->where('counterparty_id', $counterparty->id)
                ->orWhere('user_id', $user->id)
                ->first();

            if ($profile && $profile->counterparty_id && $profile->counterparty_id !== $counterparty->id) {
                throw ValidationException::withMessages([
                    'email' => 'Этот аккаунт уже связан с другим CRM-контрагентом.',
                ]);
            }

            $profile ??= new B2bClient(['status' => 'pending', 'partner_type' => 'wholesale']);
            $profile->fill([
                'user_id' => $user->id,
                'counterparty_id' => $counterparty->id,
                'company_name' => $counterparty->public_name
                    ?: ($counterparty->legal_name ?: $counterparty->name),
                'unp' => $counterparty->unp,
                'contact_name' => $contactName,
                'contact_email' => $email,
                'contact_phone' => $phone,
                'responsible_user_id' => $counterparty->responsible_user_id,
                'payment_terms' => $counterparty->commercial_payment_terms,
                'delivery_terms' => $counterparty->commercial_delivery_terms,
            ])->save();

            $counterparty->forceFill([
                'classification' => 'b2b_partner',
                'business_unit' => 'wholesale',
                'status' => 'active',
            ])->save();

            if ($contact && ! $contact->user_id) {
                $contact->forceFill(['user_id' => $user->id])->save();
            }

            return $profile->fresh(['user', 'counterparty']);
        });
    }

    private function primaryContact(CrmCounterparty $counterparty): ?CrmContact
    {
        $counterparty->loadMissing(['contacts.contactPoints', 'contacts.user', 'contactPoints']);

        return $counterparty->contacts->firstWhere('is_primary', true)
            ?? $counterparty->contacts->first();
    }

    private function firstContactPoint(?CrmContact $contact, CrmCounterparty $counterparty, string $type): ?string
    {
        $value = $contact?->contactPoints->firstWhere('type', $type)?->value
            ?? $counterparty->contactPoints->firstWhere('type', $type)?->value;

        return filled($value) ? trim((string) $value) : null;
    }
}
