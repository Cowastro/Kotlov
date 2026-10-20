<?php

namespace Tests\Unit;

use App\Console\Commands\SyncEcokaminStovesCommand;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class SyncEcokaminStovesCommandTest extends TestCase
{
    #[Test]
    public function rio_series_has_three_verified_models_with_twenty_five_percent_purchase_discount(): void
    {
        $reflection = new ReflectionClass(SyncEcokaminStovesCommand::class);
        $featured = $reflection->getConstant('FEATURED_PRODUCTS');
        $method = $reflection->getMethod('applyCommercialTerms');

        foreach ([
            '17944' => ['KR206', 'kamin-ecokamin-rio-kr206'],
            '19383' => ['KR221', 'kamin-ecokamin-rio-na-drovnike-kr221'],
            '19407' => ['KR224G', 'kamin-ecokamin-rio-grafit-na-drovnike-kr224g'],
        ] as $id => [$article, $slug]) {
            self::assertArrayHasKey($id, $featured);
            self::assertSame($article, $featured[$id]['article']);
            self::assertSame($slug, $featured[$id]['slug']);
            self::assertSame('ООО «СанБизнесГруп»', $reflection->getConstant('SUPPLIER_NAME'));

            $item = $method->invoke(
                new SyncEcokaminStovesCommand,
                $featured[$id] + [
                    'in_stock' => true,
                    'attributes' => [],
                    'documents' => [],
                    'video_url' => null,
                ],
            );

            self::assertSame(219992.0, $item['price']);
            self::assertSame(164994.0, $item['purchase_price']);
            self::assertSame(25.0, $item['dealer_discount_percent']);
            self::assertSame('ЭкоКамин, Россия', $item['attributes']['Производитель']);
            self::assertSame('ООО «СанБизнесГруп»', $item['attributes']['Импортер в РБ']);
            self::assertStringContainsString('7 кВт', $item['content']);
            self::assertStringContainsString('трёхсторонн', mb_strtolower($item['short_description']));
            self::assertSame('https://www.youtube.com/watch?v=FJdwJ7EbNlo', $item['video_url']);
        }

        self::assertSame('Без дровника', $method->invoke(
            new SyncEcokaminStovesCommand,
            $featured['17944'] + ['in_stock' => true, 'attributes' => []],
        )['attributes']['Исполнение']);
        self::assertSame('Графит', $method->invoke(
            new SyncEcokaminStovesCommand,
            $featured['19407'] + ['in_stock' => true, 'attributes' => []],
        )['attributes']['Цвет']);
    }

    #[Test]
    public function shaman_keeps_retail_price_and_calculates_dealer_purchase_price(): void
    {
        $reflection = new ReflectionClass(SyncEcokaminStovesCommand::class);
        $featured = $reflection->getConstant('FEATURED_PRODUCTS')['18838'];
        $method = $reflection->getMethod('applyCommercialTerms');

        self::assertSame('ООО «СанБизнесГруп»', $reflection->getConstant('SUPPLIER_NAME'));

        $item = $method->invoke(new SyncEcokaminStovesCommand, $featured + ['in_stock' => true]);

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
