<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            // Exact CRM IDs only. Never guess by company name or email, and never
            // choose between conflicting numbers or overwrite known requisites.
            DB::table('crm_counterparties')->where(function ($query): void {
                $query->whereNull('unp')->orWhereRaw("TRIM(unp) = ''");
            })->orderBy('id')->get(['id'])->each(function ($counterparty): void {
                $numbers = DB::table('commercial_documents')
                    ->where('crm_counterparty_id', $counterparty->id)->whereNotNull('client_unp')
                    ->pluck('client_unp')->map(fn ($value) => trim((string) $value))->filter()->unique()->values();
                if ($numbers->count() !== 1) {
                    return;
                }
                $unp = $numbers->first();
                if (DB::table('crm_counterparties')->where('unp', $unp)->where('id', '!=', $counterparty->id)->exists()) {
                    return;
                }
                DB::table('crm_counterparties')->where('id', $counterparty->id)->where(function ($query): void {
                    $query->whereNull('unp')->orWhereRaw("TRIM(unp) = ''");
                })->update(['unp' => $unp, 'updated_at' => now()]);
            });
        });
    }

    public function down(): void
    {
        // Recovered customer requisites must not disappear on a code rollback.
    }
};
