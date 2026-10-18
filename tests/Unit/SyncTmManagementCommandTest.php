<?php

namespace Tests\Unit;

use App\Console\Commands\SyncTmManagementCommand;
use ReflectionClass;
use Tests\TestCase;

class SyncTmManagementCommandTest extends TestCase
{
    public function test_it_never_uses_wholesale_as_a_missing_retail_price(): void
    {
        $this->assertNull($this->invoke('safeRetailPrice', [null, 345.0]));
        $this->assertNull($this->invoke('safeRetailPrice', [300.0, 345.0]));
        $this->assertSame(345.0, $this->invoke('safeRetailPrice', [345.0, 345.0]));
        $this->assertSame(430.0, $this->invoke('safeRetailPrice', [430.0, 345.0]));
    }

    public function test_it_reads_explicit_presence_without_inventing_a_quantity(): void
    {
        $this->assertSame([
            'in_stock' => true,
            'status' => 'available_without_quantity',
            'text' => 'на складе',
        ], $this->invoke('availabilityFromText', ['на складе']));

        $this->assertSame([
            'in_stock' => false,
            'status' => 'unknown',
            'text' => 'Остаток в прайсе не указан',
        ], $this->invoke('availabilityFromText', ['']));
    }

    public function test_price_refresh_preserves_rich_content_and_real_specs(): void
    {
        $product = (object) [
            'content' => '<p>Подробное описание производителя</p>',
            'short_description' => 'Полезное краткое описание',
            'specs' => json_encode([['key' => 'Мощность', 'value' => '400', 'unit' => 'Вт']]),
        ];
        $item = [
            'name' => 'Насос', 'brand' => 'SFA', 'category_name' => 'Насосы',
            'article' => 'SFA-1', 'section' => 'Насосы', 'price_opt' => 100, 'price_retail' => 120,
        ];

        $this->assertSame([], $this->invoke('contentPayload', [$product, $item, null]));
    }

    public function test_price_refresh_fills_missing_content_and_meta_only_specs(): void
    {
        $product = (object) [
            'content' => null,
            'short_description' => null,
            'specs' => json_encode(['Бренд' => 'SFA', 'Цена РРЦ, BYN' => 120]),
        ];
        $item = [
            'name' => 'Насос', 'brand' => 'SFA', 'category_name' => 'Насосы',
            'article' => 'SFA-1', 'section' => 'Насосы', 'price_opt' => 100, 'price_retail' => 120,
        ];

        $payload = $this->invoke('contentPayload', [$product, $item, null]);

        $this->assertArrayHasKey('content', $payload);
        $this->assertArrayHasKey('short_description', $payload);
        $this->assertArrayHasKey('specs', $payload);
    }

    private function invoke(string $method, array $arguments): mixed
    {
        $command = new SyncTmManagementCommand();
        $reflection = new ReflectionClass($command);

        return $reflection->getMethod($method)->invokeArgs($command, $arguments);
    }
}
