<?php

namespace Tests\Unit;

use App\Console\Commands\SyncHomeTechnologiesPriceListCommand;
use App\Models\Product;
use App\Services\Suppliers\HomeTechnologiesPriceList;
use ReflectionMethod;
use Tests\TestCase;

class HomeTechnologiesPriceListTest extends TestCase
{
    public function test_normalized_snapshot_contains_all_sections_and_prices(): void
    {
        $payload = (new HomeTechnologiesPriceList)->load(
            base_path('resources/data/suppliers/home-technologies-2026-09.json')
        );

        $this->assertCount(229, $payload['rows']);
        $this->assertSame(141, collect($payload['rows'])->whereNotNull('retail')->count());
        $this->assertSame(88, collect($payload['rows'])->whereNull('retail')->count());
        $this->assertSame(2, collect($payload['rows'])->where('discontinued', true)->count());
        $this->assertEqualsCanonicalizing([
            'Электрический теплый пол', 'Терморегуляторы', 'Труба Stahlmann', 'Системы Neptun', 'Арх. обогрев',
        ], collect($payload['rows'])->pluck('section')->unique()->values()->all());
    }

    public function test_retail_plan_uses_supplier_rrp_market_price_and_safe_margin(): void
    {
        $method = new ReflectionMethod(SyncHomeTechnologiesPriceListCommand::class, 'retailPlan');
        $command = new SyncHomeTechnologiesPriceListCommand;

        $rrp = $method->invoke($command, ['name_key' => 'mat', 'wholesale' => 60, 'retail' => 100], null);
        $market = $method->invoke($command, ['name_key' => 'termoregulyatorsmm9050', 'wholesale' => 68, 'retail' => null], null);
        $margin = $method->invoke($command, ['name_key' => 'stahlmann15a', 'wholesale' => 8, 'retail' => null], null);
        $preserved = $method->invoke($command, ['name_key' => 'stahlmann15a', 'wholesale' => 8, 'retail' => null], new Product(['price' => 15]));

        $this->assertSame(['price' => 100.0, 'method' => 'supplier_rrp', 'update' => true], $rrp);
        $this->assertSame(['price' => 68.0, 'method' => 'verified_market_price', 'update' => true], $market);
        $this->assertSame(['price' => 10.0, 'method' => 'standard_target_margin_20', 'update' => true], $margin);
        $this->assertSame(['price' => 15.0, 'method' => 'existing_retail_preserved', 'update' => false], $preserved);
    }

    public function test_model_key_normalization_handles_cyrillic_and_latin_variants(): void
    {
        $parser = new HomeTechnologiesPriceList;

        $this->assertSame('smm9050', $parser->key('SMM-9050'));
        $this->assertContains('smm9050', $parser->modelKeys('Терморегулятор Grand Meyer SMM-9050'));
    }
}
