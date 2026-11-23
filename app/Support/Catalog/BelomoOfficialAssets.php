<?php

namespace App\Support\Catalog;

final class BelomoOfficialAssets
{
    public static function all(): array
    {
        static $assets;

        return $assets ??= json_decode(file_get_contents(database_path('data/supplier-catalogs/belomo-official-2026-11-23.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function series(string $key): array
    {
        return self::all()[$key];
    }

    public static function verifyFiles(): void
    {
        foreach (self::all() as $series) {
            foreach ([...$series['documents'], ...$series['images']] as $asset) {
                $path = public_path(ltrim($asset['url'], '/'));
                if (! is_file($path) || ! hash_equals($asset['sha256'], hash_file('sha256', $path))) {
                    throw new \RuntimeException('Не найден или повреждён официальный файл '.$asset['url']);
                }
            }
        }
    }
}
