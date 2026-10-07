<?php

namespace App\Services;

use App\Models\Product;

class SeoMetadataBuilder
{
    public const TITLE_LIMIT = 70;
    public const DESCRIPTION_LIMIT = 180;

    public function replaceCity(?string $text, string $cityIn): ?string
    {
        if (! filled($text)) {
            return null;
        }

        $cityName = preg_replace('/^в\s+/u', '', $cityIn);
        $text = str_replace('в %city%', $cityIn, $text);
        $text = str_replace('%city%', $cityName, $text);

        return $this->normalize($text);
    }

    public function productName(Product $product): string
    {
        $name = $this->normalize((string) $product->name);
        $brand = $this->normalize((string) ($product->brand?->name ?? ''));

        if ($brand === '' || mb_stripos($name, $brand) !== false) {
            return $name;
        }

        return trim($brand . ' ' . $name);
    }

    public function productTitle(Product $product, string $cityIn): string
    {
        $stored = $this->replaceCity($product->meta_title, $cityIn);
        $brand = $this->normalize((string) ($product->brand?->name ?? ''));

        if ($stored && mb_strlen($stored) <= self::TITLE_LIMIT && ! $this->hasBrandSpam($stored, $brand)) {
            return $stored;
        }

        $name = $this->productName($product);
        $candidates = [
            $name . ' — купить ' . $cityIn . ' | KOTLOV',
            $name . ' — цена и доставка | KOTLOV',
            $name . ' | KOTLOV',
        ];

        foreach ($candidates as $candidate) {
            if (mb_strlen($candidate) <= self::TITLE_LIMIT) {
                return $candidate;
            }
        }

        $suffix = ' | KOTLOV';

        return $this->truncate($name, self::TITLE_LIMIT - mb_strlen($suffix)) . $suffix;
    }

    public function productDescription(Product $product, string $cityIn, iterable $highlights = []): string
    {
        $stored = $this->replaceCity($product->meta_description, $cityIn);

        if ($stored && $product->price) {
            $currentPrice = 'Цена ' . number_format((float) $product->price, 0, '.', ' ') . ' BYN';
            $stored = preg_replace(
                '/Цена\s+[\d\s.,]+\s*(?:руб\.?|BYN)/ui',
                $currentPrice,
                $stored
            ) ?: $stored;
        }

        $brand = $this->normalize((string) ($product->brand?->name ?? ''));

        if ($stored
            && mb_strlen($stored) <= self::DESCRIPTION_LIMIT
            && ! $this->hasBrandSpam($stored, $brand, 1)
            && ! $this->isWeakProductDescription($stored)
        ) {
            return $stored;
        }

        $description = $this->productName($product) . ' ' . $cityIn;

        $details = collect($highlights)
            ->map(fn ($highlight) => $this->normalizeProductHighlight((string) $highlight))
            ->filter(fn (string $highlight) => $highlight !== '' && mb_strlen($highlight) <= 55)
            ->unique(fn (string $highlight) => mb_strtolower($highlight))
            ->take(2)
            ->implode(', ');

        if ($details !== '') {
            $description .= '. '
                .mb_strtoupper(mb_substr($details, 0, 1))
                .mb_substr($details, 1);
        }

        if ($product->price) {
            $description .= '. Цена ' . number_format((float) $product->price, 0, '.', ' ') . ' BYN';
        }
        $description .= '. Доставка по Беларуси, гарантия и помощь с подбором.';

        return $this->truncate($description, self::DESCRIPTION_LIMIT);
    }

    public function title(?string $preferred, string $fallback): string
    {
        $title = $this->normalize((string) ($preferred ?: $fallback));

        if (mb_strlen($title) <= self::TITLE_LIMIT) {
            return $title;
        }

        $suffix = str_ends_with(mb_strtoupper($title), '| KOTLOV') ? ' | KOTLOV' : '';
        $base = $suffix ? preg_replace('/\s*\|\s*KOTLOV\s*$/ui', '', $title) : $title;

        return $this->truncate((string) $base, self::TITLE_LIMIT - mb_strlen($suffix)) . $suffix;
    }

    public function categoryTitle(string $slug, string $name, string $cityIn, ?string $stored): string
    {
        $commercialNames = [
            'electric' => 'Электрические водонагреватели',
            'vodonagrevateli' => 'Водонагреватели',
        ];

        if ($slug === 'gazovye') {
            return $this->title(
                null,
                'Газовые котлы: цены, купить ' . $cityIn . ' | KOTLOV'
            );
        }

        if ($slug === 'elektricheskie') {
            return $this->title(
                null,
                'Электрические котлы: цены, купить ' . $cityIn . ' | KOTLOV'
            );
        }

        if ($slug === 'tverdotoplivnye') {
            return $this->title(
                null,
                'Твердотопливные котлы: цены, купить ' . $cityIn . ' | KOTLOV'
            );
        }

        if ($slug === 'kotly-na-pelletah') {
            return $this->title(
                null,
                'Пеллетные котлы: цены, купить ' . $cityIn . ' | KOTLOV'
            );
        }

        $stoveCommercialNames = [
            'pechki' => 'Печи для дома',
            'pechi-kaminy' => 'Печи-камины',
            'pechi' => 'Дровяные печи',
            'peci-drovianye-otopitelnye' => 'Дровяные печи',
        ];

        if (isset($stoveCommercialNames[$slug])) {
            return $this->title(
                null,
                $stoveCommercialNames[$slug] . ': цены, купить ' . $cityIn . ' | KOTLOV'
            );
        }

        if (isset($commercialNames[$slug])) {
            return $this->title(
                null,
                $commercialNames[$slug] . ' — купить ' . $cityIn . ' | KOTLOV'
            );
        }

        $preferred = $this->replaceCity($stored, $cityIn);

        return $this->title(
            $preferred && mb_strlen($preferred) <= self::TITLE_LIMIT ? $preferred : null,
            $name . ' — купить ' . $cityIn . ' | KOTLOV'
        );
    }

    public function description(?string $preferred, string $fallback): string
    {
        return $this->truncate(
            $this->normalize((string) ($preferred ?: $fallback)),
            self::DESCRIPTION_LIMIT
        );
    }

    public function shortDescriptionFromContent(?string $content, int $limit = 220): ?string
    {
        $plain = $this->normalize(strip_tags((string) $content));

        if (mb_strlen($plain) < 80) {
            return null;
        }

        return $this->truncate($plain, max(80, $limit));
    }

    private function hasBrandSpam(string $title, string $brand, int $maximumOccurrences = 2): bool
    {
        if ($brand === '') {
            return false;
        }

        preg_match_all('/' . preg_quote($brand, '/') . '/ui', $title, $matches);

        // One mention in the product name and one site-brand mention are acceptable.
        return count($matches[0]) > $maximumOccurrences;
    }

    private function isWeakProductDescription(string $description): bool
    {
        if (mb_strlen($description) >= 110) {
            return false;
        }

        $lower = mb_strtolower($description);

        foreach ([
            'купить по лучшей цене',
            'купить по выгодной цене',
            'купить в беларуси',
            'купить по хорошей цене',
        ] as $phrase) {
            if (str_contains($lower, $phrase)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeProductHighlight(string $highlight): string
    {
        $highlight = $this->normalize($highlight);

        return preg_replace(
            '/(кВт|Вт|мм|см|м²|м³|кг|л|бар|°C|%)\s+\1(?=\s|$)/ui',
            '$1',
            $highlight
        ) ?: $highlight;
    }

    private function normalize(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function truncate(string $text, int $limit): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $short = rtrim(mb_substr($text, 0, max(1, $limit - 1)));
        $lastSpace = mb_strrpos($short, ' ');
        if ($lastSpace !== false && $lastSpace >= (int) ($limit * 0.65)) {
            $short = mb_substr($short, 0, $lastSpace);
        }

        return rtrim($short, " \t\n\r\0\x0B—,.;:") . '…';
    }
}
