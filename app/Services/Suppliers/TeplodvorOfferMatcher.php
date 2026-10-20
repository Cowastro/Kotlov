<?php

namespace App\Services\Suppliers;

use Illuminate\Support\Str;

class TeplodvorOfferMatcher
{
    private const STOPWORDS = [
        'bez', 'dlya', 'so', 'na', 'po', 'iz', 'ot', 'ob', 'pri', 'ili', 'ne', 'do', 'ko', 'v', 's', 'i',
        'kotel', 'kotyol', 'pech', 'gazovyj', 'gazovyi', 'elektricheskij', 'elektricheskaya',
        'nastennyj', 'napolnyj', 'odnokonturnyj', 'dvuhkonturnyj', 'komplekt', 'sistema',
        'kitaj', 'koreya', 'polsha', 'slovakiya', 'rossiya', 'rf', 'sht', 'mm',
    ];

    /**
     * @param array<string,mixed> $offer
     * @param array<string,string> $index slug => URL
     * @return array{url:?string,score:float,margin:float,method:string}
     */
    public function match(array $offer, array $index): array
    {
        $article = $this->compact((string) ($offer['article'] ?? ''));
        $source = trim(implode(' ', array_filter([
            $offer['brand'] ?? '',
            $offer['name'] ?? '',
            $offer['article'] ?? '',
        ])));
        $tokens = $this->tokens($source);
        $numbers = array_values(array_filter($tokens, 'ctype_digit'));
        $scored = [];

        foreach ($index as $slug => $url) {
            $slug = trim((string) $slug);
            $url = trim((string) $url);
            if ($slug === '' || $url === '') {
                continue;
            }

            $slugCompact = $this->compact($slug);
            $method = 'tokens';
            if (mb_strlen($article) >= 4 && str_contains($slugCompact, $article)) {
                $score = 1.0;
                $method = 'article';
            } else {
                $slugTokens = $this->tokens($slug);
                if (! $this->numbersCompatible($numbers, $slugTokens, $slugCompact)) {
                    continue;
                }
                $common = array_values(array_intersect($tokens, $slugTokens));
                $weight = array_sum(array_map('strlen', $tokens));
                $matchedWeight = array_sum(array_map('strlen', $common));
                $coverage = $weight > 0 ? $matchedWeight / $weight : 0;
                $union = array_unique(array_merge($tokens, $slugTokens));
                $jaccard = count($union) > 0 ? count($common) / count($union) : 0;
                $score = round(($coverage * 0.78) + ($jaccard * 0.22), 4);
            }
            $scored[] = compact('url', 'score', 'method');
        }

        usort($scored, fn (array $left, array $right): int => $right['score'] <=> $left['score']);
        $best = $scored[0] ?? null;
        $second = (float) ($scored[1]['score'] ?? 0);
        $margin = $best ? round((float) $best['score'] - $second, 4) : 0.0;
        $accepted = $best && (
            $best['method'] === 'article'
            || ($best['score'] >= 0.82 && $margin >= 0.10)
        );

        return [
            'url' => $accepted ? $best['url'] : null,
            'score' => (float) ($best['score'] ?? 0),
            'margin' => $margin,
            'method' => (string) ($best['method'] ?? 'none'),
        ];
    }

    /** @return array<int,string> */
    private function tokens(string $value): array
    {
        $slug = Str::slug(str_replace('ё', 'е', mb_strtolower($value)));
        $parts = explode('-', $slug);

        return array_values(array_unique(array_filter($parts, fn (string $token): bool =>
            (strlen($token) >= 2 || ctype_digit($token))
            && ! in_array($token, self::STOPWORDS, true)
        )));
    }

    /** @param array<int,string> $numbers @param array<int,string> $slugTokens */
    private function numbersCompatible(array $numbers, array $slugTokens, string $slugCompact): bool
    {
        if ($numbers === []) {
            return true;
        }

        $concat = count($numbers) > 1 ? implode('', $numbers) : '';
        foreach ($numbers as $number) {
            if (in_array($number, $slugTokens, true)) {
                continue;
            }
            if ($concat !== '' && str_contains($slugCompact, $concat)) {
                continue;
            }

            return false;
        }

        return true;
    }

    private function compact(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', Str::ascii(mb_strtolower($value))) ?? '';
    }
}
