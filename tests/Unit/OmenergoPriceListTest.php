<?php

namespace Tests\Unit;

use App\Services\Suppliers\OmenergoPriceList;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class OmenergoPriceListTest extends TestCase
{
    public function test_money_and_stock_never_fabricate_quantities(): void
    {
        $p = new OmenergoPriceList;
        $this->assertSame(1079.49, $p->money('1 079,49'));
        $this->assertNull($p->money('нет'));
        foreach (['*', '**', '***', 'Под заказ'] as $marker) {
            $this->assertNull($p->stock($marker)['quantity']);
        }
        $this->assertTrue($p->stock('*')['in_stock']);
        $this->assertSame('low_without_quantity', $p->stock('*')['stock_status']);
        $this->assertTrue($p->stock('**')['in_stock']);
        $this->assertSame(0, $p->stock('Нет в наличии')['quantity']);
        $this->expectException(RuntimeException::class);
        $p->stock('Скоро');
    }

    public function test_names_keep_variant_and_power_identity(): void
    {
        $p = new OmenergoPriceList;
        $this->assertSame($p->nameKey('Энергия АСН-1000'), $p->nameKey('Стабилизатор напряжения Энергия ACH 1000'));
        $this->assertNotSame($p->nameKey('Энергия ACH 1000'), $p->nameKey('Энергия ACH 1000 навесной'));
        $this->assertNotSame($p->nameKey('Энергия Voltron 500 (HP)'), $p->nameKey('Энергия Voltron Neo 500'));
        $this->assertNotSame($p->nameKey('Энергия ACH 1000'), $p->nameKey('Энергия ACH 10000'));
        $this->assertSame('E0101-0123', $p->articleKey(' Е0101-0123 '));
    }

    public function test_changed_headers_are_rejected_before_import(): void
    {
        $this->expectException(RuntimeException::class);
        (new OmenergoPriceList)->parse('<html>Sign in</html>');
    }
}
