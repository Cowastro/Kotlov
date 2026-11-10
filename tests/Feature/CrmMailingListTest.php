<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmMailingLists\CrmMailingListResource;
use App\Models\B2bClient;
use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\CrmMailingList;
use App\Models\PartnerBranch;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Crm\MailingAudience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmMailingListTest extends TestCase
{
    use RefreshDatabase;

    public function test_partners_are_found_by_pasted_names_and_every_channel_is_gathered(): void
    {
        $grand = CrmCounterparty::query()->create(['type' => 'organization', 'classification' => 'b2b_partner', 'status' => 'active', 'source' => 'manual', 'name' => 'ООО «Гранд Тепло Компани»']);
        $grand->contactPoints()->create(['type' => 'email', 'value' => 'Info@Grand.by']);
        $person = $grand->contacts()->create(['full_name' => 'Иван']);
        CrmContactPoint::query()->create(['contact_id' => $person->id, 'type' => 'phone', 'value' => '+375 29 111-22-33']);

        $pinsk = CrmCounterparty::query()->create(['type' => 'organization', 'classification' => 'b2b_partner', 'status' => 'active', 'source' => 'manual', 'name' => 'ТД Пинск']);
        $owner = User::factory()->create(['email' => 'owner@pinsk.by']);
        $client = B2bClient::query()->create(['user_id' => $owner->id, 'status' => 'approved', 'partner_type' => 'wholesale', 'counterparty_id' => $pinsk->id, 'company_name' => 'ТД Пинск', 'contact_phone' => '+375 33 444-55-66']);
        $city = \App\Models\City::query()->create(['slug' => 'pinsk', 'name' => 'Пинск', 'name_in' => 'в Пинске', 'name_title' => 'Пинск', 'is_active' => true]);
        PartnerBranch::query()->create(['city_id' => $city->id, 'b2b_client_id' => $client->id, 'name' => 'Пинск, магазин', 'email' => 'shop@pinsk.by', 'telegram_username' => 'pinsk_shop', 'phones' => ['+375 33 444-55-66', '+375 165 12-34-56'], 'address' => 'Пинск']);

        Supplier::query()->create(['code' => 'saunaopt', 'name' => 'СаунаОпт', 'contact' => 'opt@saunaopt.by, +375 44 777-88-99']);

        $list = CrmMailingList::query()->create(['name' => 'Дилеры']);
        $stats = app(MailingAudience::class)->addByNames($list, "грандтеплокомпани\nОлещик/ТД пинск\nСаунаопт\nИП Неизвестный\nграндтеплокомпани");

        $this->assertSame(4, $stats['lines']);
        $this->assertSame(3, $stats['matched']);
        $this->assertSame(['ИП Неизвестный'], $stats['missing']);
        $this->assertSame(3, $list->members()->count());

        $audience = app(MailingAudience::class);
        $byName = $list->members()->get()->keyBy('name');
        $this->assertSame(['emails' => ['info@grand.by'], 'phones' => ['+375 29 111-22-33'], 'messengers' => []], $audience->channels($byName['ООО «Гранд Тепло Компани»']));
        $pinskChannels = $audience->channels($byName['ТД Пинск']);
        $this->assertSame(['owner@pinsk.by', 'shop@pinsk.by'], $pinskChannels['emails']);
        $this->assertSame(['+375 33 444-55-66', '+375 165 12-34-56'], $pinskChannels['phones']);
        $this->assertSame(['Telegram: pinsk_shop'], $pinskChannels['messengers']);
        $this->assertSame(['opt@saunaopt.by'], $audience->channels($byName['СаунаОпт'])['emails']);

        // Adding the same list again does not duplicate members.
        $this->assertSame(0, app(MailingAudience::class)->addByNames($list, 'Гранд Тепло Компани')['added']);

        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $this->get(CrmMailingListResource::getUrl('edit', ['record' => $list]))->assertOk()->assertSee('Добавить по списку названий');
    }
}
