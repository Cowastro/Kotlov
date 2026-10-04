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
            'pech-otopitelnaya-termofor-ogon-batareya-7-antrocit' => $this->entry(56, 'https://t-m-f.ru/catalog-new/model/otopitelnye_pechi_1/ogon_batareya_7/', 'TMF — Огонь-Батарея 7'),
            'pech-otopitelnaya-termofor-student' => $this->entry(56, 'https://t-m-f.ru/catalog-new/model/otopitelnye_pechi_1/student/', 'TMF — Студент'),
            'pech-otopitelnaya-termofor-student-2' => $this->entry(56, 'https://t-m-f.ru/catalog-new/model/otopitelnye_pechi_1/student/', 'TMF — Студент'),
            'pech-otopitelnaya-ogon-batareya-11-antracit' => $this->entry(93, 'https://t-m-f.ru/catalog-new/model/otopitelnye_pechi_1/ogon_batareya_11/', 'TMF — Огонь-Батарея 11'),
            'pech-otopitelnaya-termofor-inzhiner' => $this->entry(93, 'https://t-m-f.ru/catalog-new/model/otopitelnye_pechi_1/inzhener/', 'TMF — Инженер'),
            'pech-otopitelnaya-termofor-docent' => $this->entry(186, 'https://t-m-f.ru/catalog-new/model/otopitelnye_pechi_1/dotsent/', 'TMF — Доцент'),
            'pech-otopitelnaya-termofor-docent-2' => $this->entry(186, 'https://t-m-f.ru/catalog-new/model/otopitelnye_pechi_1/dotsent/', 'TMF — Доцент'),
            'pech-otopitelnaya-termofor-professor' => $this->entry(370, 'https://t-m-f.ru/catalog-new/model/otopitelnye_pechi_1/professor/', 'TMF — Профессор'),
            'pech-otopitelnaya-termofor-normal-2' => $this->entry(37, 'https://t-m-f.ru/catalog-new/model/otopitelnye_pechi_1/normal/', 'TMF — Нормаль-2'),
            'pech-termofor-ogon-batareya-9-antracit' => $this->entry(74, 'https://t-m-f.ru/catalog-new/model/otopitelnye_pechi_1/ogon_batareya_9/', 'TMF — Огонь-Батарея 9'),
            'termofor-ogon-batareya-5-antracit' => $this->entry(37, 'https://t-m-f.ru/catalog-new/model/otopitelnye_pechi_1/ogon_batareya_5/', 'TMF — Огонь-Батарея 5'),
            'termofor-gimnazist' => $this->entry(37, 'https://t-m-f.ru/catalog-new/model/otopitelnye_pechi_1/gimnazist/', 'TMF — Гимназист'),
            'pech-termofor-professor-chd' => $this->entry(370, 'https://t-m-f.ru/catalog-new/model/otopitelnye_pechi_1/professor/', 'TMF — Профессор'),
            'termofor-ogon-batareya-5b-antracit' => $this->entry(37, 'https://t-m-f.ru/catalog-new/model/otopitelnye_pechi_1/ogon_batareya_5/', 'TMF — Огонь-Батарея 5Б'),
            'terfomor-ogon-batareya-7b-antracit' => $this->entry(56, 'https://t-m-f.ru/catalog-new/model/otopitelnye_pechi_1/ogon_batareya_7/', 'TMF — Огонь-Батарея 7Б'),
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
