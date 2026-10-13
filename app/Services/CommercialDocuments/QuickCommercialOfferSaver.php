<?php

namespace App\Services\CommercialDocuments;

use App\Models\CrmContact;
use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\CrmMailThread;
use App\Models\Product;
use App\Services\Telephony\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QuickCommercialOfferSaver
{
    public function saveClient(CrmMailThread $thread, array $data, array $analysis): CrmCounterparty
    {
        return DB::transaction(function () use ($thread, $data, $analysis): CrmCounterparty {
            $unp = trim((string) ($data['client_unp'] ?? $analysis['unp'] ?? ''));
            $counterparty = ($unp !== '' ? CrmCounterparty::query()->where('unp', $unp)->first() : null)
                ?? $thread->counterparty
                ?? $this->counterpartyByEmail((string) ($data['client_email'] ?? ''))
                ?? new CrmCounterparty;

            $name = trim((string) ($data['client_company'] ?? $analysis['company'] ?? ''));
            $counterparty->fill(array_filter([
                'type' => 'organization',
                'classification' => 'b2b_prospect',
                'business_unit' => 'wholesale',
                'status' => $counterparty->exists ? $counterparty->status : 'lead',
                'name' => $name,
                'legal_name' => $analysis['legal_name'] ?? $name,
                'unp' => $unp ?: null,
                'legal_address' => $data['client_address'] ?? $analysis['legal_address'] ?? null,
                'responsible_user_id' => $thread->assigned_user_id,
                'source' => 'email',
                'last_contact_at' => now(),
            ], fn (mixed $value): bool => $value !== null && $value !== ''));
            $counterparty->save();

            $contactName = trim((string) ($data['client_name'] ?? $analysis['contact_name'] ?? $thread->participant_name));
            $contact = $thread->contact;
            if ($contactName !== '') {
                $contact ??= CrmContact::query()->firstOrNew([
                    'counterparty_id' => $counterparty->id,
                    'full_name' => $contactName,
                ]);
                $contact->fill([
                    'counterparty_id' => $counterparty->id,
                    'full_name' => $contactName,
                    'position' => $analysis['contact_position'] ?? $contact->position,
                    'is_primary' => true,
                    'is_active' => true,
                ])->save();
            }

            $emails = array_merge([$data['client_email'] ?? null], $analysis['emails'] ?? []);
            $phones = array_merge([$data['client_phone'] ?? null], $analysis['phones'] ?? []);
            foreach (['email' => $emails, 'phone' => $phones] as $type => $values) {
                foreach (collect($values)->filter()->unique() as $index => $value) {
                    $normalized = $type === 'phone' ? PhoneNumber::normalize((string) $value) : mb_strtolower(trim((string) $value));
                    if (! $normalized || ($type === 'email' && ! filter_var($normalized, FILTER_VALIDATE_EMAIL))) {
                        continue;
                    }
                    CrmContactPoint::query()->updateOrCreate(
                        ['counterparty_id' => $counterparty->id, 'type' => $type, 'normalized_value' => $normalized],
                        [
                            'contact_id' => $contact?->id,
                            'value' => trim((string) $value),
                            'label' => $type === 'email' ? 'Рабочий' : 'Рабочий телефон',
                            'is_primary' => $index === 0,
                            'is_active' => true,
                        ],
                    );
                }
            }

            $thread->forceFill([
                'crm_counterparty_id' => $counterparty->id,
                'crm_contact_id' => $contact?->id,
            ])->save();

            return $counterparty;
        });
    }

    /** @return array<int, float> product id => quantity */
    public function createDraftProducts(array $items, int $categoryId): array
    {
        return DB::transaction(function () use ($items, $categoryId): array {
            $created = [];
            foreach ($items as $item) {
                $name = trim((string) ($item['query'] ?? ''));
                $sku = trim((string) ($item['sku'] ?? '')) ?: null;
                if ($name === '' && $sku === null) {
                    continue;
                }
                $product = ($sku ? Product::query()->where('sku', $sku)->first() : null)
                    ?? Product::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
                if (! $product) {
                    $baseSlug = Str::slug($name ?: $sku) ?: 'draft-product';
                    $slug = $baseSlug;
                    for ($suffix = 2; Product::query()->where('slug', $slug)->exists(); $suffix++) {
                        $slug = $baseSlug.'-'.$suffix;
                    }
                    $product = Product::query()->create([
                        'category_id' => $categoryId,
                        'name' => $name ?: $sku,
                        'slug' => $slug,
                        'sku' => $sku,
                        'price' => 0,
                        'currency' => 'BYN',
                        'short_description' => 'Черновик создан из входящего письма. Требует проверки менеджером.',
                        'is_active' => false,
                        'is_archived' => false,
                        'in_stock' => false,
                        'availability_status' => Product::AVAILABILITY_CHECK,
                    ]);
                }
                $created[$product->id] = (float) ($item['quantity'] ?? 1);
            }

            return $created;
        });
    }

    private function counterpartyByEmail(string $email): ?CrmCounterparty
    {
        $email = mb_strtolower(trim($email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return CrmContactPoint::query()->where('type', 'email')->where('normalized_value', $email)
            ->with('counterparty')->first()?->counterparty;
    }
}
