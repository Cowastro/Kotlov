<?php

namespace Tests\Unit;

use App\Services\PriceList\KarinaPriceAudit;
use App\Services\Pricing\KarinaPricing;
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
            ['Op-7,5-220/380', 'KARINA Optima 7,5', 1000],
        ], null, 'A1');
        try {
            (new Xlsx($book))->save($path);
            $report = (new KarinaPriceAudit)->inspect($path);
            $this->assertCount(3, $report['items']);
            $this->assertSame('Op-7,5-220/380', $report['items'][2]['article']);
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

    public function test_rub_rate_uses_nbrb_scale_and_keeps_site_retail_above_purchase(): void
    {
        $evidence = KarinaPricing::rateEvidence([
            'Cur_Abbreviation' => 'RUB', 'Cur_OfficialRate' => 3.85,
            'Cur_Scale' => 100, 'Date' => '2026-10-23T00:00:00',
        ], '2026-10-23');
        $this->assertSame(0.0385, $evidence['byn_per_rub']);
        $quote = KarinaPricing::quote(40200, $evidence['byn_per_rub']);
        $this->assertSame(30150.0, $quote['purchase_rub']);
        $this->assertSame(1547.70, $quote['retail_byn']);
        $this->assertSame(1160.78, $quote['purchase_byn']);
        $this->assertGreaterThan($quote['purchase_byn'], $quote['retail_byn']);
    }

    public function test_stale_rate_is_rejected_without_a_silent_fallback(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        KarinaPricing::rateEvidence([
            'Cur_Abbreviation' => 'RUB', 'Cur_OfficialRate' => 3.85,
            'Cur_Scale' => 100, 'Date' => '2026-10-01T00:00:00',
        ], '2026-10-23');
    }

    public function test_missing_currency_scale_is_rejected_instead_of_using_a_hundredfold_rate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        KarinaPricing::rateEvidence([
            'Cur_Abbreviation' => 'RUB', 'Cur_OfficialRate' => 3.85,
            'Date' => '2026-10-23T00:00:00',
        ], '2026-10-23');
    }

    public function test_site_rrp_is_read_from_source_instead_of_discounted_purchase(): void
    {
        $this->assertSame(40200.0, KarinaPricing::retailRubFromRaw([
            'Рекомендованная розничная цена, руб. с НДС' => '40 200,00',
        ]));
        $this->assertSame(40200.0, KarinaPricing::retailRubFromRaw(json_encode([
            'karina' => ['retail_rub' => 40200], 'purchase' => 30150,
        ])));
    }

    public function test_missing_source_rrp_never_falls_back_to_purchase(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        KarinaPricing::retailRubFromRaw(['purchase' => 30150]);
    }
}
