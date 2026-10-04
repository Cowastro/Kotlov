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
            '-pech-otopitelnaya-vezuviy-' => $this->entry('до 100 м³', 'https://vezuviy.su/otopitelnoe-oborudovanie/pechi-otopitelnye/otopitelnye-pechi-seriya-komfort/', 'Везувий — Комфорт 100 ДТ-3С'),
            'pech-otopitelnaya-vezuvij-triumf-180-to' => $this->entry('до 180 м³', 'https://vezuviy.su/otopitelnoe-oborudovanie/chugunnye-pechi-kaminy/pech-otopitelnaya-vezuviy-chugunnaya-triumf-180-teploobmennik/', 'Везувий — Триумф 180 т/о'),
            'pech-otopitelnaya-vezuvij-v5' => $this->entry('до 100 м³', 'https://vezuviy.su/otopitelnoe-oborudovanie/pechi-otopitelnye/pech-otopitelnaya-vezuviy-v5/', 'Везувий — В5'),
            'pech-otopitelnaya-vezuviy-aogt-02' => $this->entry('до 400 м³', 'https://vezuviy.su/otopitelnoe-oborudovanie/pech-otopitelnaya-vezuviy-aogt-02-c/', 'Везувий — АОГТ 02'),
            'pech-otopitelnaya-vezuviy-aogt-03-s' => $this->entry('до 600 м³', 'https://vezuviy.su/otopitelnoe-oborudovanie/pech-otopitelnaya-vezuviy-aogt-03/', 'Везувий — АОГТ 03'),
            'pech-otopitelnaya-vezuviy-aogt-04' => $this->entry('до 1000 м³', 'https://vezuviy.su/otopitelnoe-oborudovanie/pech-otopitelnaya-vezuviy-aogt-04/', 'Везувий — АОГТ 04'),
            'pech-otopitelnaya-vezuviy-komfort-100-dt-3' => $this->entry('до 100 м³', 'https://vezuviy.su/otopitelnoe-oborudovanie/pechi-otopitelnye/pech-otopitelnaya-vezuviy-komfort-100-dt-3/', 'Везувий — Комфорт 100 ДТ-3'),
            'pech-otopitelnaya-vezuviy-komfort-200-dt-3s' => $this->entry('до 200 м³', 'https://vezuviy.su/otopitelnoe-oborudovanie/pechi-otopitelnye/otopitelnye-pechi-seriya-komfort/', 'Везувий — Комфорт 200 ДТ-3С'),
            'pech-otopitelnaya-vezuviy-komfort-300-dt-3s' => $this->entry('до 300 м³', 'https://vezuviy.su/otopitelnoe-oborudovanie/pechi-otopitelnye/otopitelnye-pechi-seriya-komfort/', 'Везувий — Комфорт 300 ДТ-3С'),
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
