<?php

namespace Tests\Unit;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Marketplaces\DealByCatalogAudit;
use App\Services\Marketplaces\DealByClient;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class DealByCatalogAuditTest extends TestCase
{
    public function test_it_counts_only_unique_high_confidence_matches_as_automatic(): void
    {
        $easy = $this->product(10, 'Tatra', 'Котлы');
        $duplicate = $this->product(20, 'Tatra', 'Котлы');
        $rows = [
            $this->row(10, 'sku', ''),
            $this->row(20, 'name', ''),
            $this->row(20, 'name', ''),
        ];

        $method = new ReflectionMethod(DealByCatalogAudit::class, 'linkabilityGroups');
        $audit = new DealByCatalogAudit($this->createStub(DealByClient::class));
        $groups = $method->invoke($audit, $rows, collect([$easy, $duplicate])->keyBy('id'), 'brand');

        $this->assertSame(1, $groups[0]['auto']);
        $this->assertSame(2, $groups[0]['conflicts']);
        $this->assertSame(3, $groups[0]['total']);
    }

    /** @return array<int, mixed> */
    private function row(int $localId, string $method, string $issues): array
    {
        return ['', '', '', '', '', '', '', '', $localId, $method, '', '', '', '', '', $issues];
    }

    private function product(int $id, string $brand, string $category): Product
    {
        $product = new Product();
        $product->id = $id;
        $product->setRelation('brand', new Brand(['name' => $brand]));
        $product->setRelation('category', new Category(['name' => $category]));

        return $product;
    }
}
