<?php

namespace Tests\Unit;

use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

class SupplierContactEmailTest extends TestCase
{
    public function test_it_reads_emails_from_both_supported_channel_formats(): void
    {
        $supplier = new Supplier([
            'contact' => 'Отдел закупок: legacy@example.test',
            'communication_channels' => [
                'email' => ['orders@example.test'],
                ['type' => 'email', 'value' => 'manager@example.test'],
            ],
        ]);
        $supplier->setRelation('crmCounterparty', null);

        $this->assertSame([
            'orders@example.test',
            'manager@example.test',
            'legacy@example.test',
        ], $supplier->contactEmailAddresses());
    }

    public function test_it_prefers_primary_crm_email_and_uses_supplier_channel_as_fallback(): void
    {
        $counterparty = new CrmCounterparty();
        $counterparty->setRelation('contactPoints', new Collection([
            new CrmContactPoint([
                'type' => 'email',
                'value' => 'secondary@example.test',
                'is_primary' => false,
                'is_active' => true,
            ]),
            new CrmContactPoint([
                'type' => 'email',
                'value' => 'primary@example.test',
                'is_primary' => true,
                'is_active' => true,
            ]),
        ]));

        $supplier = new Supplier([
            'communication_channels' => [[
                'type' => 'email',
                'value' => 'info@teplo.by',
            ]],
        ]);
        $supplier->setRelation('crmCounterparty', $counterparty);

        $this->assertSame([
            'primary@example.test',
            'secondary@example.test',
            'info@teplo.by',
        ], $supplier->contactEmailAddresses());

        $counterparty->setRelation('contactPoints', new Collection());

        $this->assertSame('info@teplo.by', $supplier->contactEmailAddresses()[0]);
    }
}
