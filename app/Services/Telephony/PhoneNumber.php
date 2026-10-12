<?php

namespace App\Services\Telephony;

final class PhoneNumber
{
    public static function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?: '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 9) {
            return '375'.$digits;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '80')) {
            return '375'.substr($digits, 2);
        }

        return $digits;
    }

    /** @return list<string> */
    public static function databaseVariants(?string $phone): array
    {
        $normalized = self::normalize($phone);

        if (! $normalized) {
            return [];
        }

        $variants = [$normalized];

        if (str_starts_with($normalized, '375') && strlen($normalized) === 12) {
            $variants[] = '80'.substr($normalized, 3);
            $variants[] = substr($normalized, 3);
        }

        return array_values(array_unique($variants));
    }
}
