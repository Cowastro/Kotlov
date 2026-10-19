<?php

use App\Models\CrmContactPoint;
use App\Models\Supplier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PHONE = '+375447245570';

    public function up(): void
    {
        $supplier = Supplier::query()->where('code', 'bania')->first();
        if (! $supplier) {
            return;
        }

        DB::transaction(function () use ($supplier): void {
            $channels = collect($supplier->communication_channels ?? [])
                ->reject(fn (array $channel): bool => $this->samePhone($channel['value'] ?? null))
                ->values()
                ->all();

            $channels[] = [
                'type' => 'viber',
                'name' => 'Менеджер Банька',
                'value' => self::PHONE,
                'note' => 'По наличию и заказам',
            ];

            $contact = trim((string) $supplier->contact);
            if (! $this->samePhone($contact)) {
                $contact = '+375 (44) 724-55-70'.($contact !== '' ? ' · '.$contact : '');
            }

            $supplier->update([
                'contact' => $contact,
                'communication_channels' => $channels,
            ]);

            if ($supplier->crm_counterparty_id) {
                CrmContactPoint::query()->updateOrCreate([
                    'counterparty_id' => $supplier->crm_counterparty_id,
                    'contact_id' => null,
                    'type' => 'phone',
                    'normalized_value' => '375447245570',
                ], [
                    'value' => self::PHONE,
                    'label' => 'Менеджер Банька',
                    'is_primary' => true,
                    'is_active' => true,
                ]);
            }

            if (! collect($supplier->fresh()->contactPhoneNumbers())->contains(
                fn (string $phone): bool => $this->samePhone($phone)
            )) {
                throw new RuntimeException('BANIA manager phone verification failed.');
            }
        });
    }

    public function down(): void
    {
        // Keep the user-confirmed supplier contact on rollback.
    }

    private function samePhone(mixed $value): bool
    {
        return preg_replace('/\D/', '', (string) $value) === '375447245570';
    }
};
