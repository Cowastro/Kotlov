<?php

namespace Tests\Unit;

use App\Services\Suppliers\TdKomplektFeed;
use PHPUnit\Framework\TestCase;

class TdKomplektPumpCategoriesTest extends TestCase
{
    public function test_supplier_sections_are_corrected_by_the_actual_product_type(): void
    {
        $cases = [
            ['Баки мембранные', 'Бак мембранный для отопления Wester WRV8', '', 'membrannye-baki'],
            ['Баки мембранные', 'Бак мембранный для водоснабж Wester WAV24', '', 'membrannye-baki'],
            ['Баки мембранные', 'Мембрана для баков 24 л Wester', '', 'komplektuyushchie-dlya-nasosov'],
            ['Баки мембранные', 'Комплект для крепления мембранных баков WESTER', '', 'komplektuyushchie-dlya-nasosov'],
            ['Баки мембранные', 'Кронштейн для расширительного бака 1"', '', 'komplektuyushchie-dlya-nasosov'],
            ['Станции водоснабжения', 'Баллон резиновый для гидроаккумулятора 24л', '', 'komplektuyushchie-dlya-nasosov'],
            ['Станции водоснабжения', 'Станция водоснабжения автоматическая ECO GFI-904', '', 'nasosnyie-stantsii'],
            ['Насосы циркуляционные', 'Насосная установка повышения давления PW-175EA WILO', '', 'nasosy-povysheniya-davleniya'],
            ['Насосы циркуляционные', 'Насос TOP-Z 30/7 DM WILO', '', 'tsirkulyatsionnyie'],
            ['Насосы циркуляционные', 'Насос циркуляционный Titan-RS25-40-130, A&P', '', 'tsirkulyatsionnyie'],
            ['Насосы погружные', 'Канализационная насосная установка Marvel SmartLift, A&P', '', 'kanalizatsionnye-nasosy'],
            ['Насосы погружные', 'Насос погружной фекальный Marvel CD 1.1 A&P', '', 'fekalnye-nasosy'],
            ['Насосы погружные', 'Насос погружной ECO CP-405', 'Поплавковый выключатель', 'drenajnyie'],
            ['Насосы погружные', 'Насос погружной для чистой воды GOOD WORK 3-4800/20-7/3 A&P', 'Центробежный, многоступенчатый. Поплавковый выключатель.', 'nasosy-dlya-kolodtsa'],
            ['Насосы скважинные', 'Насос скважинный винтовой DGM BP-55W', '', 'skvajinnye-nasosy'],
            ['Насосы циркуляционные', 'Насос скважинный тестовый', '', 'skvajinnye-nasosy'],
            ['Насосы погружные', 'Насос циркуляционный тестовый', '', 'tsirkulyatsionnyie'],
            ['Насосы садовые', 'Насос садовый ECO GFI-07P', '', 'poverhnostnyie'],
            ['Насосы вибрационные', 'Насос вибрационный ECO CPV-330', '', 'nasosy-dlya-kolodtsa'],
            ['Насосы вибрационные', 'Ремкомплект для вибрационных насосов ECO', '', 'komplektuyushchie-dlya-nasosov'],
            ['Товары сопутствующие для насосов', 'Соединение вн. резьба 1" х елочка AV Engineering', '', 'komplektuyushchie-dlya-nasosov'],
            ['Товары сопутствующие для насосов', 'Комплект гаек для циркуляционного насоса', '', 'komplektuyushchie-dlya-nasosov'],
            ['Чистка прайса + распродажа', 'Насос циркуляционный TOP-RL30/6,5 WILO', '', null],
            ['Насосы погружные', 'Неопознанное оборудование', '', null],
        ];
        foreach ($cases as [$section,$name,$description,$expected]) {
            $row = ['parentid_name1' => 'Насосы и насосное оборудование', 'parentid_name2' => $section, 'name' => $name, 'description' => $description];
            $this->assertSame($expected, (new TdKomplektFeed)->category($row), $name);
        }
    }
}
