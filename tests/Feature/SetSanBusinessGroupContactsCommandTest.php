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

    public function test_the_wholesale_phone_and_b2b_mailbox_replace_the_city_number(): void
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

        $this->artisan('supplier:set-sanbusinessgroup-contacts')
            ->expectsOutputToContain('metric.sbg_contacts.phone_points_to_disable=1')
            ->assertSuccessful();
        $this->assertCount(2, $supplier->fresh()->contactPhoneNumbers());

        $this->artisan('supplier:set-sanbusinessgroup-contacts --apply')
            ->expectsOutputToContain('metric.sbg_contacts.new_phone_first=1')
            ->assertSuccessful();
        $this->artisan('supplier:set-sanbusinessgroup-contacts --apply')->assertSuccessful();

        $supplier = $supplier->fresh();
        $this->assertSame(['+375 29 351 29 82'], $supplier->contactPhoneNumbers());
        $this->assertSame('b2b@kotlov.by', $supplier->contactEmailAddresses()[0]);
        $this->assertContains(['type' => 'site', 'value' => 'https://sbg.by'], $supplier->communication_channels);
        $mailbox = CrmMailbox::query()->where('email', 'b2b@kotlov.by')->sole();
        $this->assertFalse($mailbox->is_enabled);
    }
}
