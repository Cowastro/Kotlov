<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Models\User;
use App\Services\Integrations\PricelessStockPriceProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class PricelessStockPriceProposalTest extends TestCase
{
    use RefreshDatabase;

    private IntegrationSource $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->source = IntegrationSource::query()->updateOrCreate(['code' => 'onec'], ['name' => '1C']);
    }

    public function test_retail_is_checked_against_the_purchase_and_wholesale_is_retail_minus_twenty_percent(): void
    {
        $proposal = app(PricelessStockPriceProposal::class);

        $ok = $proposal->proposeRow($this->row('a'), $this->card(111, 70));
        $this->assertSame('ok', $ok['verdict']);
        $this->assertSame(111.0, $ok['retail']);
        $this->assertSame(88.8, $ok['wholesale']);

        $low = $proposal->proposeRow($this->row('b'), $this->card(100, 95));
        $this->assertSame('raised', $low['verdict']);
        $this->assertSame(123.5, $low['retail']);

        $tight = $proposal->proposeRow($this->row('c'), $this->card(120, 100));
        $this->assertSame('ok', $tight['verdict']);
        $this->assertSame(105.0, $tight['wholesale']);
        $this->assertStringContainsString('Опт поднят до закупки +5 %', $tight['note']);

        $computed = $proposal->proposeRow($this->row('d'), $this->card(0, 50));
        $this->assertSame('computed', $computed['verdict']);
        $this->assertSame(65.0, $computed['retail']);

        $high = $proposal->proposeRow($this->row('e'), $this->card(300, 100));
        $this->assertSame('check', $high['verdict']);

        $this->assertSame('need_cost', $proposal->proposeRow($this->row('f'), null)['verdict']);
        $this->assertSame('need_cost', $proposal->proposeRow($this->row('g'), $this->card(0, 0))['verdict']);
    }

    public function test_the_1c_file_keeps_the_export_layout_and_only_checked_rows_and_managers_download_it(): void
    {
        Storage::fake('local');
        $this->row('7b0fbdc2-0000-11e9-bad6-1c1b0d1de7c2', $this->card(111, 70), 'Бак-ватерпасс 304 d180, РФ', 'TS.BAK.BWP.0000.79508');
        $this->row('7b0fbdc2-0001-11e9-bad6-1c1b0d1de7c2', null, 'Без карточки');
        $this->row('not-a-guid', $this->card(50, 30), 'Не GUID');

        $this->artisan('integration:propose-priceless-prices', ['--file' => 'test-prices'])
            ->expectsOutputToContain('metric.price_proposal.loaded=1')
            ->expectsOutputToContain('metric.price_proposal.need_cost=1')
            ->assertSuccessful();

        $tmp = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        file_put_contents($tmp, Storage::disk('local')->get('price-proposals/test-prices.xlsx'));
        $book = IOFactory::load($tmp);
        $types = $book->getSheetByName('Лист1')->toArray();
        $this->assertEquals(['de262f38-66a1-11e9-9b7c-1c1b0d1de7c2', 'Оптовая цена', 12, 14, 15, 16, 17], array_slice($types[2], 0, 7));
        $load = $book->getSheetByName('Лист_1')->toArray();
        $this->assertCount(3, $load);
        $row = $load[2];
        $this->assertSame('TS.BAK.BWP.0000.79508', $row[1]);
        $this->assertSame('7b0fbdc2-0000-11e9-bad6-1c1b0d1de7c2', $row[2]);
        $this->assertEqualsWithDelta(92.5, (float) $row[8], 0.001);   // Розничный тип цен (без НДС)
        $this->assertEqualsWithDelta(74.0, (float) $row[14], 0.001);  // Оптовая цена (без НДС)
        $this->assertEqualsWithDelta(88.8, (float) $row[20], 0.001);  // Оптовая с НДС
        $this->assertEqualsWithDelta(111.0, (float) $row[26], 0.001); // Розничная с НДС
        $this->assertCount(4, $book->getSheetByName('Анализ')->toArray());
        @unlink($tmp);

        $this->get('/admin/price-proposals/test-prices.xlsx')->assertRedirect();
        $this->actingAs(User::factory()->create(['role' => 'client', 'is_active' => true]))->get('/admin/price-proposals/test-prices.xlsx')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'manager', 'is_active' => true]))->get('/admin/price-proposals/test-prices.xlsx')->assertOk();
    }

    private function card(float $retail, float $purchase): Product
    {
        $category = Category::query()->firstOrCreate(['slug' => 'kotly'], ['name' => 'Котлы', 'is_active' => true]);

        return Product::query()->create(['category_id' => $category->id, 'name' => 'Карточка '.uniqid(), 'slug' => 'card-'.uniqid(),
            'price' => $retail, 'manual_purchase_price_byn' => $purchase ?: null]);
    }

    private function row(string $externalId, ?Product $card = null, string $name = 'Товар', ?string $sku = null): IntegrationProduct
    {
        return IntegrationProduct::query()->create(['integration_source_id' => $this->source->id, 'external_id' => $externalId,
            'external_code' => 'УТ-'.substr(md5($externalId), 0, 6), 'external_sku' => $sku, 'name' => $name, 'stock_quantity' => 2, 'price' => 0,
            'product_id' => $card?->id, 'match_status' => $card ? 'matched' : 'unmatched']);
    }
}
