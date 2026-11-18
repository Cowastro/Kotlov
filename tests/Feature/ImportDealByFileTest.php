<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportDealByFileTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.deal_by.token' => 'test-token', 'services.deal_by.base_url' => 'https://deal.test/api/v1']);
        $sheet = (new Spreadsheet)->getActiveSheet();
        $sheet->fromArray([
            ['Код_товара', 'Название_позиции', 'Цена', 'Идентификатор_товара'],
            ['MB-1', 'Печь Ока', 1755, 'kotlov-101'],
            ['MB-2', 'Печь Енисей', 1890, 'kotlov-102'],
        ]);
        @mkdir(public_path('exports'), 0755, true);
        $this->file = public_path('exports/deal-import-test.xlsx');
        (new Xlsx($sheet->getParent()))->save($this->file);
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    public function test_dry_run_lists_rows_and_writes_nothing(): void
    {
        Http::fake(['deal.test/api/v1/products/list*' => Http::response(['products' => [['id' => 9, 'external_id' => 'kotlov-101']]])]);

        $this->artisan('deal:import-file', ['file' => 'deal-import-test'])
            ->expectsOutputToContain('audit.deal_import.row=101:on_shop')
            ->expectsOutputToContain('audit.deal_import.row=102:new')
            ->expectsOutputToContain('metric.deal_import.new=1')
            ->assertSuccessful();
        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
    }

    public function test_apply_imports_by_address_and_keeps_other_items(): void
    {
        $lists = 0;
        Http::fake([
            'deal.test/api/v1/products/list*' => function () use (&$lists) {
                return Http::response(['products' => $lists++ === 0
                    ? [['id' => 9, 'external_id' => 'kotlov-101']]
                    : [['id' => 9, 'external_id' => 'kotlov-101'], ['id' => 10, 'external_id' => 'kotlov-102']]]);
            },
            'deal.test/api/v1/products/import_url' => Http::response(['id' => 'imp-77', 'status' => 'success']),
            'deal.test/api/v1/products/import/status/imp-77' => Http::sequence()
                ->push(['status' => 'SUCCESS', 'total' => 0, 'created' => 0])
                ->push(['status' => 'SUCCESS', 'total' => 1, 'imported' => 1, 'created' => 1, 'errors' => []]),
        ]);

        $this->artisan('deal:import-file', ['file' => 'deal-import-test', '--apply' => true, '--wait' => 30])
            ->expectsOutputToContain('audit.deal_import.id=imp-77')
            ->expectsOutputToContain('audit.deal_import.status=success')
            ->expectsOutputToContain('metric.deal_import.created=1')
            ->expectsOutputToContain('metric.deal_import.added=1')
            ->expectsOutputToContain('metric.deal_import.still_missing=0')
            ->assertSuccessful();

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'products/import_url')
            && $request['mark_missing_product_as'] === 'none'
            && $request['updated_fields'] === ['price']
            && str_ends_with($request['url'], '/exports/deal-import-test.xlsx'));
        Http::assertSentCount(5);
    }
}
