<?php

use App\Models\CrmContactPoint;
use App\Models\Supplier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PHONE = '+375445920920';

    public function up(): void
    {
        $supplier = Supplier::query()->where('code', 'tm-management')->first();
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
                'name' => 'Андрей Логацкий',
                'value' => self::PHONE,
                'note' => 'Специалист насосного отдела ТМ Менеджмент',
            ];

            $contact = trim((string) $supplier->contact);
            if (! $this->containsPhone($contact)) {
                $manager = '+375 (44) 592-09-20 — Андрей Логацкий, специалист насосного отдела';
                $contact = $contact !== '' ? $contact.' · '.$manager : $manager;
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
                    'normalized_value' => '375445920920',
                ], [
                    'value' => self::PHONE,
                    'label' => 'Андрей Логацкий — насосный отдел ТМ',
                    'is_primary' => false,
                    'is_active' => true,
                ]);
            }

            if (! collect($supplier->fresh()->contactPhoneNumbers())->contains(
                fn (string $phone): bool => $this->samePhone($phone)
            )) {
                throw new RuntimeException('TM Management pump manager phone verification failed.');
            }
        });
    }

    public function down(): void
    {
        // Keep the user-confirmed supplier contact on rollback.
    }

    private function samePhone(mixed $value): bool
    {
        return preg_replace('/\D/', '', (string) $value) === '375445920920';
    }

    private function containsPhone(string $value): bool
    {
        return preg_match('/375\D*44\D*592\D*09\D*20/u', $value) === 1;
    }
};
