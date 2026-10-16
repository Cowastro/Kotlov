<?php

namespace App\Services\CommercialDocuments;

use App\Models\CommercialDocument;
use App\Models\CrmContact;
use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Services\Telephony\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommercialDocumentClientResolver
{
    public function sync(CommercialDocument $document): ?CrmCounterparty
    {
        if (! $this->hasClientIdentity($document)) {
            return null;
        }

        return DB::transaction(function () use ($document): CrmCounterparty {
            $counterparty = $this->findCounterparty($document) ?? new CrmCounterparty;
            $this->fillCounterparty($counterparty, $document);
            $counterparty->save();

            $selectedContact = $document->crm_contact_id
                ? CrmContact::query()
                    ->where('counterparty_id', $counterparty->id)
                    ->with('contactPoints')
                    ->find($document->crm_contact_id)
                : null;
            $contact = $selectedContact ?? $this->findOrCreateContact($counterparty, $document);

            if ($selectedContact) {
                $this->fillDocumentFromCrm($document, $counterparty, $selectedContact);
            } else {
                $this->syncContactPoint($counterparty, $contact, 'email', $document->client_email);
                $this->syncContactPoint($counterparty, $contact, 'phone', $document->client_phone);
            }

            $document->forceFill([
                'crm_counterparty_id' => $counterparty->id,
                'crm_contact_id' => $contact?->id,
                'client_unp' => $counterparty->unp,
            ])->saveQuietly();

            return $counterparty;
        });
    }

    private function fillDocumentFromCrm(
        CommercialDocument $document,
        CrmCounterparty $counterparty,
        CrmContact $contact,
    ): void {
        $points = $contact->contactPoints
            ->where('is_active', true)
            ->sortByDesc('is_primary');

        $document->forceFill([
            'client_name' => $contact->full_name,
            'client_company' => $counterparty->type === 'organization'
                ? ($counterparty->public_name ?: $counterparty->name)
                : null,
            'client_legal_name' => $counterparty->type === 'organization'
                ? ($counterparty->legal_name ?: $counterparty->name)
                : null,
            'client_email' => $points->firstWhere('type', 'email')?->value,
            'client_phone' => $points->firstWhere('type', 'phone')?->value,
            'client_unp' => $counterparty->unp,
            'client_address' => $counterparty->legal_address
                ?: ($counterparty->actual_address ?: $counterparty->postal_address),
        ]);
    }

    private function hasClientIdentity(CommercialDocument $document): bool
    {
        return collect([
            $document->crm_counterparty_id,
            $document->client_company,
            $document->client_name,
            $document->client_email,
            $document->client_phone,
            $document->client_unp,
        ])->contains(fn (mixed $value): bool => trim((string) $value) !== '');
    }

    private function findCounterparty(CommercialDocument $document): ?CrmCounterparty
    {
        if ($document->crm_counterparty_id) {
            $selected = CrmCounterparty::query()->find($document->crm_counterparty_id);
            if ($selected) {
                return $selected;
            }
        }

        $unp = trim((string) $document->client_unp);
        if ($unp !== '') {
            $byUnp = CrmCounterparty::query()->where('unp', $unp)->first();
            if ($byUnp) {
                return $byUnp;
            }
        }

        $email = mb_strtolower(trim((string) $document->client_email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $byEmail = $this->counterpartyByContactPoint('email', $email);
            if ($byEmail) {
                return $byEmail;
            }
        }

        $phone = PhoneNumber::normalize($document->client_phone);
        if ($phone) {
            $byPhone = $this->counterpartyByContactPoint('phone', $phone);
            if ($byPhone) {
                return $byPhone;
            }
        }

        $company = trim((string) ($document->client_company ?: $document->client_legal_name));
        if ($company === '') {
            return null;
        }

        $normalizedCompany = mb_strtolower($company);

        return CrmCounterparty::query()
            ->where(function ($query) use ($normalizedCompany): void {
                $query->whereRaw('LOWER(name) = ?', [$normalizedCompany])
                    ->orWhereRaw('LOWER(public_name) = ?', [$normalizedCompany])
                    ->orWhereRaw('LOWER(legal_name) = ?', [$normalizedCompany]);
            })
            ->first();
    }

    private function counterpartyByContactPoint(string $type, string $normalizedValue): ?CrmCounterparty
    {
        return CrmContactPoint::query()
            ->where('type', $type)
            ->where('normalized_value', $normalizedValue)
            ->whereNotNull('counterparty_id')
            ->with('counterparty')
            ->first()?->counterparty;
    }

    private function fillCounterparty(CrmCounterparty $counterparty, CommercialDocument $document): void
    {
        $company = trim((string) $document->client_company);
        $legalName = trim((string) $document->client_legal_name);
        $contactName = trim((string) $document->client_name);
        $fallbackName = trim((string) ($document->client_email ?: $document->client_phone));
        $unp = trim((string) $document->client_unp);
        $name = $company ?: $contactName ?: ($fallbackName ?: 'Контрагент '.$unp);

        if (! $counterparty->exists) {
            $counterparty->fill([
                'type' => ($company !== '' || $unp !== '') ? 'organization' : 'person',
                'classification' => 'b2b_prospect',
                'business_unit' => 'wholesale',
                'status' => 'lead',
                'name' => $name,
                'public_name' => $company ?: $name,
                'legal_name' => $legalName ?: ($company ?: null),
                'source' => 'manual',
            ]);
        }

        // A non-empty number entered in the quote updates the directory.
        // An empty quote field must never erase a known taxpayer number.
        if ($unp !== '') {
            if ($counterparty->exists && $counterparty->unp !== $unp
                && CrmCounterparty::query()->where('unp', $unp)->whereKeyNot($counterparty->id)->exists()) {
                throw ValidationException::withMessages(['client_unp' => 'Этот УНП уже записан у другого контрагента. Выберите его из справочника.']);
            }
            $counterparty->unp = $unp;
        }

        foreach ([
            'legal_name' => $legalName,
            'public_name' => $company,
            'legal_address' => trim((string) $document->client_address),
            'responsible_user_id' => $document->manager_id,
        ] as $attribute => $value) {
            if (blank($counterparty->{$attribute}) && filled($value)) {
                $counterparty->{$attribute} = $value;
            }
        }

        $counterparty->last_contact_at = now();
    }

    private function findOrCreateContact(CrmCounterparty $counterparty, CommercialDocument $document): ?CrmContact
    {
        $contactName = trim((string) $document->client_name);
        $contact = $document->crm_contact_id
            ? CrmContact::query()->where('counterparty_id', $counterparty->id)->find($document->crm_contact_id)
            : null;

        foreach ([
            'email' => mb_strtolower(trim((string) $document->client_email)),
            'phone' => PhoneNumber::normalize($document->client_phone),
        ] as $type => $normalizedValue) {
            if ($contact || ! $normalizedValue) {
                continue;
            }

            $contact = CrmContactPoint::query()
                ->where('counterparty_id', $counterparty->id)
                ->where('type', $type)
                ->where('normalized_value', $normalizedValue)
                ->with('contact')
                ->first()?->contact;
        }

        if (! $contact && $contactName !== '') {
            $contact = CrmContact::query()->firstOrNew([
                'counterparty_id' => $counterparty->id,
                'full_name' => $contactName,
            ]);
        }

        if (! $contact && $counterparty->type === 'person') {
            $contact = CrmContact::query()->firstOrNew([
                'counterparty_id' => $counterparty->id,
                'full_name' => $counterparty->name,
            ]);
        }

        if ($contact) {
            $contact->fill([
                'counterparty_id' => $counterparty->id,
                'full_name' => $contactName ?: $contact->full_name,
                'is_primary' => $contact->exists ? $contact->is_primary : true,
                'is_active' => true,
            ])->save();
        }

        return $contact;
    }

    private function syncContactPoint(
        CrmCounterparty $counterparty,
        ?CrmContact $contact,
        string $type,
        ?string $value,
    ): void {
        $value = trim((string) $value);
        $normalizedValue = $type === 'phone' ? PhoneNumber::normalize($value) : mb_strtolower($value);

        if (! $normalizedValue || ($type === 'email' && ! filter_var($normalizedValue, FILTER_VALIDATE_EMAIL))) {
            return;
        }

        CrmContactPoint::query()->updateOrCreate(
            [
                'counterparty_id' => $counterparty->id,
                'type' => $type,
                'normalized_value' => $normalizedValue,
            ],
            [
                'contact_id' => $contact?->id,
                'value' => $value,
                'label' => $type === 'email' ? 'Рабочий' : 'Рабочий телефон',
                'is_primary' => true,
                'is_active' => true,
            ],
        );
    }
}
