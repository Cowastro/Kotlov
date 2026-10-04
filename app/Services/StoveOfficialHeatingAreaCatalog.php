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
        $entries = [
            'panadero-pec-kamin-akita-ecodesign' => $this->entry(88, 'https://panadero.com/producto/akita/', 'Panadero — AKITA'),
            'panadero-pec-kamin-maja-s-ecodesign' => $this->entry(84, 'https://panadero.com/producto/maja-s/', 'Panadero — MAJA-S'),
            'panadero-pec-kamin-onix-wall-ecodesign' => $this->entry(96, 'https://panadero.com/producto/onix-wall/', 'Panadero — ONIX WALL'),
            'panadero-pec-kamin-osaka-ecodesign' => $this->entry(96, 'https://panadero.com/producto/osaka/', 'Panadero — OSAKA'),
            'panadero-pec-kamin-oval-ecodesign' => $this->entry(104, 'https://panadero.com/producto/oval/', 'Panadero — OVAL'),
            'panadero-pec-kamin-suerte-ecodesign' => $this->entry(96, 'https://panadero.com/producto/suerte/', 'Panadero — SUERTE'),
            'mbs-pec-olympia-l-cernaia' => $this->entry(79, 'https://mbs.rs/wp-content/uploads/2024/01/MBS-Serbia-catalogue-2024.pdf', 'MBS product catalogue 2024 — SD Olympia'),
            'mbs-pec-olympia-s' => $this->entry(79, 'https://mbs.rs/wp-content/uploads/2024/01/MBS-Serbia-catalogue-2024.pdf', 'MBS product catalogue 2024 — SD Olympia S'),
            'mbs-pec-olymp-l-krasnaia' => $this->entry(67, 'https://mbs.rs/wp-content/uploads/2024/01/MBS-Serbia-catalogue-2024.pdf', 'MBS product catalogue 2024 — SD Olymp'),
            'mbs-pec-olymp-plus-l-kremovaia' => $this->entry(67, 'https://mbs.rs/wp-content/uploads/2024/01/MBS-Serbia-catalogue-2024.pdf', 'MBS product catalogue 2024 — SD Olymp S / Olymp Plus'),
            'mbs-plita-na-tverdom-toplive-thermo-magnum-4d-d-s-pravyi' => $this->entry(93, 'https://mbs.rs/wp-content/uploads/2024/01/MBS-Serbia-catalogue-2024.pdf', 'MBS product catalogue 2024 — SD Thermo Magnum, right-hand version'),
            'mbs-plita-na-tverdom-toplive-thermo-magnum-4d-l-s-levyi' => $this->entry(93, 'https://mbs.rs/wp-content/uploads/2024/01/MBS-Serbia-catalogue-2024.pdf', 'MBS product catalogue 2024 — SD Thermo Magnum, left-hand version'),
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
            'termofor-ogon-batareya-5-lajt-antracit' => $this->entry(37, 'https://t-m-f.ru/catalog-new/model/otopitelnye_pechi_1/ogon_batareya_5_layt/', 'TMF — Огонь-Батарея 5 Лайт'),
            'termofor-ogon-batareya-7-lajt-antracit' => $this->entry(56, 'https://t-m-f.ru/catalog-new/model/otopitelnye_pechi_1/ogon_batareya_7_layt/', 'TMF — Огонь-Батарея 7 Лайт'),
            'termofor-ogon-batareya-9-lajt-antracit' => $this->entry(74, 'https://t-m-f.ru/catalog-new/model/otopitelnye_pechi_1/ogon_batareya_9_layt/', 'TMF — Огонь-Батарея 9 Лайт'),
            'termofor-ogon-batareya-11-lajt-antracit' => $this->entry(93, 'https://t-m-f.ru/catalog-new/model/otopitelnye_pechi_1/ogon_batareya_11_layt/', 'TMF — Огонь-Батарея 11 Лайт'),
            'termofor-student-ugol' => $this->entry(56, 'https://t-m-f.ru/catalog-new/model/otopitelnye_pechi_1/student_ugol/', 'TMF — Студент Уголь'),
            'termofor-inzhener-ugol' => $this->entry(93, 'https://t-m-f.ru/catalog-new/model/otopitelnye_pechi_1/inzhener_ugol/', 'TMF — Инженер Уголь'),
            'termofor-professor-ugol' => $this->entry(370, 'https://t-m-f.ru/catalog-new/model/otopitelnye_pechi_1/professor_ugol/', 'TMF — Профессор Уголь'),
            'termofor-normal-2-turbo-antracit' => $this->entry(45, 'https://t-m-f.ru/catalog-new/model/otopitelnye_pechi_1/normal_2_turbo/', 'TMF — Нормаль-2 Турбо'),
            'pech-termofor-ogon-batareya-7-antracit-seryj-metallik' => $this->entry(56, 'https://t-m-f.ru/catalog-new/model/otopitelnye_pechi_1/ogon_batareya_7/', 'TMF — Огонь-Батарея 7'),
            'termofor-zolushka-lajt' => $this->entry(19, 'https://t-m-f.ru/catalog-new/model/otopitelnye_pechi_1/zolushka_2016_layt/', 'TMF — Золушка 2016 Лайт'),
            'po-farengeit-10-antracit' => $this->entry(74, 'https://t-m-f.ru/catalog-new/model/otopitelnye_pechi_1/drovyanye/farengeyt_10/farengeyt_10/', 'TMF — Фаренгейт 10'),
            'po-le-burze-antracit' => $this->entry(19, 'https://t-m-f.ru/catalog-new/model/otopitelnye_pechi_1/le_burzhe/', 'TMF — Ле Бурже'),
            'po-statika-tetra-mini-cernaia-bronza' => $this->entry(37, 'https://t-m-f.ru/catalog-new/model/pechi_kaminy_i_topki_1/varochnye_1/statika_tetra_mini/', 'TMF — Статика Тетра Мини'),
            'po-vodogreinaia-normal-batareia-tv-to-antracit-115' => $this->entry(37, 'https://t-m-f.ru/catalog-new/model/otopitelnye_pechi_1/drovyanye/normal_batareya/', 'TMF — Нормаль-батарея'),
            'po-zoluska-2016' => $this->entry(19, 'https://t-m-f.ru/catalog-new/model/otopitelnye_pechi_1/zolushka_2016/', 'TMF — Золушка 2016'),
        ];

        $aliases = [
            'po-docent-cd-tv' => 'pech-otopitelnaya-termofor-docent',
            'po-docent-sd-tv' => 'pech-otopitelnaya-termofor-docent',
            'po-gimnazist' => 'termofor-gimnazist',
            'po-inzener-cd-sk-tv' => 'pech-otopitelnaya-termofor-inzhiner',
            'po-inzener-sd-sk-tv' => 'pech-otopitelnaya-termofor-inzhiner',
            'po-inzener-ugol-cd-ck-zg-tv' => 'termofor-inzhener-ugol',
            'po-normal-2-antracit-tv' => 'pech-otopitelnaya-termofor-normal-2',
            'po-normal-2-antracit-tz' => 'pech-otopitelnaya-termofor-normal-2',
            'po-normal-2-turbo-antracit-tv' => 'termofor-normal-2-turbo-antracit',
            'po-normal-2-turbo-antracit-tz' => 'termofor-normal-2-turbo-antracit',
            'po-ogon-batareia-11-antracit' => 'pech-otopitelnaya-ogon-batareya-11-antracit',
            'po-ogon-batareia-11-lait-antracit' => 'termofor-ogon-batareya-11-lajt-antracit',
            'po-ogon-batareia-5-antracit' => 'termofor-ogon-batareya-5-antracit',
            'po-ogon-batareia-5-lait-antracit' => 'termofor-ogon-batareya-5-lajt-antracit',
            'po-ogon-batareia-7-antracit' => 'pech-otopitelnaya-termofor-ogon-batareya-7-antrocit',
            'po-ogon-batareia-7-lait-antracit' => 'termofor-ogon-batareya-7-lajt-antracit',
            'po-ogon-batareia-9-antracit' => 'pech-termofor-ogon-batareya-9-antracit',
            'po-ogon-batareia-9-lait-antracit' => 'termofor-ogon-batareya-9-lajt-antracit',
            'po-student-cd-sk-tv' => 'pech-otopitelnaya-termofor-student',
            'po-student-sd-sk-tv' => 'pech-otopitelnaya-termofor-student',
            'po-student-ugol-cd-ck-zg-tv' => 'termofor-student-ugol',
            'po-zoluska-2016-lait' => 'termofor-zolushka-lajt',
        ];

        foreach ($aliases as $slug => $sourceSlug) {
            $entries[$slug] = $entries[$sourceSlug];
        }

        return $entries;
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
