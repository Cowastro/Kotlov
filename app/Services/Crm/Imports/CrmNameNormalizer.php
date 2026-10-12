<?php

namespace App\Services\Crm\Imports;

final class CrmNameNormalizer
{
    public static function normalize(?string $name): string
    {
        $name = mb_strtoupper(trim((string) $name));

        return preg_replace('/[^\pL\pN]+/u', '', $name) ?: '';
    }
}
