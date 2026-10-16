<?php

use App\Models\CrmContactPoint;
use App\Models\Supplier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $supplier = Supplier::query()->where('code', 'mercury')->first();
        if (! $supplier) {
            return;
        }
        DB::transaction(function () use ($supplier): void {
            $phone = '+375297873892';
            $contact = preg_replace('/\+?375[\s().-]*29[\s().-]*640[\s().-]*09[\s().-]*59/u', '+375 (29) 787-38-92', (string) $supplier->contact);
            if (! str_contains(preg_replace('/\D/', '', $contact), '375297873892')) {
                $contact = '+375 (29) 787-38-92'.($contact !== '' ? ' · '.$contact : '');
            }
            $channels = $supplier->communication_channels ?? [];
            $hasPhone = false;
            foreach ($channels as &$channel) {
                if (($channel['type'] ?? null) === 'phone') {
                    $digits = preg_replace('/\D/', '', $channel['value'] ?? '');
                    if ($digits === '375296400959') {
                        $channel['value'] = $phone;
                        $hasPhone = true;
                    } elseif ($digits === '375297873892') {
                        $hasPhone = true;
                    }
                }
            }
            unset($channel);
            if (! $hasPhone) {
                array_unshift($channels, ['type' => 'phone', 'value' => $phone]);
            }
            $supplier->fill(['contact' => $contact, 'communication_channels' => $channels]);
            if ($supplier->isDirty()) {
                $supplier->save();
            }
            if ($supplier->crm_counterparty_id) {
                $points = CrmContactPoint::query()->where('counterparty_id', $supplier->crm_counterparty_id)->where('type', 'phone')->get();
                foreach ($points as $point) {
                    if (preg_replace('/\D/', '', $point->value) === '375296400959') {
                        $point->update(['value' => $phone]);
                    }
                }
                $companyPhones = CrmContactPoint::query()->where('counterparty_id', $supplier->crm_counterparty_id)->whereNull('contact_id')->where('type', 'phone');
                $companyPhones->update(['is_primary' => false]);
                CrmContactPoint::query()->updateOrCreate([
                    'counterparty_id' => $supplier->crm_counterparty_id, 'contact_id' => null,
                    'type' => 'phone', 'value' => $phone,
                ], ['is_active' => true, 'is_primary' => true]);
            }
            if (! collect($supplier->fresh()->contactPhoneNumbers())->contains(fn ($value) => preg_replace('/\D/', '', $value) === '375297873892')) {
                throw new RuntimeException('Mercury supplier phone verification failed.');
            }
        });
    }

    public function down(): void
    {
        // Keep the user-confirmed contact; rolling back code must not restore the wrong number.
    }
};
