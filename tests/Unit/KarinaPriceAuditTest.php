<?php

namespace Tests\Unit;

use App\Services\PriceList\KarinaPriceAudit;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

class KarinaPriceAuditTest extends TestCase
{
    public function test_workbook_discount_and_preamble_are_handled_without_exporting_contacts(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'karina-audit-');
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([
            ['private@example.test', '+375291234567', ''],
            ['Артикул', 'Наименование товара Поставщика', 'Рекомендованная розничная цена, руб. с НДС'],
            ['K-6', 'KARINA 6', 999.99],
            ['K-9', 'KARINA 9', '1 200,00'],
            ['K-X', 'private@example.test', 100],
            ['K-0', 'KARINA 0', 0],
        ], null, 'A1');
        try {
            (new Xlsx($book))->save($path);
            $report = (new KarinaPriceAudit)->inspect($path);
            $this->assertCount(2, $report['items']);
            $this->assertSame(749.99, $report['items'][0]['purchase']);
            $this->assertSame(900.0, $report['items'][1]['purchase']);
            $this->assertSame(2, $report['sheets'][0]['header_row']);
            $this->assertSame([5, 6], $report['sheets'][0]['excluded_rows']);
            $this->assertStringNotContainsString('private@example.test', json_encode($report));
            $this->assertStringNotContainsString('+375291234567', json_encode($report));
        } finally {
            $book->disconnectWorksheets();
            unlink($path);
        }
    }

    public function test_ambiguous_skus_conflicting_names_and_shared_targets_are_never_silently_chosen(): void
    {
        $audit = new KarinaPriceAudit;
        $products = [
            ['id' => 1, 'sku' => 'K-6', 'name' => 'KARINA 6'],
            ['id' => 2, 'sku' => 'K-6', 'name' => 'KARINA 9'],
        ];
        $rows = $audit->match([
            ['article' => 'K-6', 'name' => 'KARINA 6'],
            ['article' => 'K-6', 'name' => 'KARINA 6'],
            ['article' => 'NEW', 'name' => 'KARINA 12'],
        ], $products);
        $this->assertSame('ambiguous', $rows[0]['match_status']);
        $this->assertSame([1, 2], $rows[0]['candidate_product_ids']);
        $this->assertTrue($rows[0]['duplicate_article']);
        $this->assertTrue($rows[0]['duplicate_name']);
        $this->assertTrue($rows[0]['shared_target']);
        $this->assertSame('unmatched', $rows[2]['match_status']);

        $conflict = $audit->match([['article' => 'K-6', 'name' => 'KARINA 9']], [
            ['id' => 1, 'sku' => 'K-6', 'name' => 'KARINA 6'],
            ['id' => 2, 'sku' => 'K-9', 'name' => 'KARINA 9'],
        ]);
        $this->assertSame('ambiguous', $conflict[0]['match_status']);
    }

    public function test_exact_name_keeps_power_and_model_digits_and_allows_missing_article_only_as_review_candidate(): void
    {
        $audit = new KarinaPriceAudit;
        $rows = $audit->match([
            ['article' => '', 'name' => '  KARINA   6  '],
            ['article' => '', 'name' => 'KARINA 9'],
        ], [['id' => 1, 'sku' => 'K-6', 'name' => 'KARINA 6']]);
        $this->assertSame('exact_candidate', $rows[0]['match_status']);
        $this->assertSame('exact_name', $rows[0]['match_method']);
        $this->assertSame('unmatched', $rows[1]['match_status']);
    }

    public function test_unknown_layout_is_reported_without_guessing_price_columns(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'karina-audit-');
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([
            ['Наименование', 'РРЦ', 'Цена'],
            ['KARINA 6', 1000, 750],
        ]);
        try {
            (new Xlsx($book))->save($path);
            $report = (new KarinaPriceAudit)->inspect($path);
            $this->assertSame([], $report['items']);
            $this->assertNull($report['sheets'][0]['header_row']);
        } finally {
            $book->disconnectWorksheets();
            unlink($path);
        }
    }
}
