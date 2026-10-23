<?php

namespace App\Services\Pricing;

use DateTimeImmutable;
use InvalidArgumentException;

class KarinaPricing
{
    public const RATE_URL = 'https://api.nbrb.by/exrates/rates/RUB?parammode=2';

    public static function rateEvidence(array $response, string $today): array
    {
        $official = (float) ($response['Cur_OfficialRate'] ?? 0);
        $scale = (int) ($response['Cur_Scale'] ?? 0);
        $date = substr((string) ($response['Date'] ?? ''), 0, 10);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $now = DateTimeImmutable::createFromFormat('!Y-m-d', $today);
        if (($response['Cur_Abbreviation'] ?? '') !== 'RUB'
            || ! is_finite($official) || $official <= 0 || $scale <= 0
            || ! $parsed || ! $now || $parsed->format('Y-m-d') !== $date
            || $parsed > $now || $parsed < $now->modify('-3 days')) {
            throw new InvalidArgumentException('Invalid or stale NBRB RUB rate; KARINA price plan refused.');
        }
        $rate = round($official / $scale, 6);
        if ($rate <= 0 || $rate >= 1) {
            throw new InvalidArgumentException('Implausible BYN per RUB rate; KARINA price plan refused.');
        }

        return [
            'source' => self::RATE_URL, 'date' => $date, 'official_rate' => $official,
            'scale' => $scale, 'byn_per_rub' => $rate,
        ];
    }

    public static function quote(float $retailRub, float $rate): array
    {
        if (! is_finite($retailRub) || $retailRub <= 0 || ! is_finite($rate) || $rate <= 0 || $rate >= 1) {
            throw new InvalidArgumentException('Positive RUB retail price and valid RUB/BYN rate required.');
        }
        $purchaseRub = round($retailRub * 0.75, 2);

        return [
            'retail_rub' => $retailRub, 'purchase_rub' => $purchaseRub,
            'retail_byn' => CurrencyPriceConverter::convertToByn($retailRub, 'RUB', $rate),
            'purchase_byn' => CurrencyPriceConverter::convertToByn($purchaseRub, 'RUB', $rate),
        ];
    }
}
