<?php

namespace App\Services\Crm;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BelarusCompanyLookup
{
    public function isAvailable(): bool
    {
        return trim((string) config('services.belarus_company_registry.username')) !== ''
            && trim((string) config('services.belarus_company_registry.password')) !== '';
    }

    /** @return array<string, mixed>|null */
    public function lookup(?string $unp): ?array
    {
        $unp = mb_strtoupper(preg_replace('/[^0-9A-ZА-Я]/ui', '', (string) $unp) ?? '');
        if (! $this->isAvailable() || ! preg_match('/^[0-9A-ZА-Я]{9}$/u', $unp)) {
            return null;
        }

        return Cache::remember('belarus-company:'.$unp, now()->addDay(), function () use ($unp): ?array {
            $response = Http::acceptJson()->timeout(8)->retry(1, 250)->get(
                (string) config('services.belarus_company_registry.url'),
                [
                    'username' => config('services.belarus_company_registry.username'),
                    'password' => md5((string) config('services.belarus_company_registry.password')),
                    'number' => $unp,
                ],
            );

            if (! $response->successful() || ! is_array($data = $response->json())) {
                return null;
            }

            $tax = is_array(data_get($data, 'tax')) ? data_get($data, 'tax') : [];
            $egrn = is_array(data_get($data, 'egrn')) ? data_get($data, 'egrn') : [];
            $contacts = is_array(data_get($data, 'contacts')) ? data_get($data, 'contacts') : [];

            return [
                'unp' => $unp,
                'company' => $this->first([$tax['shortName'] ?? null, $egrn['shortName'] ?? null]),
                'legal_name' => $this->first([$tax['longName'] ?? null, $egrn['longName'] ?? null]),
                'legal_address' => $this->first([$tax['address'] ?? null, $egrn['address'] ?? null]),
                'phones' => $this->values($contacts['phones'] ?? []),
                'emails' => $this->values($contacts['emails'] ?? []),
                'source' => 'Контрагенто',
            ];
        });
    }

    private function first(array $values): ?string
    {
        foreach ($values as $value) {
            if (($value = trim((string) $value)) !== '') {
                return $value;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function values(mixed $values): array
    {
        return collect(is_array($values) ? $values : [$values])
            ->map(fn (mixed $value): string => trim((string) (is_array($value) ? ($value['value'] ?? '') : $value)))
            ->filter()->unique()->values()->all();
    }
}
