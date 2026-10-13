<?php

namespace App\Services\CrmMail;

use Throwable;

class MailEncodingNormalizer
{
    public function decodeHeader(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = preg_replace("/\r?\n[\t ]+/", ' ', $value) ?? $value;
        $value = preg_replace('/(?<=\?=)[\t ]+(?==\?)/', '', $value) ?? $value;
        $decoded = preg_replace_callback(
            '/=\?([^?]+)\?([bq])\?([^?]*)\?=/i',
            function (array $match): string {
                $bytes = strtolower($match[2]) === 'b'
                    ? base64_decode($match[3], true)
                    : quoted_printable_decode(str_replace('_', ' ', $match[3]));

                return $bytes === false
                    ? $match[0]
                    : $this->toUtf8($bytes, $match[1]);
            },
            $value,
        );

        if ($decoded === null) {
            return trim($this->toUtf8($value));
        }

        return trim($this->toUtf8($decoded));
    }

    public function toUtf8(string $value, ?string $charset = null): string
    {
        if ($value === '') {
            return '';
        }

        $source = strtoupper(trim((string) $charset, " \t\n\r\0\x0B\"'"));
        $source = match ($source) {
            'WINDOWS1251', 'CP1251', 'WIN-1251' => 'Windows-1251',
            'KOI8R' => 'KOI8-R',
            'UTF8' => 'UTF-8',
            default => $source,
        };

        try {
            if ($source !== '' && ! in_array($source, ['DEFAULT', 'UNKNOWN-8BIT', 'UTF-8', 'US-ASCII', 'ASCII'], true)) {
                return mb_convert_encoding($value, 'UTF-8', $source);
            }

            if (! mb_check_encoding($value, 'UTF-8')) {
                $detected = mb_detect_encoding(
                    $value,
                    ['Windows-1251', 'KOI8-R', 'ISO-8859-5', 'ISO-8859-1'],
                    true,
                );

                return $detected
                    ? mb_convert_encoding($value, 'UTF-8', $detected)
                    : mb_scrub($value, 'UTF-8');
            }
        } catch (Throwable) {
            return mb_scrub($value, 'UTF-8');
        }

        return $value;
    }
}
