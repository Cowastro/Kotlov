<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class StorefrontProductSearch
{
    private const MAX_TOKENS = 10;

    /** @var list<string> */
    private const STOP_TOKENS = [
        'и', 'или', 'в', 'во', 'на', 'для', 'по', 'с', 'со', 'из', 'от', 'к', 'у', 'под', 'над',
    ];

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
        'тепловой' => ['теплов'],
        'тепловая' => ['теплов'],
        'тепловое' => ['теплов'],
        'тепловые' => ['теплов'],
        'теплового' => ['теплов'],
        'насос' => ['насос'],
        'насоса' => ['насос'],
        'насосы' => ['насос'],
        'насосов' => ['насос'],
        'дымоходный' => ['дымоход'],
        'дымоходные' => ['дымоход'],
        'дымохода' => ['дымоход'],
        'вытяжная' => ['труба'],
        'вытяжной' => ['труба'],
        'вытяжные' => ['труба'],
    ];

    public function apply(Builder $query, string $search, bool $includeInternal = false): Builder
    {
        $tokens = array_slice($this->tokens($search), 0, self::MAX_TOKENS);
        $heatingElementQuery = $this->isHeatingElementQuery($search);

        if ($tokens === []) {
            return $query;
        }

        $this->applyIndexedCandidateFilter($query, $search, $includeInternal);

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
    public function applyForSuggestions(
        Builder $query,
        string $search,
        bool $includeInternal = false,
        bool $includeCategoryMatches = true
    ): Builder {
        $tokens = array_slice($this->directTokens($search), 0, self::MAX_TOKENS);
        $heatingElementQuery = $this->isHeatingElementQuery($search);

        if ($tokens === []) {
            return $query;
        }

        $this->applyIndexedCandidateFilter($query, $search, $includeInternal);

        return $query->where(function (Builder $matches) use ($search, $tokens, $includeInternal, $includeCategoryMatches, $heatingElementQuery): void {
            if (! $heatingElementQuery) {
                // Keep cross-script brands/model codes in the fast pass. Expand
                // case only for Cyrillic values: this keeps the query compact,
                // while remaining correct on databases with a case-sensitive
                // Unicode LIKE implementation.
                foreach ($this->variants($search) as $variant) {
                    foreach ($this->fastCaseVariants($variant) as $caseVariant) {
                        $this->addTermMatches($matches, $caseVariant, $includeInternal);
                    }
                }
            }

            $matches->orWhere(function (Builder $allTokens) use ($tokens, $includeInternal, $includeCategoryMatches): void {
                foreach ($tokens as $alternatives) {
                    $allTokens->where(function (Builder $tokenMatch) use ($alternatives, $includeInternal, $includeCategoryMatches): void {
                        if ($this->isHeatingElementToken($alternatives)) {
                            $tokenMatch->whereHas('category', fn (Builder $category) => $category
                                ->where('slug', 'elektricheskie-teny-dlya-otopleniya'));

                            return;
                        }

                        foreach ($alternatives as $alternative) {
                            foreach ($this->fastCaseVariants($alternative) as $caseVariant) {
                                $this->addTermMatches($tokenMatch, $caseVariant, $includeInternal, $includeCategoryMatches);
                            }
                        }
                    });
                }
            });
        });
    }

    /**
     * Use the compact query for the common case and keep the exhaustive search
     * as a fallback. This avoids building the much larger variant expression
     * for every full results page while preserving typo/layout support when the
     * compact pass cannot find anything.
     */
    public function applyForResults(Builder $query, string $search, bool $includeInternal = false): Builder
    {
        $fastQuery = clone $query;
        $this->applyForSuggestions($fastQuery, $search, $includeInternal, false);

        if ($fastQuery->exists()) {
            return $this->applyForSuggestions($query, $search, $includeInternal, false);
        }

        $categoryQuery = clone $query;
        $this->applyForSuggestions($categoryQuery, $search, $includeInternal);

        if ($categoryQuery->exists()) {
            return $this->applyForSuggestions($query, $search, $includeInternal);
        }

        $exhaustiveQuery = clone $query;
        $this->apply($exhaustiveQuery, $search, $includeInternal);

        if ($exhaustiveQuery->exists()) {
            return $this->apply($query, $search, $includeInternal);
        }

        $relaxedQuery = clone $query;
        if ($this->applyRelaxedForResults($relaxedQuery, $search, $includeInternal)->exists()) {
            return $this->applyRelaxedForResults($query, $search, $includeInternal);
        }

        return $this->apply($query, $search, $includeInternal);
    }

    /**
     * A long copied title can contain one specification that differs from our
     * card (for example steel thickness 0.8 vs 1.0). Only after every exact
     * strategy failed, allow one token to differ and keep all remaining tokens
     * mandatory. Short queries stay strict to avoid noisy results.
     */
    private function applyRelaxedForResults(Builder $query, string $search, bool $includeInternal): Builder
    {
        $tokens = array_slice($this->directTokens($search), 0, self::MAX_TOKENS);

        if (count($tokens) < 4) {
            return $query->whereRaw('1 = 0');
        }

        $this->applyIndexedCandidateFilter($query, $search, $includeInternal);

        return $query->where(function (Builder $matches) use ($tokens, $includeInternal): void {
            foreach (array_keys($tokens) as $skippedIndex) {
                $matches->orWhere(function (Builder $candidate) use ($tokens, $skippedIndex, $includeInternal): void {
                    foreach ($tokens as $index => $alternatives) {
                        if ($index === $skippedIndex) {
                            continue;
                        }

                        $candidate->where(function (Builder $tokenMatch) use ($alternatives, $includeInternal): void {
                            foreach ($alternatives as $alternative) {
                                foreach ($this->fastCaseVariants($alternative) as $caseVariant) {
                                    $this->addTermMatches($tokenMatch, $caseVariant, $includeInternal);
                                }
                            }
                        });
                    }
                });
            }
        });
    }

    /**
     * MySQL can use the catalogue FULLTEXT index to reduce a large catalogue to
     * a small candidate set before the precise LIKE/relationship rules run.
     * SQLite remains on the portable search path used by the test suite.
     *
     * Internal searches also include supplier-only fields which are not part of
     * the public index, so they deliberately retain the exhaustive behaviour.
     */
    private function applyIndexedCandidateFilter(Builder $query, string $search, bool $includeInternal): void
    {
        if ($includeInternal || ! in_array($query->getModel()->getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $tokenGroups = collect($this->tokens($search));
        $terms = $tokenGroups
            // Put one direct form of every word first, so a long copied title
            // keeps its model codes instead of spending the limit on variants
            // of its first two words.
            ->map(fn (array $alternatives): ?string => $alternatives[0] ?? null)
            ->concat($tokenGroups->flatMap(fn (array $alternatives): array => array_slice($alternatives, 1)))
            ->flatMap(fn (string $term): array => preg_split('/[^\pL\pN]+/u', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->map(fn (string $term): string => preg_replace('/[^\pL\pN]/u', '', $term) ?? '')
            ->filter(fn (string $term): bool => mb_strlen($term) >= 3)
            ->unique()
            ->take(20)
            ->values();

        if ($terms->isEmpty()) {
            return;
        }

        $booleanQuery = $terms->map(fn (string $term): string => $term.'*')->implode(' ');
        // A multi-word product query already has several indexed product terms.
        // Keeping relationship OR branches there can make MySQL abandon the
        // FULLTEXT access path. A single word may legitimately be only a brand
        // or category, so retain entity fallbacks for that case.
        $entityVariants = count($this->directTokens($search)) === 1
            ? collect($this->variants($search))->take(8)
            : collect();

        $query->where(function (Builder $candidate) use ($booleanQuery, $entityVariants): void {
            $candidate->whereRaw(
                'MATCH (`products`.`name`, `products`.`sku`) AGAINST (? IN BOOLEAN MODE)',
                [$booleanQuery]
            );

            foreach ($entityVariants as $variant) {
                $like = '%'.$variant.'%';
                $candidate
                    ->orWhereHas('brand', fn (Builder $brand) => $brand->where('name', 'like', $like))
                    ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', $like));
            }
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

    /** @return list<string> */
    private function fastCaseVariants(string $value): array
    {
        if (! preg_match('/[а-яё]/u', $value)) {
            return [$value];
        }

        return $this->caseVariants($value);
    }

    /** @return list<list<string>> */
    private function tokens(string $search): array
    {
        $normalized = $this->normalize($search);
        $normalized = preg_replace('/гидравлическ\S*\s+разделител\S*/u', 'гидрострелка', $normalized) ?? $normalized;
        $tokens = $this->searchableTokens($normalized);

        return array_map(fn (string $token): array => $this->variants($token), $tokens);
    }

    /** @return list<list<string>> */
    private function directTokens(string $search): array
    {
        $tokens = $this->searchableTokens($this->normalize($search));

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

    /** @return list<string> */
    private function searchableTokens(string $normalizedSearch): array
    {
        $tokens = preg_split('/[^\pL\pN]+/u', $normalizedSearch, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter($tokens, function (string $token): bool {
            if (in_array($token, self::STOP_TOKENS, true)) {
                return false;
            }

            // Однобуквенные части составных обозначений вроде «ТМ-Р» дают
            // слишком широкий SQL-фильтр. Одиночные цифры сохраняем: они
            // нужны для толщины металла в запросах вида 430-0.8.
            return mb_strlen($token) > 1 || ctype_digit($token);
        }));
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
