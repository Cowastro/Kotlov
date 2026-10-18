<?php

namespace Tests\Unit;

use App\Console\Commands\SyncThermostudioPricelistCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Symfony\Component\Console\Input\ArrayInput;
use Tests\TestCase;

class SyncThermostudioPricelistCommandTest extends TestCase
{
    #[DataProvider('modelCodeProvider')]
    public function test_it_extracts_stable_brand_model_codes(string $value, string $brand, string $expected): void
    {
        $this->assertSame($expected, $this->invoke('brandModelCode', [$value, $brand]));
    }

    public static function modelCodeProvider(): array
    {
        return [
            ['Газовый котел Vaillant turboTEC pro VUW 242/5-3', 'Vaillant', 'TURBOTEC|VUW242/5-3'],
            ['ecoTEC VU 656/5-5', 'Vaillant', 'ECOTEC|VU656/5-5'],
            ['Дымоход Vaillant 80/125 PP 303209', 'Vaillant', 'ARTICLE303209'],
            ['23 MOV Гепард, атмо', 'Protherm', '23MOV'],
            ['Газовый котел Protherm Гепард 23 MOV', 'Protherm', '23MOV'],
            ['Lynx 24 "Рысь"', 'Protherm', 'LYNX24'],
            ['Газовый котел Protherm Рысь 24', 'Protherm', 'LYNX24'],
            ['12КЕ "Скат" c Wilo Para', 'Protherm', 'SKAT12'],
            ['Электрический котел Protherm Скат 12K (Ray)', 'Protherm', 'SKAT12'],
        ];
    }

    public function test_it_converts_only_eur_cells_and_preserves_byn_cells(): void
    {
        $reflection = new ReflectionClass($this->command());
        $property = $reflection->getProperty('resolvedEurRate');
        $property->setValue($this->command(), 3.4694);

        $this->assertSame(4000.0, $this->invoke('priceInByn', ['4 000 Br', '#,##0 [$Br-423]', 'EUR']));
        $this->assertSame(3573.48, $this->invoke('priceInByn', ['1030', '#,##0', 'EUR']));
    }

    public function test_it_rejects_competing_rows_for_one_product(): void
    {
        $rows = [
            ['matched_product_id' => 555, 'action' => 'matched', 'match_confidence' => 'brand_model_code'],
            ['matched_product_id' => 555, 'action' => 'matched', 'match_confidence' => 'brand_model_code'],
            ['matched_product_id' => 556, 'action' => 'matched', 'match_confidence' => 'brand_model_code'],
        ];

        $result = $this->invoke('rejectDuplicateProductMatches', [$rows]);

        $this->assertNull($result[0]['matched_product_id']);
        $this->assertNull($result[1]['matched_product_id']);
        $this->assertSame('duplicate_product_match', $result[0]['action']);
        $this->assertSame(556, $result[2]['matched_product_id']);
    }

    #[DataProvider('stockProvider')]
    public function test_it_normalises_stock_into_shared_availability_semantics(string $value, string $status, ?int $quantity): void
    {
        $this->assertSame(
            ['status' => $status, 'quantity' => $quantity],
            $this->invoke('stock', [$value]),
        );
    }

    public static function stockProvider(): array
    {
        return [
            ['есть', 'in_stock', null],
            ['отсутствует', 'out_of_stock', 0],
            ['нет в наличии', 'out_of_stock', 0],
            ['под заказ', 'preorder', null],
            ['', 'unknown', null],
            ['5', 'in_stock', 5],
        ];
    }

    public function test_only_linked_mode_rejects_name_only_catalog_matches(): void
    {
        $reflection = new ReflectionClass($this->command());
        $reflection->getProperty('indexByProductName')->setValue($this->command(), ['test model' => 777]);
        $this->setInput(['--only-linked' => true]);

        $classified = $this->invoke('classify', [[
            'brand' => 'Test',
            'sheet' => 'Test',
            'section' => '',
            'supplier_article' => 'UNLINKED',
            'supplier_article_raw' => 'UNLINKED',
            'name' => 'Test Model',
            'description' => '',
            'norm_article' => 'UNLINKED',
            'ambiguous_variant' => false,
        ]]);

        $this->assertSame('skip_unlinked', $classified['action']);
        $this->assertNull($classified['matched_product_id']);
    }

    public function test_only_linked_mode_keeps_confirmed_supplier_article_matches(): void
    {
        $reflection = new ReflectionClass($this->command());
        $reflection->getProperty('indexBySupplierArticle')->setValue($this->command(), ['CONFIRMED' => 778]);
        $this->setInput(['--only-linked' => true]);

        $classified = $this->invoke('classify', [[
            'brand' => 'Test',
            'sheet' => 'Test',
            'section' => '',
            'supplier_article' => 'CONFIRMED',
            'supplier_article_raw' => 'CONFIRMED',
            'name' => 'Confirmed Model',
            'description' => '',
            'norm_article' => 'CONFIRMED',
            'ambiguous_variant' => false,
        ]]);

        $this->assertSame('matched', $classified['action']);
        $this->assertSame(778, $classified['matched_product_id']);
        $this->assertSame('supplier_article', $classified['match_confidence']);
    }

    public function test_it_keeps_coaxial_chimneys_in_their_own_category(): void
    {
        $this->setCategoryMap([
            'koaxial-dymoxod' => 57,
            'gazovye' => 53,
            'truby-mono' => 309,
            'prochie-dymohod' => 322,
        ]);

        $this->assertSame(57, $this->invoke('categoryId', [[
            'sheet' => 'Газовые котлы',
            'section' => 'Дымоходы',
            'supplier_article' => '',
            'supplier_article_raw' => '',
            'name' => 'Ferroli Коаксиальный дымоход 60/100 универсальный',
            'description' => '',
        ]]));
    }

    public function test_it_excludes_the_blist_half_metre_serbian_pipe_from_import(): void
    {
        $row = [
            'brand' => 'Blist',
            'sheet' => 'Дымоходы',
            'section' => 'Одностенные дымоходы',
            'supplier_article' => '',
            'supplier_article_raw' => '',
            'name' => 'Blist Труба 0,5м, Сербия',
            'description' => '',
        ];

        $classified = $this->invoke('classify', [$row]);

        $this->assertSame('excluded_archive', $classified['action']);
        $this->assertNull($classified['matched_product_id']);
        $this->assertNull($classified['category_id']);
    }

    private ?SyncThermostudioPricelistCommand $instance = null;

    private function command(): SyncThermostudioPricelistCommand
    {
        return $this->instance ??= new SyncThermostudioPricelistCommand;
    }

    private function invoke(string $method, array $arguments): mixed
    {
        $reflection = new ReflectionClass($this->command());
        $target = $reflection->getMethod($method);

        return $target->invokeArgs($this->command(), $arguments);
    }

    private function setCategoryMap(array $categories): void
    {
        $reflection = new ReflectionClass($this->command());
        $reflection->getProperty('categoryBySlug')->setValue($this->command(), $categories);
    }

    private function setInput(array $options): void
    {
        $input = new ArrayInput($options, $this->command()->getDefinition());
        $input->bind($this->command()->getDefinition());
        $this->command()->setInput($input);
    }
}
