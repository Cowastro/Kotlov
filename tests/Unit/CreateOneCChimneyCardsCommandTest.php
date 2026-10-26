<?php

namespace Tests\Unit;

use App\Console\Commands\CreateOneCChimneyCardsCommand as C;
use PHPUnit\Framework\TestCase;

class CreateOneCChimneyCardsCommandTest extends TestCase
{
    public function test_titles_specs_and_price(): void
    {
        $this->assertSame('Хомут в шахту d250 мм нерж.ст.430-0,5 мм', C::title('KD HT D240 F050 Хомут в шахту d250мм нерж.ст.430-0,5мм', false));
        $this->assertSame('Теплов и Сухов Отвод Термо 45° ОТ-Р 430-0.8/430 D115/200* (2S)', C::title('Отвод Термо 45* ОТ-Р 430-0.8/430 D115/200* (2S)', true));

        $specs = C::specs('Теплов и Сухов Труба Термо L 1000 ТТ-Р 430-0.5/430 D130/200');
        $this->assertSame('Термо (сэндвич, утеплённый)', $specs['Исполнение']);
        $this->assertSame('AISI 430 / AISI 430 (кожух)', $specs['Марка стали']);
        $this->assertSame('0.5 мм', $specs['Толщина стали']);
        $this->assertSame('130 мм', $specs['Внутренний диаметр']);
        $this->assertSame('200 мм', $specs['Внешний диаметр']);
        $this->assertSame('1000 мм', $specs['Длина']);

        $this->assertSame(25.42, C::retailFromWholesale(20.88));
    }
}
