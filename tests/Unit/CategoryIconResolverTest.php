<?php

namespace Tests\Unit;

use App\Support\CategoryIconResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CategoryIconResolverTest extends TestCase
{
    #[DataProvider('categoryIcons')]
    public function test_it_assigns_semantic_icons(string $slug, string $name, string $expected): void
    {
        $this->assertSame($expected, CategoryIconResolver::resolve($slug, $name));
    }

    public static function categoryIcons(): array
    {
        return [
            'coaxial chimney' => ['koaxial-dymoxod', 'Дымоходы коаксиальные', 'icon-ArrowFatUp'],
            'chimney cap' => ['zaglushki-dymohod', 'Заглушки и оголовки', 'icon-Exclude'],
            'chimney umbrella' => ['zonty-deflektory', 'Зонты и дефлекторы', 'icon-ShieldCheck'],
            'condensate drain' => ['kondensatootvody', 'Конденсатоотводы и ревизии', 'icon-Leaf'],
            'mounting' => ['krepleniya-dymohod', 'Крепления и монтаж', 'icon-PuzzlePiece'],
            'adapter' => ['perehody-adaptery-dymohod', 'Переходы и адаптеры', 'icon-ArrowsLeftRight'],
            'sandwich system' => ['dymohody-sendvich', 'Система Сэндвич', 'icon-CopySimple'],
            'heat exchanger' => ['teplosyomniki', 'Теплосъёмники', 'icon-ArrowClockwise'],
            'gas alarm' => ['new-gas-alarm', 'Сигнализаторы загазованности', 'icon-ShieldCheck'],
            'installation tools' => ['instrumenty-dlya-montazha', 'Инструменты для монтажа', 'icon-Ruler'],
            'submersible pump' => ['new-submersible-pumps', 'Насосы погружные', 'icon-ArrowLongBottom'],
            'mixing valves' => ['smesitelnaya-armatura', 'Смесительная арматура', 'icon-ArrowsLeftRight'],
            'collector cabinet' => ['kollektornye-shkafy', 'Коллекторные шкафы', 'icon-Layout'],
            'air duct' => ['gibkie-vozduhovody', 'Гибкие воздуховоды', 'icon-Wind'],
            'transformer' => ['avtotransformatory-latr', 'Автотрансформаторы ЛАТР', 'icon-Lightning-1'],
            'unknown' => ['other-products', 'Прочие товары', 'icon-Package'],
        ];
    }
}
