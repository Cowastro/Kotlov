<?php

namespace Tests\Unit;

use App\Console\Commands\SyncAkvatermexCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

class SyncAkvatermexCommandTest extends TestCase
{
    #[DataProvider('modelKeyProvider')]
    public function test_it_normalizes_catalog_and_supplier_names_to_the_same_model_key(
        string $catalogName,
        string $supplierName,
        string $expected,
    ): void {
        $this->assertSame($expected, $this->invoke('modelKey', [$catalogName, 'Thermex']));
        $this->assertSame($expected, $this->invoke('modelKey', [$supplierName, 'Thermex']));
    }

    public static function modelKeyProvider(): array
    {
        return [
            ['Накопительный водонагреватель Thermex IC 10 O', 'THERMEX IC 10 O', 'ic 10 o'],
            ['Водонагреватель аккумуляционный электрический бытовой THERMEX Nova 80 V', 'THERMEX Nova 80V', 'nova 80 v'],
            ['Водонагреватель электрический THERMEX Thermo 30 V Slim', 'Thermex Thermo 30V Slim', 'thermo 30 v slim'],
            ['Газовый котел Thermex Xantus HM24', 'THERMEX Xantus HM24', 'xantus hm 24'],
            ['Газовый котел Thermex EuroStyle F 24 (дымоход в подарок)', 'THERMEX EuroStyle F24', 'eurostyle f 24'],
        ];
    }

    public function test_it_rejects_competing_supplier_rows_for_one_product(): void
    {
        $rows = [
            ['matched_product_id' => 12001, 'action' => 'matched', 'match_confidence' => 'brand_model'],
            ['matched_product_id' => 12001, 'action' => 'matched', 'match_confidence' => 'brand_model'],
            ['matched_product_id' => 11450, 'action' => 'matched', 'match_confidence' => 'brand_model'],
        ];

        $result = $this->invoke('rejectDuplicateProductMatches', [$rows]);

        $this->assertSame('duplicate_product_match', $result[0]['action']);
        $this->assertSame('duplicate_product_match', $result[1]['action']);
        $this->assertNull($result[0]['matched_product_id']);
        $this->assertSame(11450, $result[2]['matched_product_id']);
    }

    private SyncAkvatermexCommand $command;

    protected function setUp(): void
    {
        parent::setUp();
        $this->command = new SyncAkvatermexCommand();
    }

    private function invoke(string $method, array $arguments): mixed
    {
        $reflection = new ReflectionClass($this->command);

        return $reflection->getMethod($method)->invokeArgs($this->command, $arguments);
    }
}
