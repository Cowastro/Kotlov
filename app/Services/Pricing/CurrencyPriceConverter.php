<?php

namespace App\Services\Pricing;

use InvalidArgumentException;

/**
 * Единая точка пересчёта цен поставщиков в BYN.
 *
 * Цена на сайте (products.price) всегда хранится и выводится в BYN.
 * Поставщик может отдавать прайс в своей валюте — тогда у него в
 * suppliers.currency_rate задан курс к BYN: price_byn = price * rate.
 */
class CurrencyPriceConverter
{
    public const BASE_CURRENCY = 'BYN';

    public const SUPPORTED_CURRENCIES = ['BYN', 'RUB', 'USD', 'EUR', 'PLN'];

    /**
     * Нормализовать курс: для BYN всегда 1, для остальных валют курс обязан быть > 0.
     */
    public static function rateFor(?string $currency, float|int|string|null $rate): float
    {
        if (self::normalizeCurrency($currency) === self::BASE_CURRENCY) {
            return 1.0;
        }

        $rate = (float) $rate;

        if ($rate <= 0) {
            throw new InvalidArgumentException(sprintf(
                'Не задан курс к BYN для валюты %s. Укажите currency_rate у поставщика.',
                self::normalizeCurrency($currency)
            ));
        }

        return $rate;
    }

    /**
     * Пересчитать цену в BYN. convertToByn(100, 'RUB', 0.035) = 3.50
     */
    public static function convertToByn(float|int|string|null $amount, ?string $currency, float|int|string|null $rate): ?float
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        return round((float) $amount * self::rateFor($currency, $rate), 2);
    }

    public static function normalizeCurrency(?string $currency): string
    {
        $currency = mb_strtoupper(trim((string) $currency));

        return $currency !== '' ? $currency : self::BASE_CURRENCY;
    }
}
