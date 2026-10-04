<?php

namespace App\Services;

class PelletBoilerCatalogClassifier
{
    public function isPelletBoiler(string $name): bool
    {
        $name = $this->normalize($name);

        return preg_match('/\bкот[её]л\S*/u', $name) === 1
            && preg_match('/(?:пеллет|pellet)/u', $name) === 1
            && preg_match('/(?:горелк|бункер|шнек|контроллер|автоматик|комплектующ)/u', $name) !== 1;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return preg_replace('/\s+/u', ' ', $value) ?? '';
    }
}
