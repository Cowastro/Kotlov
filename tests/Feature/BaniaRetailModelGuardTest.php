<?php

namespace Tests\Feature;

use App\Console\Commands\SyncBaniaPricelistCommand;
use Tests\TestCase;

class BaniaRetailModelGuardTest extends TestCase
{
    public function test_a_retail_row_of_another_model_does_not_price_the_card(): void
    {
        $fits = function (string $card, string $brand, string $row): bool {
            $method = new \ReflectionMethod(SyncBaniaPricelistCommand::class, 'retailRowFitsCard');

            return $method->invoke(app(SyncBaniaPricelistCommand::class), (object) ['product_name' => $card, 'brand_name' => $brand], ['name' => $row]);
        };

        $this->assertFalse($fits('Печь отопительная Везувий АОГТ 01', 'Везувий', 'Печь отопительная Везувий "Мини-1"'));
        $this->assertFalse($fits('Печь отопительная Везувий Комфорт 100 ДТ-3', 'Везувий', 'Печь отопительная Везувий Комфорт 70 ДТ-3'));
        $this->assertFalse($fits('Термофор Гейзер 2014 терракота', 'Термофор', 'Печь банная Гейзер Мини 2016 Inox ДА ЗК терракота'));
        $this->assertFalse($fits('Дверка К412 топочная герметичная', 'Везувий', 'Дверка Везувий каминная 290 антрацит'));

        $this->assertTrue($fits('Твердотопливный котел Теплодар Kupper PRO 16', 'Теплодар', 'Котел Kupper PRO 16-20'));
        $this->assertTrue($fits('Печь для бани Теплодар Русь 18 Л Профи', 'Теплодар', 'Печь Русь 18 Л Профи'));
        $this->assertTrue($fits('Печь для бани Теплодар Былина 18С 12', 'Теплодар', 'Печь Былина 18C 12'));
        $this->assertTrue($fits('Печь отопительная Везувий АОГТ 01', 'Везувий', 'Печь отопительная ВЕЗУВИЙ АОГТ 01'));
        $this->assertTrue($fits('Стекло Везувий 260 (215х185)', 'Везувий', 'Стекло Везувий 260 (0215*0185)'));
        $this->assertTrue($fits('Печь отопительная Везувий B8 (ДТ-4)', 'Везувий', 'Печь отопительная Везувий В8 ДТ-4'));
        $this->assertTrue($fits('Дверь Эталон бронза 190 x 70', 'DoorWood', 'Дверь DoorWood Эталон бронза 190x70 стекло 10 мм'));
        $this->assertFalse($fits('Антисептик для внутренних работ Prosept Sauna концентрат 1:10 1 л', '', 'Печь банная Атмосфера М сетка нержавейка'));
        $this->assertTrue($fits('Бак для воды Теплодар Парус 60 для регистра', 'Теплодар', 'Бак Парус 60 РГ'));
        $this->assertTrue($fits('Дверь для сауны DoorWood Теплый день 190х70 бронза, коробка листва', 'DoorWood', 'Дверь для сауны DoorWood Теплый день 190x70 бронза коробка листва'));
    }
}
