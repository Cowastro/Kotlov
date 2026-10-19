<?php

use App\Models\CrmContactPoint;
use App\Models\Supplier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PHONE = '+375295155377';

    public function up(): void
    {
        $supplier = Supplier::query()->where('code', 'santehsklad')->first();
        if (! $supplier) {
            return;
        }

        DB::transaction(function () use ($supplier): void {
            $channels = collect($supplier->communication_channels ?? [])
                ->filter(fn ($channel): bool => is_array($channel))
                ->reject(fn (array $channel): bool => $this->samePhone($channel['value'] ?? null))
                ->values()
                ->all();

            $channels[] = [
                'type' => 'phone',
                'name' => 'Приём заявок',
                'value' => self::PHONE,
                'note' => 'Сантехсклад — заказы и наличие',
            ];

            $contact = trim((string) $supplier->contact);
            if (! $this->containsPhone($contact)) {
                $ordersPhone = '+375 (29) 515-53-77 — приём заявок';
                $contact = $contact !== '' ? $contact.' · '.$ordersPhone : $ordersPhone;
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
                    'normalized_value' => '375295155377',
                ], [
                    'value' => self::PHONE,
                    'label' => 'Сантехсклад — приём заявок',
                    'is_primary' => true,
                    'is_active' => true,
                ]);
            }

            if (! collect($supplier->fresh()->contactPhoneNumbers())->contains(
                fn (string $phone): bool => $this->samePhone($phone)
            )) {
                throw new RuntimeException('Santehsklad orders phone verification failed.');
            }
        });
    }

    public function down(): void
    {
        // Keep the user-confirmed supplier contact on rollback.
    }

    private function samePhone(mixed $value): bool
    {
        return preg_replace('/\D/', '', (string) $value) === '375295155377';
    }

    private function containsPhone(string $value): bool
    {
        return preg_match('/375\D*29\D*515\D*53\D*77/u', $value) === 1;
    }
};
