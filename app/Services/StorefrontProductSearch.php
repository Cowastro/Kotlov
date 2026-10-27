<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class StorefrontProductSearch
{
    private const MAX_TOKENS = 8;

    /**
     * Common catalogue word forms that cannot be matched by merely changing
     * the word order. Values are deliberately short search stems, but remain
     * domain-specific enough not to broaden the result set arbitrarily.
     *
     * @var array<string, list<string>>
     */
    private const DOMAIN_TOKEN_ALIASES = [
        'газ' => ['газ'],
        'газа' => ['газ'],
        'газу' => ['газ'],
        'газом' => ['газ'],
        'газовый' => ['газ'],
        'газовая' => ['газ'],
        'газовое' => ['газ'],
        'газовые' => ['газ'],
        'газового' => ['газ'],
        'газовой' => ['газ'],
        'газовых' => ['газ'],
        'счетчик' => ['счетчик', 'счётчик'],
        'счётчик' => ['счетчик', 'счётчик'],
        'счетчики' => ['счетчик', 'счётчик'],
        'счётчики' => ['счетчик', 'счётчик'],
        'счетчика' => ['счетчик', 'счётчик'],
        'счётчика' => ['счетчик', 'счётчик'],
        'счетчиков' => ['счетчик', 'счётчик'],
        'счётчиков' => ['счетчик', 'счётчик'],
        'котел' => ['котел', 'котёл'],
        'котёл' => ['котел', 'котёл'],
        'котла' => ['котел', 'котёл'],
        'котлы' => ['котел', 'котёл'],
        'котельный' => ['котел', 'котёл'],
        'котельная' => ['котел', 'котёл'],
        'отопительный' => ['отоплен'],
        'отопительная' => ['отоплен'],
        'отопительные' => ['отоплен'],
        'отопления' => ['отоплен'],
        'дымоходный' => ['дымоход'],
        'дымоходные' => ['дымоход'],
        'дымохода' => ['дымоход'],
    ];

    public function apply(Builder $query, string $search, bool $includeInternal = false): Builder
    {
        $tokens = array_slice($this->tokens($search), 0, self::MAX_TOKENS);
        $heatingElementQuery = $this->isHeatingElementQuery($search);

        if ($tokens === []) {
            return $query;
        }

        return $query->where(function (Builder $matches) use ($search, $tokens, $includeInternal, $heatingElementQuery): void {
            // A substring search for «ТЭН» also matches the manufacturer name «СТЭН».
            // For heating-element queries the token branch below deliberately uses
            // the dedicated catalogue category instead of a raw substring match.
            if (! $heatingElementQuery) {
                foreach ($this->variants($search) as $variant) {
                    foreach ($this->caseVariants($variant) as $caseVariant) {
                        $this->addTermMatches($matches, $caseVariant, $includeInternal);
                    }
                }
            }

            $matches->orWhere(function (Builder $allTokens) use ($tokens, $includeInternal): void {
                foreach ($tokens as $alternatives) {
                    $allTokens->where(function (Builder $tokenMatch) use ($alternatives, $includeInternal): void {
                        if ($this->isHeatingElementToken($alternatives)) {
                            $tokenMatch->whereHas('category', fn (Builder $category) => $category
                                ->where('slug', 'elektricheskie-teny-dlya-otopleniya'));

                            return;
                        }

                        foreach ($alternatives as $alternative) {
                            $tokenMatch->orWhere(function (Builder $phrase) use ($alternative, $includeInternal): void {
                                foreach (explode(' ', $alternative) as $word) {
                                    $phrase->where(function (Builder $fields) use ($word, $includeInternal): void {
                                        foreach ($this->caseVariants($word) as $caseVariant) {
                                            $this->addTermMatches($fields, $caseVariant, $includeInternal, true);
                                        }
                                    });
                                }
                            });
                        }
                    });
                }
            });
        });
    }

    /**
     * A deliberately small first pass for autocomplete. Most visitors type a
     * product name, article or category in the correct keyboard layout. Running
     * every transliteration and keyboard-layout variant for each keystroke makes
     * that common path unnecessarily expensive. The controller falls back to
     * apply() when this pass finds nothing, so typo/layout support is preserved.
     */
    public function applyForSuggestions(Builder $query, string $search, bool $includeInternal = false): Builder
    {
        $tokens = array_slice($this->directTokens($search), 0, self::MAX_TOKENS);
        $heatingElementQuery = $this->isHeatingElementQuery($search);

        if ($tokens === []) {
            return $query;
        }

        return $query->where(function (Builder $matches) use ($search, $tokens, $includeInternal, $heatingElementQuery): void {
            if (! $heatingElementQuery) {
                // Keep cross-script brands/model codes in the fast pass, but do
                // not multiply every value by three case variants. Production
                // uses a case-insensitive MySQL collation.
                foreach ($this->variants($search) as $variant) {
                    $this->addTermMatches($matches, $variant, $includeInternal);
                }
            }

            $matches->orWhere(function (Builder $allTokens) use ($tokens, $includeInternal): void {
                foreach ($tokens as $alternatives) {
                    $allTokens->where(function (Builder $tokenMatch) use ($alternatives, $includeInternal): void {
                        if ($this->isHeatingElementToken($alternatives)) {
                            $tokenMatch->whereHas('category', fn (Builder $category) => $category
                                ->where('slug', 'elektricheskie-teny-dlya-otopleniya'));

                            return;
                        }

                        foreach ($alternatives as $alternative) {
                            $this->addTermMatches($tokenMatch, $alternative, $includeInternal, true);
                        }
                    });
                }
            });
        });
    }

    public function isHeatingElementQuery(string $search): bool
    {
        $tokens = preg_split('/[^\pL\pN]+/u', $this->normalize($search), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return count(array_intersect($tokens, ['тэн', 'тэны', 'тэна', 'тэнов'])) > 0;
    }

    public function applyCategory(Builder $query, string $search): Builder
    {
        return $this->applyNamedEntity($query, $search);
    }

    public function applyBrand(Builder $query, string $search): Builder
    {
        return $this->applyNamedEntity($query, $search);
    }

    private function applyNamedEntity(Builder $query, string $search): Builder
    {
        $tokens = array_slice($this->tokens($search), 0, self::MAX_TOKENS);

        if ($tokens === []) {
            return $query;
        }

        return $query->where(function (Builder $matches) use ($search, $tokens): void {
            foreach ($this->variants($search) as $variant) {
                foreach ($this->caseVariants($variant) as $caseVariant) {
                    $matches->orWhere('name', 'like', '%'.$caseVariant.'%');
                }
            }

            $matches->orWhere(function (Builder $allTokens) use ($tokens): void {
                foreach ($tokens as $alternatives) {
                    $allTokens->where(function (Builder $tokenMatch) use ($alternatives): void {
                        foreach ($alternatives as $alternative) {
                            $tokenMatch->orWhere(function (Builder $phrase) use ($alternative): void {
                                foreach (explode(' ', $alternative) as $word) {
                                    $phrase->where(function (Builder $wordMatch) use ($word): void {
                                        foreach ($this->caseVariants($word) as $caseVariant) {
                                            $wordMatch->orWhere('name', 'like', '%'.$caseVariant.'%');
                                        }
                                    });
                                }
                            });
                        }
                    });
                }
            });
        });
    }

    private function addTermMatches(Builder $query, string $term, bool $includeInternal, bool $includeCategory = false): void
    {
        $like = '%'.$term.'%';

        $query->orWhere('name', 'like', $like)
            ->orWhere('sku', 'like', $like)
            ->orWhereHas('brand', fn (Builder $brand) => $brand->where('name', 'like', $like));

        if ($includeCategory) {
            $query->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', $like));
        }

        if (! $includeInternal) {
            return;
        }

        $query
            ->orWhereHas('supplierProducts', fn (Builder $offer) => $offer
                ->where('supplier_article', 'like', $like)
                ->orWhere('supplier_name', 'like', $like)
                ->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('name', 'like', $like)))
            ->orWhereHas('integrationProducts', fn (Builder $offer) => $offer
                ->where('external_sku', 'like', $like)
                ->orWhere('name', 'like', $like)
                ->orWhereHas('source', fn (Builder $source) => $source
                    ->where('name', 'like', $like)
                    ->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('name', 'like', $like))));
    }

    /** @param list<string> $alternatives */
    private function isHeatingElementToken(array $alternatives): bool
    {
        foreach ($alternatives as $alternative) {
            if (in_array($this->normalize($alternative), ['тэн', 'тэны', 'тэна', 'тэнов'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Search variants cover transliteration, a wrong keyboard layout and
     * visually identical Latin/Cyrillic model codes without an external API.
     *
     * @return list<string>
     */
    public function variants(string $value): array
    {
        $value = $this->normalize($value);

        if ($value === '') {
            return [];
        }

        $variants = [$value];
        $variants[] = $this->swapKeyboardLayout($value);
        $variants[] = $this->latinToCyrillic($value);

        $latin = $this->cyrillicToLatin($value);
        $variants[] = $latin;
        $variants[] = $latin ? str_replace('ks', 'x', $latin) : null;
        $variants[] = $this->visualLatinToCyrillic($value);
        $variants[] = $this->visualCyrillicToLatin($value);

        if (preg_match('/^гидравлическ\S*\s+разделител\S*$/u', $value)) {
            $variants[] = 'гидрострелка';
        }
        if (preg_match('/^гидрострел\S*$/u', $value)) {
            $variants[] = 'гидравлический разделитель';
        }

        foreach (self::DOMAIN_TOKEN_ALIASES[$value] ?? [] as $alias) {
            $variants[] = $alias;
        }

        if (str_contains($value, 'ё')) {
            $variants[] = str_replace('ё', 'е', $value);
        } elseif (str_contains($value, 'е')) {
            $variants[] = str_replace('е', 'ё', $value);
        }

        return array_values(array_unique(array_filter($variants, fn (?string $variant): bool => filled($variant))));
    }

    /** @return list<string> */
    public function caseVariants(string $value): array
    {
        return array_values(array_unique([$value, mb_convert_case($value, MB_CASE_TITLE), mb_strtoupper($value)]));
    }

    /** @return list<list<string>> */
    private function tokens(string $search): array
    {
        $normalized = $this->normalize($search);
        $normalized = preg_replace('/гидравлическ\S*\s+разделител\S*/u', 'гидрострелка', $normalized) ?? $normalized;
        $tokens = preg_split('/[^\pL\pN]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_map(fn (string $token): array => $this->variants($token), $tokens);
    }

    /** @return list<list<string>> */
    private function directTokens(string $search): array
    {
        $tokens = preg_split('/[^\pL\pN]+/u', $this->normalize($search), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_map(function (string $token): array {
            $alternatives = [$token];

            foreach (self::DOMAIN_TOKEN_ALIASES[$token] ?? [] as $alias) {
                $alternatives[] = $alias;
            }

            if (str_contains($token, 'ё')) {
                $alternatives[] = str_replace('ё', 'е', $token);
            } elseif (str_contains($token, 'е')) {
                $alternatives[] = str_replace('е', 'ё', $token);
            }

            return array_values(array_unique($alternatives));
        }, $tokens);
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }

    private function latinToCyrillic(string $value): ?string
    {
        if (! preg_match('/[a-z]/', $value)) {
            return null;
        }

        return strtr($value, [
            'shch' => 'щ', 'sch' => 'щ', 'yo' => 'ё', 'zh' => 'ж', 'kh' => 'х',
            'ts' => 'ц', 'ch' => 'ч', 'sh' => 'ш', 'yu' => 'ю', 'ya' => 'я', 'ye' => 'е',
            'a' => 'а', 'b' => 'б', 'c' => 'к', 'd' => 'д', 'e' => 'е', 'f' => 'ф',
            'g' => 'г', 'h' => 'х', 'i' => 'и', 'j' => 'дж', 'k' => 'к', 'l' => 'л',
            'm' => 'м', 'n' => 'н', 'o' => 'о', 'p' => 'п', 'q' => 'к', 'r' => 'р',
            's' => 'с', 't' => 'т', 'u' => 'у', 'v' => 'в', 'w' => 'в', 'x' => 'кс',
            'y' => 'ы', 'z' => 'з',
        ]);
    }

    private function cyrillicToLatin(string $value): ?string
    {
        if (! preg_match('/[а-яё]/u', $value)) {
            return null;
        }

        return strtr($value, [
            'щ' => 'shch', 'ш' => 'sh', 'ч' => 'ch', 'ц' => 'ts', 'ю' => 'yu', 'я' => 'ya',
            'ё' => 'yo', 'ж' => 'zh', 'х' => 'kh', 'а' => 'a', 'б' => 'b', 'в' => 'v',
            'г' => 'g', 'д' => 'd', 'е' => 'e', 'з' => 'z', 'и' => 'i', 'й' => 'y',
            'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p',
            'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'ъ' => '',
            'ы' => 'y', 'ь' => '', 'э' => 'e',
        ]);
    }

    private function swapKeyboardLayout(string $value): ?string
    {
        $latin = "`qwertyuiop[]asdfghjkl;'zxcvbnm,.";
        $cyrillic = 'ёйцукенгшщзхъфывапролджэячсмитьбю';

        if (preg_match('/[a-z]/', $value)) {
            return strtr($value, array_combine(mb_str_split($latin), mb_str_split($cyrillic)));
        }

        if (preg_match('/[а-яё]/u', $value)) {
            return strtr($value, array_combine(mb_str_split($cyrillic), mb_str_split($latin)));
        }

        return null;
    }

    private function visualLatinToCyrillic(string $value): ?string
    {
        return $this->visualVariant($value, [
            'a' => 'а', 'b' => 'в', 'c' => 'с', 'e' => 'е', 'h' => 'н', 'k' => 'к',
            'm' => 'м', 'o' => 'о', 'p' => 'р', 't' => 'т', 'x' => 'х', 'y' => 'у',
        ], '/[a-z]/');
    }

    private function visualCyrillicToLatin(string $value): ?string
    {
        return $this->visualVariant($value, [
            'а' => 'a', 'в' => 'b', 'с' => 'c', 'е' => 'e', 'н' => 'h', 'к' => 'k',
            'м' => 'm', 'о' => 'o', 'р' => 'p', 'т' => 't', 'х' => 'x', 'у' => 'y',
        ], '/[а-яё]/u');
    }

    /** @param array<string, string> $map */
    private function visualVariant(string $value, array $map, string $letterPattern): ?string
    {
        if (! preg_match($letterPattern, $value)) {
            return null;
        }

        foreach (mb_str_split($value) as $character) {
            if (preg_match($letterPattern, $character) && ! isset($map[$character])) {
                return null;
            }
        }

        return strtr($value, $map);
    }
}
