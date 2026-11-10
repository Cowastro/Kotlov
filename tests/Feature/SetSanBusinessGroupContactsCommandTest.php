<?php

namespace Tests\Feature;

use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\CrmMailbox;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetSanBusinessGroupContactsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_wholesale_phone_and_info_mailbox_remain(): void
    {
        $counterparty = CrmCounterparty::query()->create(['name' => 'ООО «СанБизнесГруп»']);
        $supplier = Supplier::query()->updateOrCreate(['code' => 'sanbusinessgroup'], [
            'name' => 'ООО «СанБизнесГруп»', 'is_active' => true,
            'crm_counterparty_id' => $counterparty->id,
            'contact' => 'тел. +375 17 123-45-67, info@sbg.by',
            'communication_channels' => [['type' => 'phone', 'value' => '+375 17 123-45-67'], ['type' => 'site', 'value' => 'https://sbg.by']],
        ]);
        CrmContactPoint::query()->create(['counterparty_id' => $counterparty->id, 'type' => 'phone', 'value' => '8 017 123-45-67', 'is_primary' => true, 'is_active' => true]);
        CrmContactPoint::query()->create(['counterparty_id' => $counterparty->id, 'type' => 'email', 'value' => 'info@sbg.by', 'is_primary' => true, 'is_active' => true]);
        CrmContactPoint::query()->create(['counterparty_id' => $counterparty->id, 'type' => 'email', 'value' => 'personal@gmail.com', 'is_primary' => false, 'is_active' => true]);

        $this->artisan('supplier:set-sanbusinessgroup-contacts')
            ->expectsOutputToContain('metric.sbg_contacts.phone_points_to_disable=1')
            ->expectsOutputToContain('metric.sbg_contacts.email_points_to_disable=2')
            ->assertSuccessful();
        // «8 017 123-45-67» is stored as «+375 17 123-45-67» — the same number as the channel, listed once.
        $this->assertCount(1, $supplier->fresh()->contactPhoneNumbers());

        $this->artisan('supplier:set-sanbusinessgroup-contacts --apply')
            ->expectsOutputToContain('metric.sbg_contacts.new_phone_first=1')
            ->assertSuccessful();
        $this->artisan('supplier:set-sanbusinessgroup-contacts --apply')->assertSuccessful();

        $supplier = $supplier->fresh();
        $this->assertSame(['+375 29 354-40-41'], $supplier->contactPhoneNumbers());
        $this->assertSame(['info@kotlov.by'], $supplier->contactEmailAddresses());
        $this->assertContains(['type' => 'site', 'value' => 'https://sbg.by'], $supplier->communication_channels);
        $this->assertTrue(CrmMailbox::query()->where('email', 'info@kotlov.by')->exists());
    }
}
