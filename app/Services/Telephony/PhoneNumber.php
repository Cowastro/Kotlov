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

    /**
     * One Belarusian number in the standard form for mailings: «8 (029) 6724518» → «+375 29 672-45-18»,
     * «(01593)7-07-13» → «+375 1593 7-07-13». Null when the text is not a single Belarusian number.
     */
    public static function format(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?: '';
        $national = match (true) {
            strlen($digits) === 12 && str_starts_with($digits, '375') => substr($digits, 3),
            strlen($digits) === 11 && str_starts_with($digits, '80') => substr($digits, 2),
            strlen($digits) === 10 && str_starts_with($digits, '0') => substr($digits, 1),
            strlen($digits) === 9 => $digits,
            default => null,
        };
        if ($national === null || ! preg_match('/^[1-4]/', $national)) {
            return null;
        }

        // Mobile operators and Minsk: 2-digit code + XXX-XX-XX; regions 3-digit code + XX-XX-XX; district 4-digit code + X-XX-XX.
        // A code written in brackets («(01593) 7-07-13», «8 (0216) 229702») is kept as written.
        $written = preg_match('/\(\s*(?:8\s*)?0?(\d{2,4})\s*\)/', (string) $phone, $m) && str_starts_with($national, $m[1]) ? strlen($m[1]) : null;
        $codeLength = preg_match('/^(25|29|33|44)/', $national) ? 2 : ($written ?? (str_starts_with($national, '17') ? 2 : 3));
        $code = substr($national, 0, $codeLength);
        $local = substr($national, $codeLength);
        $local = match (strlen($local)) {
            7 => substr($local, 0, 3).'-'.substr($local, 3, 2).'-'.substr($local, 5),
            6 => substr($local, 0, 2).'-'.substr($local, 2, 2).'-'.substr($local, 4),
            default => substr($local, 0, 1).'-'.substr($local, 1, 2).'-'.substr($local, 3),
        };

        return "+375 {$code} {$local}";
    }

    /**
     * Every number in a free-text field («801562 50655, 029 6645244», «+375173627945 тел/факс»), formatted,
     * plus the words left over (кроме номеров) as a note. Numbers that are not Belarusian stay as written.
     *
     * @return array{phones: list<string>, note: string}
     */
    public static function extract(?string $text): array
    {
        $text = (string) $text;
        $phones = [];
        $note = $text;
        // A run of digits with the usual separators; «доб. 123» stays in the note.
        if (preg_match_all('/(?<![\d])[+(]?\+?\d[\d\s().\-]{5,}\d(?![\d])/u', $text, $matches)) {
            foreach ($matches[0] as $match) {
                if (preg_match('/(доб|вн)\.?\s*$/iu', strstr($text, $match, true) ?: '')) {
                    continue;
                }
                $phones[] = self::format($match) ?? trim($match);
                $note = str_replace($match, ' ', $note);
            }
        }
        $note = trim(preg_replace('/^[\s,;:\/()-]+|[\s,;:\/()-]+$/u', '', preg_replace('/\s+/u', ' ', $note) ?? '') ?? '');

        return ['phones' => array_values(array_unique($phones)), 'note' => $note];
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
