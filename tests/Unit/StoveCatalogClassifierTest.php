<?php

namespace Tests\Unit;

use App\Services\StoveCatalogClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StoveCatalogClassifierTest extends TestCase
{
    #[DataProvider('unambiguousProducts')]
    public function test_it_classifies_only_unambiguous_non_heating_products(string $name, string $category): void
    {
        $this->assertSame($category, (new StoveCatalogClassifier)->targetCategorySlug($name));
    }

    public static function unambiguousProducts(): array
    {
        return [
            ['Печь банная Мета-Бел ПБМ 20 ПС', 'drovyanye-pechi-dlya-bani'],
            ['Банная печь на дровах', 'drovyanye-pechi-dlya-bani'],
            ['Печь-каменка для парной', 'drovyanye-pechi-dlya-bani'],
            ['Kratki костровая чаша CASA GOBLET', 'mangalyi'],
            ['Садовый очаг из стали', 'mangalyi'],
            ['Полки для подогрева к печи Тайга Pro', 'aksessuary-kaminy'],
        ];
    }

    #[DataProvider('realHeatingProducts')]
    public function test_it_does_not_move_real_or_uncertain_heating_products(string $name): void
    {
        $this->assertNull((new StoveCatalogClassifier)->targetCategorySlug($name));
    }

    public static function realHeatingProducts(): array
    {
        return [
            ['Камин ПРАГА угловой'],
            ['Камин ПАНОРАМА THREE GLASS'],
            ['Печь-камин Kratki BJORN'],
            ['Отопительная печь Теплодар ОВ-120'],
        ];
    }
}
