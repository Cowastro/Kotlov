<?php

namespace App\Services\Crm;

use App\Models\CrmCounterparty;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class BelarusTaxpayerRegistry
{
    /** @return array<string, mixed>|null */
    public function lookup(string $unp): ?array
    {
        $unp = preg_replace('/\D+/', '', $unp) ?: '';
        if (! preg_match('/^\d{9}$/', $unp)) {
            return null;
        }

        $local = CrmCounterparty::query()
            ->with(['contactPoints' => fn ($query) => $query->where('is_active', true)])
            ->where('unp', $unp)
            ->first();

        if ($local) {
            return [
                'source' => 'CRM / 1С',
                'crm_counterparty_id' => $local->id,
                'unp' => $local->unp,
                'legal_name' => $local->legal_name ?: $local->name,
                'short_name' => $local->public_name ?: $local->name,
                'address' => $local->legal_address ?: ($local->actual_address ?: $local->postal_address),
                'email' => $local->contactPoints->firstWhere('type', 'email')?->value,
                'phone' => $local->contactPoints->firstWhere('type', 'phone')?->value,
                'status' => CrmCounterparty::STATUSES[$local->status] ?? $local->status,
            ];
        }

        try {
            $response = Http::acceptJson()
                ->timeout(8)
                ->retry(2, 250)
                ->get((string) config('services.belarus_taxpayer_registry.url'), [
                    'unp' => $unp,
                    'charset' => 'UTF-8',
                    'type' => 'json',
                ]);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $row = $response->json('row') ?? $response->json('ROW');
        if (! is_array($row) || trim((string) ($row['vunp'] ?? $row['VUNP'] ?? '')) !== $unp) {
            return null;
        }

        return [
            'source' => 'Реестр МНС Республики Беларусь',
            'crm_counterparty_id' => null,
            'unp' => $unp,
            'legal_name' => $this->value($row, 'vnaimp'),
            'short_name' => $this->value($row, 'vnaimk') ?: $this->value($row, 'vnaimp'),
            'address' => $this->value($row, 'vpadres'),
            'email' => null,
            'phone' => null,
            'status' => $this->value($row, 'vkods'),
            'registered_at' => $this->value($row, 'dreg'),
            'tax_office' => $this->value($row, 'vmns'),
        ];
    }

    /** @param array<string, mixed> $row */
    private function value(array $row, string $key): ?string
    {
        $value = trim((string) ($row[$key] ?? $row[strtoupper($key)] ?? ''));

        return $value !== '' ? $value : null;
    }
}
