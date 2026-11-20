<?php

namespace App\Services\Suppliers;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class HaierTechnicalCatalog
{
    /** Only complete model names, including every capacity, generation and suffix. */
    public static function model(string $name): ?string
    {
        $name = mb_strtoupper(str_replace('ё', 'е', $name));
        $name = preg_replace('/\b(?:HAIER|НАСТЕННЫЙ|ГАЗОВЫЙ|КОТЕЛ|ЭЛЕКТРИЧЕСКИЙ|НАКОПИТЕЛЬНЫЙ|ПРОТОЧНЫЙ|ВОДОНАГРЕВАТЕЛЬ)\b/u', '', $name);
        $model = trim(preg_replace('/\s+/u', ' ', $name));
        if (! preg_match('/^[A-Z0-9][A-Z0-9 .()\-]*$/D', $model) || ! preg_match('/[0-9]/', $model)) {
            return null;
        }

        return str_replace(' ', '', $model);
    }

    public function index(): array
    {
        return Cache::remember('termteplo:haier:articles:v1', now()->addDay(), function () {
            $paths = [];
            foreach (['gas_boilers', 'water_heaters', 'flow_water_heaters', 'gas_flow', 'heating_boilers', 'electric_towels', 'electric_boilers'] as $category) {
                $html = Http::timeout(25)->retry(2, 500)->get("https://haierproff.ru/heaters/catalog/{$category}/")->throw()->body();
                preg_match_all('~href="(/heaters/catalog/[a-z0-9_-]+/[a-z0-9_-]+/([A-Z0-9]{6,})/)"~', $html, $links, PREG_SET_ORDER);
                foreach ($links as [, $path, $article]) {
                    $paths[$article][$path] = true;
                }
            }

            return array_map('array_key_first', array_filter($paths, fn ($matches) => count($matches) === 1));
        });
    }

    public function specifications(string $article, string $html): array
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NONET);
        $xpath = new DOMXPath($dom);
        $actual = trim($xpath->evaluate('string(//*[contains(@class,"pd__services-text") and normalize-space(.)="Артикул"]/following-sibling::*[1])'));
        if ($actual !== $article) {
            return [];
        }
        $specs = [];
        foreach ($xpath->query('//*[contains(@class,"characteristic-list__section")]') as $section) {
            $group = trim($xpath->evaluate('string(.//*[contains(@class,"characteristic-list__caption")])', $section));
            foreach ($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " characteristic-list__item ")]', $section) as $item) {
                $key = trim($xpath->evaluate('string(.//*[contains(@class,"characteristic-list__key")])', $item));
                $value = trim($xpath->evaluate('string(.//*[contains(@class,"characteristic-list__val")])', $item));
                if ($key !== '' && $value !== '') {
                    $specs[$group === '' ? $key : "{$group} · {$key}"] = $value;
                }
            }
        }

        return $specs;
    }

    public static function append(array $existing, array $incoming): array
    {
        $list = array_is_list($existing);
        $labels = $list ? array_column($existing, 'key') : array_keys($existing);
        $keys = array_fill_keys(array_map(fn ($key) => mb_strtolower(trim((string) $key)), $labels), true);
        foreach ($incoming as $key => $value) {
            $folded = mb_strtolower(trim($key));
            if (isset($keys[$folded])) {
                continue;
            }
            $keys[$folded] = true;
            if ($list) {
                $existing[] = ['key' => $key, 'value' => $value];
            } else {
                $existing[$key] = $value;
            }
        }

        return $existing;
    }
}
