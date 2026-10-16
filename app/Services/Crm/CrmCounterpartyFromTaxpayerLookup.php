<?php

namespace App\Services\Crm;

use App\Models\CrmContact;
use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Services\Telephony\PhoneNumber;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CrmCounterpartyFromTaxpayerLookup
{
    /** @return array{counterparty: CrmCounterparty, contact: ?CrmContact, created: bool} */
    public function upsert(array $data, ?int $responsibleUserId = null, ?string $contactName = null): array
    {
        $unp = preg_replace('/\D+/', '', (string) ($data['unp'] ?? '')) ?: '';
        if (! preg_match('/^\d{9}$/', $unp)) {
            throw new InvalidArgumentException('Для сохранения контрагента укажите корректный УНП из 9 цифр.');
        }

        return DB::transaction(function () use ($data, $responsibleUserId, $contactName, $unp): array {
            $counterparty = CrmCounterparty::query()->where('unp', $unp)->lockForUpdate()->first()
                ?? new CrmCounterparty;
            $created = ! $counterparty->exists;
            $shortName = trim((string) ($data['short_name'] ?? ''));
            $legalName = trim((string) ($data['legal_name'] ?? ''));
            $name = $shortName ?: ($legalName ?: 'Контрагент '.$unp);

            if ($created) {
                $counterparty->fill([
                    'type' => 'organization',
                    'classification' => 'b2b_prospect',
                    'business_unit' => 'wholesale',
                    'status' => 'lead',
                    'source' => 'tax_registry',
                ]);
            }

            $counterparty->fill([
                'name' => $name,
                'public_name' => $shortName ?: $name,
                'legal_name' => $legalName ?: $name,
                'unp' => $unp,
                'legal_address' => $this->value($data['address'] ?? null),
                'registered_at' => $this->date($data['registered_at'] ?? null) ?: $counterparty->registered_at,
                'responsible_user_id' => $counterparty->responsible_user_id ?: $responsibleUserId,
                'last_contact_at' => now(),
            ])->save();

            $contact = $this->contact($counterparty, $contactName, $data['email'] ?? null, $data['phone'] ?? null);
            $this->syncPoint($counterparty, $contact, 'email', $data['email'] ?? null);
            $this->syncPoint($counterparty, $contact, 'phone', $data['phone'] ?? null);

            return compact('counterparty', 'contact', 'created');
        });
    }

    private function contact(CrmCounterparty $counterparty, ?string $name, mixed $email, mixed $phone): ?CrmContact
    {
        foreach ([
            'email' => mb_strtolower(trim((string) $email)),
            'phone' => PhoneNumber::normalize((string) $phone),
        ] as $type => $normalized) {
            if (! $normalized) {
                continue;
            }
            $contact = CrmContactPoint::query()
                ->where('counterparty_id', $counterparty->id)
                ->where('type', $type)
                ->where('normalized_value', $normalized)
                ->with('contact')
                ->first()?->contact;
            if ($contact) {
                return $contact;
            }
        }

        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        $contact = CrmContact::query()->firstOrNew([
            'counterparty_id' => $counterparty->id,
            'full_name' => $name,
        ]);
        $contact->fill([
            'is_primary' => $contact->exists ? $contact->is_primary : ! $counterparty->contacts()->exists(),
            'is_active' => true,
        ])->save();

        return $contact;
    }

    private function syncPoint(CrmCounterparty $counterparty, ?CrmContact $contact, string $type, mixed $value): void
    {
        $value = trim((string) $value);
        $normalized = $type === 'phone' ? PhoneNumber::normalize($value) : mb_strtolower($value);
        if (! $normalized || ($type === 'email' && ! filter_var($normalized, FILTER_VALIDATE_EMAIL))) {
            return;
        }

        CrmContactPoint::query()->updateOrCreate(
            [
                'counterparty_id' => $counterparty->id,
                'type' => $type,
                'normalized_value' => $normalized,
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

    private function value(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function date(mixed $value): ?Carbon
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
