<?php

namespace App\Support\Suppliers;

use App\Services\Pricing\CurrencyPriceConverter;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * EcoKamin products are supplied by ООО «СанБизнесГруп» (supplier code
 * «sanbusinessgroup»); cards and RUB prices come from ecokamin.ru. The same
 * supplier also owns the whole BYN 1С catalogue, so EcoKamin tooling only
 * touches the rouble links (not all of them carry an ecokamin.ru URL).
 */
final class EcokaminChannel
{
    public const SUPPLIER_CODE = 'sanbusinessgroup';

    public const CURRENCY = 'RUB';

    public static function supplierId(): ?int
    {
        $id = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id');

        return $id ? (int) $id : null;
    }

    /** EcoKamin supplier_products rows: the supplier's only rouble links. */
    public static function links(int $supplierId): Builder
    {
        return DB::table('supplier_products')
            ->where('supplier_id', $supplierId)
            ->where('currency', self::CURRENCY);
    }

    /** Official NBRB rate (BYN per 1 RUB); falls back to the last stored EcoKamin rate. */
    public static function rubRate(): float
    {
        try {
            $response = Http::timeout(10)->get('https://api.nbrb.by/exrates/rates/RUB?parammode=2');
            $official = (float) $response->json('Cur_OfficialRate');
            $scale = (int) ($response->json('Cur_Scale') ?: 1);
            if ($response->ok() && $official > 0 && $scale > 0) {
                return CurrencyPriceConverter::rateFor(self::CURRENCY, round($official / $scale, 6));
            }
        } catch (\Throwable) {
            // Fall back to the stored rate below.
        }

        $supplierId = self::supplierId();
        $stored = $supplierId
            ? self::links($supplierId)->where('currency_rate', '>', 0)
                ->orderByDesc('updated_at')->value('currency_rate')
            : null;
        if (! $stored) {
            throw new \RuntimeException('Не удалось получить курс RUB НБРБ и нет сохранённого курса ЭкоКамин.');
        }

        return CurrencyPriceConverter::rateFor(self::CURRENCY, $stored);
    }
}
