<?php

namespace App\Services\CrmMail;

use App\Models\CrmContact;
use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use Illuminate\Support\Facades\DB;

class LeadMatcher
{
    /** @return array{0: CrmCounterparty|null, 1: CrmContact|null} */
    public function resolve(string $email, ?string $name, bool $createLead): array
    {
        $normalized = mb_strtolower(trim($email));
        $point = CrmContactPoint::query()
            ->with(['counterparty', 'contact.counterparty'])
            ->where('type', 'email')
            ->where('normalized_value', $normalized)
            ->where('is_active', true)
            ->first();

        if ($point) {
            return [$point->counterparty ?? $point->contact?->counterparty, $point->contact];
        }

        if (! $createLead) {
            return [null, null];
        }

        return DB::transaction(function () use ($normalized, $name): array {
            $existing = CrmContactPoint::query()
                ->with(['counterparty', 'contact.counterparty'])
                ->where('type', 'email')
                ->where('normalized_value', $normalized)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return [$existing->counterparty ?? $existing->contact?->counterparty, $existing->contact];
            }

            $displayName = filled($name) ? trim((string) $name) : $normalized;
            $counterparty = CrmCounterparty::create([
                'type' => 'person',
                'classification' => 'unclassified',
                'business_unit' => 'retail',
                'status' => 'lead',
                'name' => $displayName,
                'source' => 'email',
                'last_contact_at' => now(),
            ]);
            $contact = CrmContact::create([
                'counterparty_id' => $counterparty->id,
                'full_name' => $displayName,
                'is_primary' => true,
                'is_active' => true,
            ]);
            CrmContactPoint::create([
                'counterparty_id' => $counterparty->id,
                'contact_id' => $contact->id,
                'type' => 'email',
                'value' => $normalized,
                'label' => 'Входящая почта',
                'is_primary' => true,
                'is_active' => true,
            ]);

            return [$counterparty, $contact];
        });
    }
}
