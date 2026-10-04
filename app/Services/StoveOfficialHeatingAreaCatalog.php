<?php

namespace App\Services;

class StoveOfficialHeatingAreaCatalog
{
    /**
     * Explicit heated areas published by the manufacturers.
     *
     * Values based only on power or heated volume are deliberately excluded.
     * Product slugs are used instead of database IDs so the catalogue remains
     * stable between environments.
     *
     * @return array<string, array{area: int, source_url: string, source_label: string}>
     */
    public function entries(): array
    {
        return [
            'panadero-pec-kamin-akita-ecodesign' => $this->entry(88, 'https://panadero.com/producto/akita/', 'Panadero — AKITA'),
            'panadero-pec-kamin-maja-s-ecodesign' => $this->entry(84, 'https://panadero.com/producto/maja-s/', 'Panadero — MAJA-S'),
            'panadero-pec-kamin-onix-wall-ecodesign' => $this->entry(96, 'https://panadero.com/producto/onix-wall/', 'Panadero — ONIX WALL'),
            'panadero-pec-kamin-osaka-ecodesign' => $this->entry(96, 'https://panadero.com/producto/osaka/', 'Panadero — OSAKA'),
            'panadero-pec-kamin-oval-ecodesign' => $this->entry(104, 'https://panadero.com/producto/oval/', 'Panadero — OVAL'),
            'panadero-pec-kamin-suerte-ecodesign' => $this->entry(96, 'https://panadero.com/producto/suerte/', 'Panadero — SUERTE'),
            'mbs-pec-olympia-l-cernaia' => $this->entry(79, 'https://mbs.rs/wp-content/uploads/2024/01/MBS-Serbia-catalogue-2024.pdf', 'MBS product catalogue 2024 — SD Olympia'),
            'mbs-pec-olympia-s' => $this->entry(79, 'https://mbs.rs/wp-content/uploads/2024/01/MBS-Serbia-catalogue-2024.pdf', 'MBS product catalogue 2024 — SD Olympia S'),
            'mbs-pec-olymp-l-krasnaia' => $this->entry(67, 'https://mbs.rs/wp-content/uploads/2024/01/MBS-Serbia-catalogue-2024.pdf', 'MBS product catalogue 2024 — SD Olymp'),
            'nordflam-pec-kamin-carini-eko' => $this->entry(84, 'https://nordflam.eu/carini', 'Nordflam — Carini'),
            'nordflam-pec-kamin-frovi-eko-belaia' => $this->entry(50, 'https://old.nordflam.eu/frovibiel', 'Nordflam — Frovi'),
            'nordflam-pec-kamin-palermo-eko' => $this->entry(78, 'https://nordflam.eu/palermo', 'Nordflam — Palermo'),
        ];
    }

    /** @return array{area: int, source_url: string, source_label: string} */
    private function entry(int $area, string $sourceUrl, string $sourceLabel): array
    {
        return [
            'area' => $area,
            'source_url' => $sourceUrl,
            'source_label' => $sourceLabel,
        ];
    }
}
