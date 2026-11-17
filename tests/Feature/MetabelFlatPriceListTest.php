<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class MetabelFlatPriceListTest extends TestCase
{
    use RefreshDatabase;

    private function priceFile(): string
    {
        $sheet = (new Spreadsheet())->getActiveSheet();
        $sheet->fromArray([
            [null, '2026-11-16'],
            ['Наименование', 'Цена продажи'],
            ['Печь-камин АОТК 6.0   (в модификации "Ока с плитой")', 1755],
            ['Печь-камин АОТ 7.0 (в модификации  "Ритм L")', 1890],
            ['Труба 1 м', 180],
        ]);
        $file = tempnam(sys_get_temp_dir(), 'mb').'.xlsx';
        (new Xlsx($sheet->getParent()))->save($file);

        return $file;
    }

    private function card(string $name, string $slug, float $price): int
    {
        $now = now();
        if (! DB::table('brands')->where('id', 45)->exists()) {
            DB::table('brands')->insert(['id' => 45, 'name' => 'Мета', 'slug' => 'meta-flat-test', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        $categoryId = DB::table('categories')->where('slug', 'pechi-kaminy-flat-test')->value('id')
            ?? DB::table('categories')->insertGetId(['name' => 'Печи-камины', 'slug' => 'pechi-kaminy-flat-test', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);

        return DB::table('products')->insertGetId([
            'category_id' => $categoryId, 'brand_id' => 45, 'name' => $name, 'slug' => $slug, 'sku' => strtoupper($slug),
            'price' => $price, 'currency' => 'BYN', 'is_active' => true, 'is_archived' => false, 'in_stock' => false,
            'availability_status' => 'check', 'requires_price_confirmation' => false, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function test_flat_list_updates_found_cards_and_creates_nothing(): void
    {
        $ritm = $this->card('Печь-камин Мета Ритм L', 'meta-ritm-l-test', 1700);
        $before = DB::table('products')->count();
        $file = $this->priceFile();

        $this->artisan('supplier:sync-metabel', ['--price-file' => $file, '--only-existing' => true])
            ->expectsOutputToContain("audit.metabel_sync.row=ritm-l:{$ritm}:1700:1890:update:none")
            ->expectsOutputToContain('audit.metabel_sync.row=truba-1-m:0:0:180:no_card:none')
            ->assertSuccessful();
        $this->assertSame(1700.0, (float) DB::table('products')->where('id', $ritm)->value('price'));

        $this->artisan('supplier:sync-metabel', ['--price-file' => $file, '--only-existing' => true, '--apply' => true, '--no-scrape' => true, '--no-images' => true])
            ->expectsOutputToContain('metric.metabel_sync.skipped_no_card=')
            ->assertSuccessful();

        $this->assertSame(1890.0, (float) DB::table('products')->where('id', $ritm)->value('price'));
        $this->assertSame($before, DB::table('products')->count());
        $link = DB::table('supplier_products')->where('product_id', $ritm)->first();
        $this->assertNull($link->price_byn);
        $this->assertSame(1890.0, (float) json_decode($link->raw, true)['retail_byn']);
    }

    public function test_a_row_linked_by_hand_updates_its_card(): void
    {
        $ok = $this->card('Печь-камин Мета-Бел Ока с варочной панелью', 'meta-oka-plate-test', 1680);
        $file = $this->priceFile();

        $this->artisan('supplier:sync-metabel', ['--price-file' => $file, '--only-existing' => true, '--apply' => true, '--no-scrape' => true,
            '--no-images' => true, '--link' => ["oka-s-plitoi:{$ok}"]])
            ->expectsOutputToContain("audit.metabel_sync.row=oka-s-plitoi:{$ok}:1680:1755:update:none")
            ->assertSuccessful();
        $this->assertSame(1755.0, (float) DB::table('products')->where('id', $ok)->value('price'));
        $this->assertTrue(DB::table('supplier_products')->where('product_id', $ok)->where('supplier_article', 'ОКА С ПЛИТОЙ')->exists());
    }
}
