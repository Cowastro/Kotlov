<?php

namespace Tests\Unit;

use App\Console\Commands\SyncEcokaminStovesCommand;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class SyncEcokaminStovesCommandTest extends TestCase
{
    #[Test]
    public function shaman_keeps_retail_price_and_calculates_dealer_purchase_price(): void
    {
        $reflection = new ReflectionClass(SyncEcokaminStovesCommand::class);
        $featured = $reflection->getConstant('FEATURED_PRODUCTS')['18838'];
        $method = $reflection->getMethod('applyCommercialTerms');

        self::assertSame('ООО «СанБизнесГруп»', $reflection->getConstant('SUPPLIER_NAME'));

        $item = $method->invoke(new SyncEcokaminStovesCommand(), $featured + ['in_stock' => true]);

        self::assertSame(149990.0, $item['price']);
        self::assertSame(112492.5, $item['purchase_price']);
        self::assertSame(25.0, $item['dealer_discount_percent']);
        self::assertSame('ЭкоКамин', $item['brand']);
        self::assertSame('Камин чугунный ЭкоКамин ШАМАН', $item['name']);
        self::assertSame('Камин чугунный ЭкоКамин ШАМАН', $item['h1']);
        self::assertSame('ЭкоКамин, Россия', $item['attributes']['Производитель']);
        self::assertStringContainsString('легендарного Invicta Shaman', $item['short_description']);
        self::assertStringContainsString('самостоятельная модель', mb_strtolower($item['content']));
        self::assertStringNotContainsString('товар бренда Invicta', $item['content']);
        self::assertFalse($item['in_stock']);
        self::assertSame('preorder', $item['stock_status']);
        self::assertSame('https://cloud.mail.ru/public/b6qm/2Ua58bCkh', $item['live_photos_url']);
    }
}
