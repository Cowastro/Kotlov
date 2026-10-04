<?php

namespace App\Services;

use Illuminate\Support\Str;

class SaunaStoveFilterNormalizer
{
    /**
     * Extract only facts explicitly present in the product card. A model number
     * on its own is never treated as the steam-room volume.
     */
    public function extract(
        string $name,
        array $specs = [],
        array $attributes = [],
        ?string $shortDescription = null,
        ?string $content = null,
    ): array {
        $facts = array_merge($this->flattenSpecs($specs), $attributes);

        return [
            'volume' => $this->extractVolume($name, $facts, $shortDescription, $content),
            'door' => $this->extractDoor($name, $facts),
            'remote_firebox' => $this->extractRemoteFirebox($name, $facts),
        ];
    }

    public function volumeRange(?float $volume): ?string
    {
        if ($volume === null || $volume <= 0 || $volume > 100) {
            return null;
        }

        return match (true) {
            $volume <= 15 => 'до 15',
            $volume <= 20 => '15—20',
            $volume <= 25 => '20—25',
            $volume <= 30 => '25—30',
            default => '30 и более',
        };
    }

    /**
     * Complete facts for a small set of catalog families whose model numbers
     * are documented steam-room capacities. These rules are deliberately
     * brand- and series-specific so an arbitrary number in a title can never
     * become a filter value.
     */
    public function withVerifiedModelFacts(string $brand, string $name, array $facts): array
    {
        $brand = $this->normalize($brand);
        $name = $this->normalize($name);
        $verified = [];

        if ($brand === 'везувий' && preg_match('/\bураган\s+ковка\s+16\s*\(205\)/u', $name)) {
            $verified = ['volume' => 18.0, 'door' => true, 'remote_firebox' => true];
        } elseif ($brand === 'экокамин' && preg_match('/\bмедведь\s+30\b/u', $name)) {
            $verified = ['volume' => 30.0, 'remote_firebox' => true];
        } elseif ($brand === 'gfs') {
            $verified = match (true) {
                (bool) preg_match('/\bзк\s*18\b/u', $name) => ['volume' => 18.0],
                (bool) preg_match('/\bзк\s*25\b/u', $name) => ['volume' => 25.0],
                (bool) preg_match('/\bgrom\s*30\b/u', $name) => ['volume' => 30.0, 'door' => true, 'remote_firebox' => true],
                (bool) preg_match('/\bgrom\s*50\b/u', $name) => ['volume' => 50.0, 'door' => true, 'remote_firebox' => true],
                (bool) preg_match('/\bgrom\s*80\b/u', $name) => ['volume' => 80.0],
                (bool) preg_match('/\bгроза\s*18м?\b/u', $name) => ['volume' => 18.0],
                (bool) preg_match('/\bгроза\s*24\b/u', $name) => ['volume' => 24.0],
                default => [],
            };
        } elseif ($brand === 'aston') {
            $verified = match (true) {
                (bool) preg_match('/\bшторм\s*16\b/u', $name) => ['volume' => 16.0, 'door' => str_contains($name, 'дт-4с'), 'remote_firebox' => true],
                (bool) preg_match('/\bшторм\s*20\b/u', $name) => ['volume' => 20.0, 'door' => true, 'remote_firebox' => true],
                (bool) preg_match('/\baston\s*12\b/u', $name) => ['volume' => 14.0, 'door' => str_contains($name, 'стекл'), 'remote_firebox' => true],
                (bool) preg_match('/\baston\s*16\b/u', $name) => ['volume' => 18.0, 'door' => str_contains($name, 'стекл'), 'remote_firebox' => true],
                (bool) preg_match('/\baston\s*20\b/u', $name) => ['volume' => 22.0, 'door' => str_contains($name, 'стекл'), 'remote_firebox' => true],
                (bool) preg_match('/\baston\s*24\b/u', $name) => ['volume' => 26.0, 'door' => true, 'remote_firebox' => true],
                default => [],
            };
        } elseif (in_array($brand, ['термофор', 'tmf'], true)) {
            $verified = match (true) {
                str_contains($name, 'саяны мини') => ['volume' => 9.0, 'door' => false, 'remote_firebox' => true],
                str_contains($name, 'саяны xxl') => ['volume' => 24.0, 'door' => ! str_contains($name, ' да'), 'remote_firebox' => true],
                str_contains($name, 'скоропарка iii') => ['volume' => 16.0],
                str_contains($name, 'черная жемчужина') => ['volume' => 20.0, 'door' => true],
                default => [],
            };
        } elseif ($brand === 'теплодар' && preg_match('/\bрусь-18\s+лу\b/u', $name)) {
            $verified = ['volume' => 18.0, 'door' => false, 'remote_firebox' => false];
        } elseif ($brand === 'prometall' && preg_match('/\bатмосфера\s+m\b/u', $name)) {
            $verified = ['volume' => 16.0, 'door' => true, 'remote_firebox' => true];
        } elseif (in_array($brand, ['сибирь', 'нмк'], true)) {
            $verified = match (true) {
                (bool) preg_match('/\bкамчатка-?10\b/u', $name) => ['volume' => 10.0, 'door' => true, 'remote_firebox' => true],
                (bool) preg_match('/\bкамчатка-?15\b/u', $name) => ['volume' => 15.0, 'door' => true, 'remote_firebox' => true],
                (bool) preg_match('/\bсибирь-15\b/u', $name) => ['volume' => 15.0, 'door' => false, 'remote_firebox' => str_contains($name, 'с втк')],
                (bool) preg_match('/\bсибирь-20\b/u', $name) => ['volume' => 20.0, 'door' => str_contains($name, 'панорам'), 'remote_firebox' => true],
                (bool) preg_match('/\bсибирь-22\b/u', $name) => ['volume' => 22.0, 'door' => str_contains($name, 'панорам'), 'remote_firebox' => true],
                (bool) preg_match('/\bсибирь-24\b/u', $name) => ['volume' => 24.0, 'door' => str_contains($name, 'панорам'), 'remote_firebox' => true],
                default => [],
            };
        } elseif ($brand === 'fireway' && preg_match('/\bпаровар\s*24\b.*\bк505\b/u', str_replace(['(', ')'], ' ', $name))) {
            $verified = ['volume' => 24.0, 'door' => true, 'remote_firebox' => true];
        } elseif ($brand === 'ермак' && preg_match('/\b(16|20|24)\b/u', $name, $matches)) {
            $verified = [
                'volume' => match ((int) $matches[1]) {
                    16 => 18.0,
                    20 => 22.0,
                    24 => 26.0,
                },
                'remote_firebox' => true,
            ];
        }

        foreach ($verified as $key => $value) {
            if (($facts[$key] ?? null) === null) {
                $facts[$key] = $value;
            }
        }

        return $facts;
    }

    private function extractVolume(
        string $name,
        array $facts,
        ?string $shortDescription,
        ?string $content,
    ): ?float {
        foreach ($facts as $key => $value) {
            $normalizedKey = $this->normalize((string) $key);

            if (! preg_match('/(?:объем|обьем|объём).*(?:парил|парн|помещ)|(?:парил|парн).*(?:объем|обьем|объём)/u', $normalizedKey)) {
                continue;
            }

            if ($volume = $this->maximumCubicValue((string) $value, true)) {
                return $volume;
            }
        }

        $nameVolume = $this->maximumCubicValue($name);
        if ($nameVolume !== null) {
            return $nameVolume;
        }

        $text = trim(strip_tags(($shortDescription ?? '').' '.($content ?? '')));
        preg_match_all(
            '/(?:объ[её]м(?:\s+(?:парил\p{L}*|парн\p{L}*|помещен\p{L}*))?|для\s+парн\p{L}*|парн\p{L}*\s+объ[её]мом)[^.;:\n]{0,90}/ui',
            $text,
            $matches
        );

        foreach ($matches[0] ?? [] as $fragment) {
            if ($volume = $this->maximumCubicValue($fragment, true)) {
                return $volume;
            }
        }

        return null;
    }

    private function extractDoor(string $name, array $facts): ?bool
    {
        foreach ($facts as $key => $value) {
            if (! preg_match('/(?:двер|стекл)/u', $this->normalize((string) $key))) {
                continue;
            }

            $result = $this->glassDecision((string) $value);
            if ($result !== null) {
                return $result;
            }
        }

        return $this->glassDecision($name);
    }

    private function extractRemoteFirebox(string $name, array $facts): ?bool
    {
        foreach ($facts as $key => $value) {
            $normalizedKey = $this->normalize((string) $key);
            if (! preg_match('/(?:вынос.*топ|топк|исполнен)/u', $normalizedKey)) {
                continue;
            }

            $result = $this->remoteFireboxDecision((string) $value);
            if ($result !== null) {
                return $result;
            }
        }

        return $this->remoteFireboxDecision($name);
    }

    private function maximumCubicValue(string $value, bool $allowPlainNumber = false): ?float
    {
        $value = Str::lower(str_replace(',', '.', html_entity_decode($value)));
        $pattern = $allowPlainNumber
            ? '/(?<!\d)(\d{1,3}(?:\.\d+)?)(?!\d)/u'
            : '/(?<!\d)(\d{1,3}(?:\.\d+)?)\s*(?:м\s*[³3]|м\.?\s*куб)/ui';

        preg_match_all($pattern, $value, $matches);
        $numbers = collect($matches[1] ?? [])
            ->map(fn ($number) => (float) $number)
            ->filter(fn ($number) => $number > 0 && $number <= 100);

        return $numbers->isEmpty() ? null : (float) $numbers->max();
    }

    private function glassDecision(string $value): ?bool
    {
        $value = $this->normalize($value);

        if (preg_match('/(?:без\s+стекл|глух)/u', $value)) {
            return false;
        }

        if (preg_match('/(?:со\s+стекл|со\s+стеколь|панорам|витра|жаропрочн.*стекл|обзорн.*стекл|двер\p{L}*.*стекл)/u', $value)) {
            return true;
        }

        return null;
    }

    private function remoteFireboxDecision(string $value): ?bool
    {
        $value = $this->normalize($value);

        if (preg_match('/(?:без\s+вынос|невынос|нет\b)/u', $value)) {
            return false;
        }

        if (preg_match('/(?:с\s+вынос|выносн|вынос\s+топк|есть\b|да\b)/u', $value)) {
            return true;
        }

        return null;
    }

    private function flattenSpecs(array $specs): array
    {
        $facts = [];

        foreach ($specs as $key => $value) {
            if (is_array($value) && isset($value['key'], $value['value'])) {
                $facts[(string) $value['key']] = (string) $value['value'];

                continue;
            }

            if (is_string($key) && is_scalar($value)) {
                $facts[$key] = (string) $value;
            }
        }

        return $facts;
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', Str::lower(str_replace('ё', 'е', $value))) ?? '');
    }
}
