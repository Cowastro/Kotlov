<?php

namespace Tests\Unit;

use App\Console\Commands\SyncBaniaPricelistCommand;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class SyncBaniaPricelistSafetyTest extends TestCase
{
    public function test_exact_article_is_rejected_when_catalogue_variant_conflicts(): void
    {
        $candidate = (object) [
            'product_name' => 'Чугунная печь для бани Везувий Легенда Ковка 22 (224)',
            'supplier_name' => 'Везувий Легенда Ковка',
        ];
        $row = $this->row('ВЕЗУВИЙ Легенда Ковка 16 (224)', '4670013507344');

        $result = $this->invoke('matchRow', $row, ['article' => ['4670013507344' => [$candidate]]], [$candidate]);

        self::assertSame('manual_review', $result['action']);
        self::assertSame('article_title_conflict', $result['match_type']);
    }

    public function test_exact_article_is_accepted_for_same_model_after_generic_words_are_removed(): void
    {
        $candidate = (object) [
            'product_name' => 'Чугунная печь для бани Везувий Легенда Ковка 22 (224)',
            'supplier_name' => '',
        ];
        $row = $this->row('ВЕЗУВИЙ Легенда Ковка 22 (224)', '4670013507573');

        $result = $this->invoke('matchRow', $row, ['article' => ['4670013507573' => [$candidate]]], [$candidate]);

        self::assertSame('article', $result['match_type']);
        self::assertArrayNotHasKey('action', $result);
    }

    public function test_near_title_match_does_not_repair_a_different_product(): void
    {
        $candidate = (object) [
            'product_name' => 'Печь для бани Везувий Русичъ Антрацит 12 (ДТ-3)',
            'supplier_name' => '',
            'price_byn' => 1135,
            'product_price' => 1135,
        ];
        $row = $this->row('ВЕЗУВИЙ Скиф Стандарт 12 (ДТ-3)', '4680019121598');

        $result = $this->invoke('matchRow', $row, ['article' => []], [$candidate]);

        self::assertNotSame('title', $result['match_type']);
        self::assertSame('manual_review', $result['action']);
    }

    public function test_unique_exact_title_can_override_a_stale_article_link(): void
    {
        $wrongArticleProduct = (object) [
            'product_name' => 'Печь отопительная ASTON Lesnaya 200',
            'supplier_name' => '',
        ];
        $exactTitleProduct = (object) [
            'product_name' => 'Печь отопительная ASTON Lesnaya 300',
            'supplier_name' => '',
        ];
        $row = $this->row('Печь отопительная ASTON Lesnaya 300', '4610094715375');

        $result = $this->invoke(
            'matchRow',
            $row,
            ['article' => ['4610094715375' => [$wrongArticleProduct]]],
            [$wrongArticleProduct, $exactTitleProduct]
        );

        self::assertSame('title_article_repair', $result['match_type']);
        self::assertSame($exactTitleProduct, $result['supplier_product']);
    }

    public function test_duplicate_exact_titles_are_sent_to_manual_review(): void
    {
        $first = (object) ['product_name' => 'Печь банная Тест 18', 'supplier_name' => ''];
        $second = (object) ['product_name' => 'Печь банная Тест 18', 'supplier_name' => ''];
        $row = $this->row('Печь банная Тест 18', 'TEST-18');

        $result = $this->invoke('matchRow', $row, ['article' => []], [$first, $second]);

        self::assertSame('manual_review', $result['action']);
        self::assertSame('title_ambiguous', $result['match_type']);
    }

    public function test_standalone_retail_match_uses_exact_article_and_title(): void
    {
        $product = (object) [
            'product_id' => 10,
            'product_name' => 'Печь-камин Везувий KZ-14РS "Антрацит"',
            'supplier_name' => '',
            'supplier_article' => '4610094759515',
            'supplier_article_normalized' => '4610094759515',
        ];
        $retail = [
            'name' => 'Печь-камин Везувий KZ-14РS "Антрацит"',
            'article' => '4610094759515',
            'norm_article' => '4610094759515',
            'price' => 3433.0,
            'normalized_name' => $this->invoke('normalizeName', 'Печь-камин Везувий KZ-14РS "Антрацит"'),
        ];

        $result = $this->invoke('matchRetailForSupplierProduct', $product, [
            'article' => ['4610094759515' => [$retail]],
            'rows' => [$retail],
        ]);

        self::assertSame('retail_article_exact', $result['match_type']);
        self::assertSame(3433.0, $result['price']);
    }

    public function test_standalone_retail_match_falls_back_to_unique_exact_title_for_stale_article(): void
    {
        $product = (object) [
            'product_id' => 11,
            'product_name' => 'Печь отопительная ASTON Lesnaya 300',
            'supplier_name' => 'Печь отопительная ASTON Lesnaya 150',
            'supplier_article' => '4610094714873',
            'supplier_article_normalized' => '4610094714873',
        ];
        $wrongArticleRetail = [
            'name' => 'Печь отопительная ASTON Lesnaya 200',
            'article' => '4610094714873',
            'norm_article' => '4610094714873',
            'price' => 1200.0,
            'normalized_name' => $this->invoke('normalizeName', 'Печь отопительная ASTON Lesnaya 200'),
        ];
        $correctRetail = [
            'name' => 'Печь отопительная ASTON Lesnaya 300',
            'article' => '4610094715375',
            'norm_article' => '4610094715375',
            'price' => 1631.0,
            'normalized_name' => $this->invoke('normalizeName', 'Печь отопительная ASTON Lesnaya 300'),
        ];

        $result = $this->invoke('matchRetailForSupplierProduct', $product, [
            'article' => ['4610094714873' => [$wrongArticleRetail]],
            'rows' => [$wrongArticleRetail, $correctRetail],
        ]);

        self::assertSame('retail_title_exact', $result['match_type']);
        self::assertSame('4610094715375', $result['article']);
        self::assertSame(1631.0, $result['price']);
    }

    public function test_retail_parser_does_not_treat_price_as_missing_article(): void
    {
        $parsed = $this->invoke('parseRetailRow', ['ВЕЗУВИЙ Тестовая модель', null, 'шт', 1298], 4);

        self::assertSame('', $parsed['article']);
        self::assertSame('', $parsed['norm_article']);
        self::assertSame(1298.0, $parsed['price']);
    }

    public function test_spreadsheet_error_is_not_used_as_an_article(): void
    {
        $parsed = $this->invoke('parseRetailRow', ['ВЕЗУВИЙ Тестовая модель', '#NULL!', 'шт', 1298], 4);

        self::assertSame('', $parsed['norm_article']);
    }

    public function test_drive_folder_parser_finds_the_two_named_workbooks(): void
    {
        $folder = '108zmF6VlM-AWiRgXaMK9BFFqgz2iAdNG';
        $html = '["1eDUENigmEbQjge6G1WFqvJdjZ_YBM32A",["' . $folder . '"],"Наличие товара (без фото).xlsx","application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"]'
            . '["1tdKCGzoMoeYQngx2ggeI9DifxHKSDMPc",["' . $folder . '"],"Рекомендуемые розничные цены.xlsx","application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"]';

        $result = $this->invoke('parseDriveFolderWorkbookUrls', $html, $folder);

        self::assertCount(1, $result['наличие товара (без фото).xlsx']);
        self::assertCount(1, $result['рекомендуемые розничные цены.xlsx']);
    }

    private function row(string $name, string $article): array
    {
        return [
            'name' => $name,
            'article' => $article,
            'norm_article' => $article,
            'normalized_name' => $this->invoke('normalizeName', $name),
        ];
    }

    private function invoke(string $method, mixed ...$arguments): mixed
    {
        $reflection = new ReflectionMethod(SyncBaniaPricelistCommand::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke(new SyncBaniaPricelistCommand(), ...$arguments);
    }
}
