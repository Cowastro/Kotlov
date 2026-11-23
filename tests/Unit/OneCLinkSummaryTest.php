<?php

namespace Tests\Unit;

use App\Models\IntegrationProduct;
use App\Services\Integrations\OneCBridge\OneCItemLinks;
use Tests\TestCase;

class OneCLinkSummaryTest extends TestCase
{
    public function test_a_legacy_parent_link_is_a_conflict_but_does_not_inflate_characteristic_count(): void
    {
        $key = 'a0000000-0000-0000-0000-000000000001';
        $rows = collect([
            new IntegrationProduct(['external_id' => $key, 'product_id' => 1, 'match_status' => 'matched']),
            new IntegrationProduct(['external_id' => $key.'#b0000000-0000-0000-0000-000000000001', 'product_id' => 1, 'match_status' => 'matched']),
            new IntegrationProduct(['external_id' => $key.'#b0000000-0000-0000-0000-000000000002', 'product_id' => 2, 'match_status' => 'matched']),
        ]);
        $summary = OneCItemLinks::summary($rows);
        $this->assertTrue($summary['link_conflict']);
        $this->assertNull($summary['product_id']);
        $this->assertSame(2, $summary['characteristic_count']);
        $this->assertSame(2, $summary['site_link_count']);
        $this->assertSame(1, $rows->first()->product_id);
    }
}
