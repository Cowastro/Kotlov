<?php

namespace App\Services\Integrations\OneCBridge;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

class HistoricalPurchaseRate
{
    public static function inByn(object $line): object
    {
        if ($line->currency_key !== OneCPurchaseCosts::RUB) {
            return $line;
        }

        $date = substr($line->doc_date, 0, 10);
        $rate = Cache::rememberForever('nbrb:RUB:'.$date, function () use ($date): float {
            $data = Http::timeout(10)->retry(2, 250)->get('https://api.nbrb.by/exrates/rates/RUB', [
                'parammode' => 2, 'ondate' => $date,
            ])->throw()->json();
            if (($data['Cur_Abbreviation'] ?? null) !== 'RUB' || substr($data['Date'] ?? '', 0, 10) !== $date
                || ($data['Cur_OfficialRate'] ?? 0) <= 0 || ($data['Cur_Scale'] ?? 0) <= 0) {
                throw new UnexpectedValueException("Нет курса НБРБ для RUB на {$date}");
            }

            return $data['Cur_OfficialRate'] / $data['Cur_Scale'];
        });

        $converted = clone $line;
        $converted->price_net *= $rate;
        $converted->price_gross *= $rate;

        return $converted;
    }
}
