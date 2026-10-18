<?php

namespace Tests\Unit;

use App\Filament\Resources\SupplierSyncRuns\SupplierSyncRunResource;
use PHPUnit\Framework\TestCase;

class SupplierSyncRunNavigationTest extends TestCase
{
    public function test_sync_journal_is_grouped_with_integrations(): void
    {
        $this->assertSame('Интеграции', SupplierSyncRunResource::getNavigationGroup());
    }
}
