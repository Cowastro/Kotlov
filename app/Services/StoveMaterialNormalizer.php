<?php

namespace App\Services;

class StoveMaterialNormalizer
{
    public const CAST_IRON = 'Чугун';

    public const STEEL = 'Сталь';

    private const MATERIAL_KEYS = [
        'материал',
        'материал корпуса',
        'материал печи',
        'материал топки',
        'материал изделия',
        'материал изготовления',
    ];

    /**
     * Return a canonical stove material only when it is explicitly stated.
     * Conflicting facts are deliberately left unresolved.
     *
     * @param  array<int|string, mixed>  $specs
     * @param  iterable<int|string, mixed>  $attributes
     */
    public function detect(string $productName, array $specs = [], iterable $attributes = []): ?string
    {
        $facts = [];

        foreach ($attributes as $key => $value) {
            if (is_array($value) && isset($value['name'], $value['value'])) {
                $this->addFact($facts, (string) $value['name'], $value['value']);

                continue;
            }

            if (is_string($key)) {
                $this->addFact($facts, $key, $value);
            }
        }

        foreach ($specs as $key => $spec) {
            if (is_array($spec) && isset($spec['key'], $spec['value'])) {
                $this->addFact($facts, (string) $spec['key'], $spec['value']);

                continue;
            }

            if (is_string($key)) {
                $this->addFact($facts, $key, $spec);
            }
        }

        $nameFact = $this->classify($productName);
        if ($nameFact !== null) {
            $facts[] = $nameFact;
        }

        $facts = array_values(array_unique($facts));

        return count($facts) === 1 ? $facts[0] : null;
    }

    public function classify(string $value): ?string
    {
        $value = $this->normalize($value);
        $castIron = preg_match('/чугун/u', $value) === 1;
        $steel = preg_match('/стал/u', $value) === 1;

        if ($castIron === $steel) {
            return null;
        }

        return $castIron ? self::CAST_IRON : self::STEEL;
    }

    public function isMaterialKey(string $key): bool
    {
        return in_array($this->normalize($key), self::MATERIAL_KEYS, true);
    }

    private function addFact(array &$facts, string $key, mixed $value): void
    {
        if (! $this->isMaterialKey($key) || ! is_scalar($value)) {
            return;
        }

        $fact = $this->classify((string) $value);
        if ($fact !== null) {
            $facts[] = $fact;
        }
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace('ё', 'е', $value);

        return preg_replace('/\s+/u', ' ', $value) ?? '';
    }
}
