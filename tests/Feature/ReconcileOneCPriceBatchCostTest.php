<?php

namespace Tests\Feature;

use App\Models\OneCPriceBatch;
use App\Services\Integrations\OneCBridge\OneCPurchaseCosts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReconcileOneCPriceBatchCostTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_confirmed_costs_change_and_a_partial_match_is_rolled_back(): void
    {
        $batch = OneCPriceBatch::create(['status' => OneCPriceBatch::CREATED]);
        foreach (['G4', 'G6'] as $code) {
            $batch->items()->create(['code' => $code, 'item_key' => (string) Str::uuid(), 'name' => $code,
                'retail' => 250, 'wholesale' => 192.65, 'purchase' => 208.50]);
        }
        $first = $batch->items()->where('code', 'G4')->firstOrFail();
        DB::table('onec_purchase_lines')->insert(['doc_number' => 'R1', 'doc_date' => '2026-11-24 15:11:15',
            'doc_key' => (string) Str::uuid(), 'line_number' => 1, 'currency_key' => OneCPurchaseCosts::BYN,
            'item_key' => $first->item_key, 'quantity' => 7, 'price_net' => 147.69, 'price_gross' => 177.2286]);
        $args = ['batch' => $batch->id, 'receipt' => 'R1', 'date' => '2026-11-24', '--code' => ['G4', 'G6']];
        $this->artisan('onec:reconcile-batch-cost', $args + ['--apply' => true])->assertFailed();
        $args['--code'] = ['G4'];
        $this->artisan('onec:reconcile-batch-cost', $args)->assertSuccessful();
        $this->assertNull($first->fresh()->purchase_net);
        $this->artisan('onec:reconcile-batch-cost', $args + ['--apply' => true])->assertSuccessful();
        $this->assertDatabaseHas('onec_price_batch_items', ['id' => $first->id, 'purchase' => 177.23,
            'purchase_net' => 147.69, 'retail' => 250, 'wholesale' => 192.65]);
        $this->assertNull($batch->items()->where('code', 'G6')->firstOrFail()->purchase_net);
    }
}
