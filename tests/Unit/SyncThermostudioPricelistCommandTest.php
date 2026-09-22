<?php

namespace Tests\Unit;

use App\Console\Commands\SyncThermostudioPricelistCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
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
}
