<?php

namespace App\Services\Shipping;

use App\Models\VamExpressShipment;

class VamExpressProfiles
{
    /** The caller holds vam-express-sync, shared with normal sync and label downloads. */
    public function fill(VamExpressPortal $portal, int $limit = 10): int
    {
        $shipments = VamExpressShipment::query()
            ->where(fn ($query) => $query->whereNull('profile_checked_at')->orWhere('profile_checked_at', '<=', now()->subDay()))
            ->orderByRaw('CASE WHEN sender_profile IS NULL THEN 0 ELSE 1 END')
            ->orderBy('profile_checked_at')->orderByDesc('remote_created_at')->orderBy('id')
            ->limit(min(100, max(1, $limit)))->get();
        $count = 0;
        foreach ($shipments as $shipment) {
            try {
                $name = $portal->senderProfile($shipment->remote_id);
                $shipment->update(['sender_profile' => $name, 'profile_checked_at' => now()]);
                $count++;
            } catch (VamExpressException $e) {
                $shipment->update(['profile_checked_at' => now()]);
                throw $e;
            }
        }

        return $count;
    }
}
