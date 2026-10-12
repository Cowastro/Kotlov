<?php

namespace App\Services\Crm;

use App\Models\CrmContactPoint;
use App\Models\TelephonyCall;
use App\Services\Telephony\PhoneNumber;

class CrmContactMatcher
{
    public function attachToCall(TelephonyCall $call): bool
    {
        $phone = PhoneNumber::normalize($call->customer_phone);

        if (! $phone) {
            return false;
        }

        $point = CrmContactPoint::query()
            ->with('contact.counterparty')
            ->where('type', 'phone')
            ->where('normalized_value', $phone)
            ->where('is_active', true)
            ->whereHas('contact', fn ($query) => $query->where('is_active', true))
            ->orderByDesc('is_primary')
            ->latest('id')
            ->first();

        if (! $point) {
            return false;
        }

        $call->crm_contact_id = $point->contact_id;
        $call->crm_counterparty_id = $point->contact->counterparty_id;

        return true;
    }
}
