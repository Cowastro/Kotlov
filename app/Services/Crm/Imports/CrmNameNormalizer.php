<?php

namespace App\Services\Crm\Imports;

final class CrmNameNormalizer
{
    public static function normalize(?string $name): string
    {
        $name = mb_strtoupper(trim((string) $name));

        return preg_replace('/[^\pL\pN]+/u', '', $name) ?: '';
    }

    public static function organizationKey(?string $name): string
    {
        $name = mb_strtoupper(trim((string) $name));
        $name = preg_replace(
            '/(?:^|[^\pL\pN])(СООО|ООО|ОАО|ЗАО|ОДО|УП|ЧУП|ЧТУП|ИП|СП)(?=$|[^\pL\pN])/u',
            ' ',
            $name,
        ) ?? $name;

        return self::normalize($name);
    }
}
