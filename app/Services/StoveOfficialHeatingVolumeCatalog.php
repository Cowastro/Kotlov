<?php

namespace App\Services;

class StoveOfficialHeatingVolumeCatalog
{
    /**
     * Explicit room-volume ranges published by the manufacturer.
     *
     * @return array<string, array{volume: string, source_url: string, source_label: string}>
     */
    public function entries(): array
    {
        return [
            'pec-lokomotivie-120-2019' => $this->entry('60–120 м³', 'https://www.teplodar.ru/catalog/detail/lokomotiv-120-2019/', 'Теплодар — Локомотивъ-120 (2019)'),
            'pec-lokomotivie-200-2019' => $this->entry('120–200 м³', 'https://www.teplodar.ru/catalog/detail/lokomotiv-200-2019/', 'Теплодар — Локомотивъ-200 (2019)'),
            'pec-matrica-100-11' => $this->entry('60–100 м³', 'https://www.teplodar.ru/catalog/detail/matritsa-1-1-100/', 'Теплодар — Матрица-100 (1.1)'),
            'pec-matrica-200-11' => $this->entry('140–200 м³', 'https://www.teplodar.ru/catalog/detail/matritsa-200/', 'Теплодар — Матрица-200 (1.1)'),
            'pec-meteor-150' => $this->entry('60–150 м³', 'https://www.teplodar.ru/catalog/detail/meteor_150/', 'Теплодар — Метеор-150'),
            'pec-meteor-220' => $this->entry('150–220 м³', 'https://www.teplodar.ru/catalog/detail/meteor_220/', 'Теплодар — Метеор-220'),
            'pec-top-140-dc' => $this->entry('70–140 м³', 'https://www.teplodar.ru/catalog/detail/top_model_140_s_chugunnoy_dvertsey/', 'Теплодар — ТОП-модель-140 ДЧ'),
            'pec-top-140-ds' => $this->entry('70–140 м³', 'https://www.teplodar.ru/catalog/detail/top_model_140_so_stalnoy_dvertsey/', 'Теплодар — ТОП-модель-140 ДС'),
            'pec-top-200-dc' => $this->entry('140–200 м³', 'https://www.teplodar.ru/catalog/detail/top_model_200_s_chugunnoy_dvertsey/', 'Теплодар — ТОП-модель-200 ДЧ'),
            'pec-top-200-ds' => $this->entry('140–200 м³', 'https://www.teplodar.ru/catalog/detail/top_model_200_so_stalnoy_dvertsey/', 'Теплодар — ТОП-модель-200 ДС'),
            'pec-top-300-dc' => $this->entry('200–300 м³', 'https://www.teplodar.ru/catalog/detail/top_model_300_s_chugunnoy_dvertsey/', 'Теплодар — ТОП-модель-300 ДЧ'),
            'pec-top-draiv-150' => $this->entry('50–150 м³', 'https://www.teplodar.ru/catalog/detail/top_drayv_150/', 'Теплодар — ТОП-драйв-150'),
            'po-farengeit-8-antracit' => $this->entry('до 150 м³', 'https://t-m-f.ru/catalog-new/model/otopitelnye_pechi_1/drovyanye/farengeyt_8/', 'TMF — Фаренгейт 8'),
            'po-farengeit-8-lait-antracit' => $this->entry('до 150 м³', 'https://t-m-f.ru/upload/iblock/cc5/bse8nlgy9rr898y87b8dhf3p3si2kr3j/RE-Farengeyt-Layt-_141222_.pdf', 'TMF — руководство Фаренгейт 8 Лайт'),
            '-pech-otopitelnaya-vezuviy-' => $this->entry('до 100 м³', 'https://vezuviy.su/otopitelnoe-oborudovanie/pechi-otopitelnye/otopitelnye-pechi-seriya-komfort/', 'Везувий — Комфорт 100 ДТ-3С'),
            'pech-otopitelnaya-vezuvij-triumf-180-to' => $this->entry('до 180 м³', 'https://vezuviy.su/otopitelnoe-oborudovanie/chugunnye-pechi-kaminy/pech-otopitelnaya-vezuviy-chugunnaya-triumf-180-teploobmennik/', 'Везувий — Триумф 180 т/о'),
            'pech-otopitelnaya-vezuvij-v5' => $this->entry('до 100 м³', 'https://vezuviy.su/otopitelnoe-oborudovanie/pechi-otopitelnye/pech-otopitelnaya-vezuviy-v5/', 'Везувий — В5'),
            'pech-otopitelnaya-vezuviy-aogt-02' => $this->entry('до 400 м³', 'https://vezuviy.su/otopitelnoe-oborudovanie/pech-otopitelnaya-vezuviy-aogt-02-c/', 'Везувий — АОГТ 02'),
            'pech-otopitelnaya-vezuviy-aogt-03-s' => $this->entry('до 600 м³', 'https://vezuviy.su/otopitelnoe-oborudovanie/pech-otopitelnaya-vezuviy-aogt-03/', 'Везувий — АОГТ 03'),
            'pech-otopitelnaya-vezuviy-aogt-04' => $this->entry('до 1000 м³', 'https://vezuviy.su/otopitelnoe-oborudovanie/pech-otopitelnaya-vezuviy-aogt-04/', 'Везувий — АОГТ 04'),
            'pech-otopitelnaya-vezuviy-komfort-100-dt-3' => $this->entry('до 100 м³', 'https://vezuviy.su/otopitelnoe-oborudovanie/pechi-otopitelnye/pech-otopitelnaya-vezuviy-komfort-100-dt-3/', 'Везувий — Комфорт 100 ДТ-3'),
            'pech-otopitelnaya-vezuviy-komfort-200-dt-3s' => $this->entry('до 200 м³', 'https://vezuviy.su/otopitelnoe-oborudovanie/pechi-otopitelnye/otopitelnye-pechi-seriya-komfort/', 'Везувий — Комфорт 200 ДТ-3С'),
            'pech-otopitelnaya-vezuviy-komfort-300-dt-3s' => $this->entry('до 300 м³', 'https://vezuviy.su/otopitelnoe-oborudovanie/pechi-otopitelnye/otopitelnye-pechi-seriya-komfort/', 'Везувий — Комфорт 300 ДТ-3С'),
            'pec-kamin-vezuvii-ast-13k-antracit' => $this->entry('до 260 м³', 'https://vezuviy.su/otopitelnoe-oborudovanie/chugunnye-pechi-kaminy/pech-kamin-vezuviy-ast-13-antracit-clone/', 'Везувий — АСТ-13К Антрацит'),
            'pec-kamin-vezuvii-hr-15-antracit' => $this->entry('до 300 м³', 'https://vezuviy.su/otopitelnoe-oborudovanie/pech-kamin-vezuviy-hr-15-antracit/', 'Везувий — HR-15 Антрацит'),
            'pec-kamin-vezuvii-hr-15r-antracit' => $this->entry('до 300 м³', 'https://vezuviy.su/otopitelnoe-oborudovanie/pech-kamin-vezuviy-hr-15r-antracit/', 'Везувий — HR-15P Антрацит'),
            'pec-kamin-vezuvii-kz-14rs-antracit' => $this->entry('до 280 м³', 'https://vezuviy.su/otopitelnoe-oborudovanie/pech-kamin-vezuviy-kz-14sr-antracit/', 'Везувий — KZ-14PS Антрацит'),
            'pec-kamin-aston-11kvt-180-m3-pristenno-uglovoi-o-150mm' => $this->entry('до 180 м³', 'https://pech-aston.ru/katalog/pechi-kaminy/pech-kamin-aston-11kvt-180-m3-pristenno-uglovoy-o-150mm', 'ASTON — печь-камин 11 кВт, пристенно-угловая'),
            'pec-kamin-aston-12-kvt-200-m3-prizmatik' => $this->entry('до 200 м³', 'https://pech-aston.ru/katalog/pechi-kaminy/pechi-kaminy-aston-prizmatik/pech-kamin-aston-12-kvt-200-m3-prizmatik', 'ASTON — печь-камин Призматик 12 кВт'),
            'fireway-pec-otopitelno-varocnaia-dacha-ii' => $this->entry('до 240 м³', 'https://fireway.pro/dacha-ll.html', 'FireWay — Dacha II с варочной поверхностью'),
            'fireway-pec-cugunnaia-tango' => $this->entry('до 150 м³', 'https://fireway.pro/pech-chugunnaya-ka.html', 'FireWay — Tango'),
            'pec-kamin-everest-n12m' => $this->entry('до 240 м³', 'https://everest-pech.com/chugunnye-pechi-kaminy/pech-kamin-everest-n12m/', 'Эверест — Н12М'),
            'pec-kamin-everest-n16' => $this->entry('до 320 м³', 'https://everest-pech.com/chugunnye-pechi-kaminy/pech-kamin-everest-h16/', 'Эверест — Н16'),
            'pec-kamin-everest-b7k' => $this->entry('до 140 м³', 'https://everest-pech.com/chugunnye-pechi-kaminy/pech-kamin-everest-b7u/', 'Эверест — B7'),
            'pech-otopitelnaya-kennet-ariya-200-11429' => $this->entry('до 200 м³', 'https://www.kennet.ru/catalog/dlya_doma/otopitelnye_pechi/1462/', 'Kennet — Ария 200'),
            'otopitelnaya-pech-tsar-pechi-burjuyka' => $this->entry('30–50 м³', 'https://banpechi.ru/pechi-otopitelnyie-v-minske/burzhuyka/', 'Царь-Печи — Буржуйка'),
            'pech-car-pechi-matreshka-malaya-1' => $this->entry('70–100 м³', 'https://banpechi.ru/pechi-otopitelnyie-v-minske/matreshka-malaya-1/', 'Царь-Печи — Матрёшка малая 1'),
            'pech-tsar-pechi-matreshka-bolshaya-1' => $this->entry('100–170 м³', 'https://banpechi.ru/pechi-otopitelnyie-v-minske/matreshka-bolshaya-1/', 'Царь-Печи — Матрёшка большая 1'),
            'pech-tsar-pechi-matreshka-bolshaya-1-chds' => $this->entry('100–170 м³', 'https://banpechi.ru/pechi-otopitelnyie-v-minske/matreshka-bolshaya-1/', 'Царь-Печи — Матрёшка большая 1 ЧДС'),
            'pech-tsar-pechi-matreshka-bolshaya-2' => $this->entry('150–200 м³', 'https://banpechi.ru/pechi-otopitelnyie-v-minske/matreshka-bolshaya-2/', 'Царь-Печи — Матрёшка большая 2'),
            'pech-tsar-pechi-matreshka-bolshaya-2-chds' => $this->entry('150–200 м³', 'https://banpechi.ru/pechi-otopitelnyie-v-minske/matreshka-bolshaya-2/', 'Царь-Печи — Матрёшка большая 2 ЧДС'),
            'pech-tsar-pechi-matreshka-malaya-1-chds' => $this->entry('70–100 м³', 'https://banpechi.ru/pechi-otopitelnyie-v-minske/matreshka-malaya-1/', 'Царь-Печи — Матрёшка малая 1 ЧДС'),
            'pech-tsar-pechi-matreshka-malaya-2-chds' => $this->entry('80–130 м³', 'https://banpechi.ru/pechi-otopitelnyie-v-minske/matreshka-malaya-2/', 'Царь-Печи — Матрёшка малая 2 ЧДС'),
            'pech-car-pechi-zlata' => $this->entry('35–50 м³', 'https://banpechi.ru/pechi-otopitelnyie-v-minske/zlata/', 'Царь-Печи — Злата'),
            'pech-car-pechi-zolovka' => $this->entry('50–70 м³', 'https://banpechi.ru/pechi-otopitelnyie-v-minske/zolovka/', 'Царь-Печи — Золовка'),
            'pech-tsar-pechi-milana' => $this->entry('35–45 м³', 'https://banpechi.ru/pechi-otopitelnyie-v-minske/milana/', 'Царь-Печи — Милана'),
            'pech-tsar-pechi-yarilo' => $this->entry('40–60 м³', 'https://banpechi.ru/product/otopitelnue/yarilo/', 'Царь-Печи — Ярило'),
            'pech-tsar-pechi-yarilo-dekor' => $this->entry('40–60 м³', 'https://banpechi.ru/product/otopitelnue/yarilo/', 'Царь-Печи — Ярило Декор'),
            'otopitelnaya-pech-pegas-termo-150' => $this->entry('до 150 м³', 'https://pegas-pech.ru/katalog/item/pech_pegas_termo_150/', 'Pegas — Термо 150'),
            'otopitelnaya-pech-pegas-termo-150-steklo' => $this->entry('до 150 м³', 'https://pegas-pech.ru/katalog/item/pech_pegas_termo_150_steklo/', 'Pegas — Термо 150 Стекло'),
            'otopitelnaya-pech-pegas-termo-200' => $this->entry('до 200 м³', 'https://pegas-pech.ru/katalog/katalog2/', 'Pegas — Термо 200'),
            'pech-otopitelnaya-pegas-v6' => $this->entry('до 160 м³', 'https://pegas-pech.ru/katalog/item/pech_pegas_v6/', 'Pegas — V6'),
            'otopitelnaya-pech-varvara-domovoy' => $this->entry('до 50 м³', 'https://pech-varvara.ru/products/otoplenie-doma/otopitelnye-pechi/domovoy/', 'Варвара — Домовой'),
            'otopitelnaya-pech-varvara-teplyiy-dom-150' => $this->entry('до 150 м³', 'https://pech-varvara.ru/products/otoplenie-doma/otopitelnye-pechi/teplyydom/', 'Варвара — Теплый Дом 150'),
            'otopitelnaya-pech-varvara-uyut-1-konforka' => $this->entry('до 100 м³', 'https://pech-varvara.ru/products/otoplenie-doma/otopitelnye-pechi/uyu/', 'Варвара — Уют 1 конфорка'),
            'kamin-praga-uglovoy-pravyiy-chernyiy-shamot-chernyiy' => $this->entry('105–240 м³', 'https://ecokamin.ru/catalog/kaminy/praga/14407/', 'ЭкоКамин — Прага Угловой правый, белый шамот'),
            'kamin-praga-uglovoy-levyiy-chernyiy-shamot-chernyiy' => $this->entry('105–240 м³', 'https://ecokamin.ru/catalog/kaminy/praga/14531/', 'ЭкоКамин — Прага Угловой левый, чёрный шамот'),
            'kamin-praga-uglovoy-pravyiy-chernyiy-shamot-belyiy' => $this->entry('105–240 м³', 'https://ecokamin.ru/catalog/kaminy/praga/14532/', 'ЭкоКамин — Прага Угловой правый, чёрный шамот'),
            'kamin-praga-uglovoy-levyiy-chernyiy-shamot-belyiy' => $this->entry('105–240 м³', 'https://ecokamin.ru/catalog/kaminy/praga/14342/', 'ЭкоКамин — Прага Угловой левый, белый шамот'),
            'pech-kamin-ecokamin-bavariya-panorama-prizma-s-plitoy' => $this->entry('135–240 м³', 'https://www.ecokamin.ru/upload/%D0%9A%D0%B0%D1%82%D0%B0%D0%BB%D0%BE%D0%B3%20%D0%AD%D0%BA%D0%BE%D0%BA%D0%B0%D0%BC%D0%B8%D0%BD%202023.pdf', 'ЭкоКамин 2023 — Бавария Панорама Призма с плитой, PK189'),
            'kamin-eklips-ostrovnoi-gigant-grafit' => $this->entry('140–325 м³', 'https://www.ecokamin.ru/upload/%D0%9A%D0%B0%D1%82%D0%B0%D0%BB%D0%BE%D0%B3%20%D0%AD%D0%BA%D0%BE%D0%BA%D0%B0%D0%BC%D0%B8%D0%BD%202023.pdf', 'ЭкоКамин 2023 — Эклипс Гигант'),
            'kamin-eklips-ostrovnoi-gigant-s-cernym-samotom' => $this->entry('140–325 м³', 'https://www.ecokamin.ru/upload/%D0%9A%D0%B0%D1%82%D0%B0%D0%BB%D0%BE%D0%B3%20%D0%AD%D0%BA%D0%BE%D0%BA%D0%B0%D0%BC%D0%B8%D0%BD%202023.pdf', 'ЭкоКамин 2023 — Эклипс Гигант'),
            'kamin-eklips-ostrovnoi-gigant' => $this->entry('140–325 м³', 'https://www.ecokamin.ru/upload/%D0%9A%D0%B0%D1%82%D0%B0%D0%BB%D0%BE%D0%B3%20%D0%AD%D0%BA%D0%BE%D0%BA%D0%B0%D0%BC%D0%B8%D0%BD%202023.pdf', 'ЭкоКамин 2023 — Эклипс Гигант, K186'),
        ];
    }

    /** @return array{volume: string, source_url: string, source_label: string} */
    private function entry(string $volume, string $sourceUrl, string $sourceLabel): array
    {
        return [
            'volume' => $volume,
            'source_url' => $sourceUrl,
            'source_label' => $sourceLabel,
        ];
    }
}
