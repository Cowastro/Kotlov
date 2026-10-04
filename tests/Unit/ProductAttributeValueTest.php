<?php

namespace Tests\Unit;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\ProductAttributeValue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductAttributeValueTest extends TestCase
{
    #[DataProvider('displayValueCases')]
    public function test_it_recognizes_only_attribute_values_visible_on_the_product_page(
        string $type,
        mixed $value,
        mixed $isChecked,
        ?string $optionName,
        bool $expected,
    ): void {
        $attributeValue = new ProductAttributeValue([
            'value' => $value,
            'is_checked' => $isChecked,
        ]);
        $attributeValue->setRelation('attribute', new Attribute(['type' => $type]));

        if ($optionName !== null) {
            $attributeValue->setRelation('option', new AttributeOption(['name' => $optionName]));
        }

        self::assertSame($expected, $attributeValue->hasDisplayValue());
    }

    public static function displayValueCases(): array
    {
        return [
            'empty select' => ['select', null, null, null, false],
            'filled select' => ['select', null, null, '118 мм', true],
            'unset checkbox' => ['check', null, null, null, false],
            'unchecked checkbox' => ['check', null, false, null, true],
            'filled text' => ['text', '118', null, null, true],
            'dash placeholder' => ['text', '—', null, null, false],
            'zero placeholder' => ['number', '0', null, null, false],
        ];
    }
}
