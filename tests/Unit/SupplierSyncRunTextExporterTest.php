<?php

namespace Tests\Unit;

use App\Models\SupplierSyncChange;
use App\Models\SupplierSyncRun;
use App\Services\SupplierSyncRunTextExporter;
use Illuminate\Support\Collection;
use Tests\TestCase;

class SupplierSyncRunTextExporterTest extends TestCase
{
    public function test_it_exports_readable_price_and_stock_changes(): void
    {
        $run = new SupplierSyncRun([
            'command' => 'supplier:sync-ligmet',
            'status' => 'success',
            'started_at' => '2026-09-29 13:31:18',
            'changes_count' => 1,
            'price_changes_count' => 1,
            'stock_changes_count' => 1,
            'supplier_names' => 'Лигмет',
        ]);
        $run->setRelation('changes', new Collection([
            new SupplierSyncChange([
                'product_name' => 'Печь Berna Lux S',
                'supplier_name' => 'Лигмет',
                'product_sku' => 'KOTLOV-001',
                'supplier_article' => '994549920',
                'change_flags' => ['retail_price', 'in_stock'],
                'retail_price_before' => 500,
                'retail_price_after' => 560,
                'in_stock_before' => false,
                'in_stock_after' => true,
            ]),
        ]));

        $export = app(SupplierSyncRunTextExporter::class)->render($run);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $export);
        $this->assertStringContainsString('Печь Berna Lux S', $export);
        $this->assertStringContainsString('Розничная цена: 500,00 BYN → 560,00 BYN', $export);
        $this->assertStringContainsString('Наличие: нет → есть', $export);
    }

    public function test_it_builds_a_safe_text_filename(): void
    {
        $run = new SupplierSyncRun([
            'command' => 'supplier:sync-ligmet',
            'started_at' => '2026-09-29 13:31:18',
        ]);

        $this->assertSame(
            'supplier-sync-ligmet-2026-09-29_13-31-18.txt',
            app(SupplierSyncRunTextExporter::class)->filename($run)
        );
    }
}
