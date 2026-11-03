<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PartnerFunnelReport
{
    public function rows(string $from, string $until): Collection
    {
        $installerLeads = DB::table('installer_applications as a')
            ->leftJoin('installer_profiles as p', 'p.id', '=', 'a.installer_profile_id')
            ->leftJoin('b2b_clients as c', 'c.user_id', '=', 'p.user_id')
            ->selectRaw("'installer' as partner_type, COALESCE(a.utm_source, a.ref, c.utm_source, c.ref) as source")
            ->addSelect('a.created_at', 'c.id as client_id', 'c.status');

        $companyLeads = DB::table('b2b_clients as c')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('installer_applications as a')
                    ->join('installer_profiles as p', 'p.id', '=', 'a.installer_profile_id')
                    ->whereColumn('p.user_id', 'c.user_id');
            })
            ->selectRaw('c.partner_type, COALESCE(c.utm_source, c.ref) as source')
            ->addSelect('c.created_at', 'c.id as client_id', 'c.status');

        $payments = DB::table('b2b_orders')->where('payment_status', 'paid')
            ->where('status', '!=', 'cancelled')->whereNotNull('paid_at')
            ->selectRaw('b2b_client_id, MIN(paid_at) as first_paid_at')->groupBy('b2b_client_id');

        return DB::query()->fromSub($companyLeads->unionAll($installerLeads), 'leads')
            ->leftJoinSub($payments, 'payments', 'payments.b2b_client_id', '=', 'leads.client_id')
            ->where('leads.created_at', '>=', $from)->where('leads.created_at', '<', $until)
            ->select('leads.partner_type', 'leads.source')
            ->selectRaw('COUNT(*) as applications')
            ->selectRaw("SUM(CASE WHEN leads.status = 'approved' THEN 1 ELSE 0 END) as approved")
            ->selectRaw("SUM(CASE WHEN leads.status = 'approved' AND payments.first_paid_at >= leads.created_at AND payments.first_paid_at < ? THEN 1 ELSE 0 END) as paid", [$until])
            ->groupBy('leads.partner_type', 'leads.source')
            ->orderBy('leads.partner_type')->orderBy('leads.source')->get();
    }
}
