<?php

namespace App\Services;

class StoveCatalogClassifier
{
    /**
     * Return a target category only for product types that are unambiguous.
     * Real heating fireplaces and products with uncertain names stay untouched.
     */
    public function targetCategorySlug(string $name): ?string
    {
        $name = $this->normalize($name);

        if (preg_match('/(?:печь\s+банная|банная\s+печь|печь[-\s]?каменка)/u', $name)) {
            return 'drovyanye-pechi-dlya-bani';
        }

        if (preg_match('/(?:костровая\s+чаша|чаша\s+для\s+костра|садовый\s+очаг)/u', $name)) {
            return 'mangalyi';
        }

        if (preg_match('/(?:полки?\s+для\s+подогрева\s+к\s+печи|аксессуар\S*\s+для\s+печи)/u', $name)) {
            return 'aksessuary-kaminy';
        }

        if (preg_match('/пеллетн\S*\s+горелк/u', $name)) {
            return 'pelletnye-gorelki';
        }

        if (preg_match('/(?:казан\s+чугунн\S*|печь\s+под\s+казан)/u', $name)) {
            return 'kazany';
        }

        return null;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace('ё', 'е', $value);

        return preg_replace('/\s+/u', ' ', $value) ?? '';
    }
}
