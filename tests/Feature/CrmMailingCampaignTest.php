<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmMailingCampaigns\CrmMailingCampaignResource;
use App\Filament\Resources\CrmMailingOptouts\CrmMailingOptoutResource;
use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\CrmMailingCampaign;
use App\Models\CrmMailingList;
use App\Models\CrmMailingOptout;
use App\Models\User;
use App\Services\Crm\MailingAudience;
use App\Services\Crm\MailingSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class CrmMailingCampaignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.sms_by.token' => 'test-api-key',
            'services.sms_by.alphaname_id' => '',
            'services.sms_by.vibername_id' => '',
            'services.sms_by.webhook_secret' => str_repeat('s', 32),
        ]);
    }

    public function test_viber_campaign_goes_to_mobile_phones_with_sms_forwarding_and_tracks_delivery(): void
    {
        $list = $this->listWithPartners();
        Http::fake([
            '*/getAlphanames*' => Http::response(['7229' => 'KOTLOV.BY']),
            '*/getVibernames*' => Http::response(['status' => 'OK', 'result' => ['910' => 'KOTLOV.BY']]),
            '*/sendQuickViberMessage*' => Http::sequence()->push(['status' => 'OK', 'result' => ['message_id' => 501]])
                ->push(['status' => 'OK', 'result' => ['message_id' => 502]]),
            '*/getViberDeliveryData*' => Http::response(['status' => 'OK', 'result' => ['phone' => '375291112233', 'status' => 'READ', 'error_code' => 0]]),
        ]);
        $campaign = CrmMailingCampaign::query()->create(['mailing_list_id' => $list->id, 'name' => 'Новинки', 'channel' => 'viber_sms',
            'text' => 'Новинка PRIMO 700. Ответьте СТОП, чтобы отписаться.', 'button_caption' => 'Смотреть', 'button_link' => 'https://kotlov.by/primo']);

        $sender = app(MailingSender::class);
        // Landline and the opted-out mobile are left out; the same mobile on two cards is sent once.
        $this->assertSame(['375291112233', '375447778899'], array_column($sender->recipients($campaign), 'phone'));
        $this->assertSame(2, $sender->start($campaign));
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'sendQuick'));

        $this->artisan('crm:send-mailings')->expectsOutputToContain('metric.send_mailings.sent=2')->assertSuccessful();

        $sends = Http::recorded(fn (Request $r) => str_contains($r->url(), 'sendQuickViberMessage'));
        $this->assertCount(2, $sends);
        $first = $sends->first()[0];
        $this->assertSame('POST', $first->method());
        $this->assertSame(['910', '375291112233', 'BUTTON', 'Смотреть', 'https://kotlov.by/primo', '1', '60', '7229'],
            [$first['vibername_id'], $first['phone'], $first['type_message'], $first['button'], $first['button_link'], (string) $first['forwarding_message'], (string) $first['forwarding_time'], $first['alphaname_id']]);
        $this->assertSame('done', $campaign->refresh()->status);
        $this->assertSame(['501', '502'], $campaign->messages()->orderBy('id')->pluck('provider_message_id')->all());
        $this->assertSame(2, $campaign->messages()->where('status', 'read')->count());

        // A status webhook never downgrades «прочитано».
        $this->postJson('/integrations/smsby/'.str_repeat('s', 32), ['channel' => 'VIBER', 'event_type' => 'change_status', 'message_id' => 501, 'status' => 'DELIVERED'])->assertOk();
        $this->assertSame('read', $campaign->messages()->where('provider_message_id', '501')->value('status'));
    }

    public function test_sms_only_campaign_and_inactive_viber_name(): void
    {
        $list = $this->listWithPartners();
        Http::fake([
            '*/getAlphanames*' => Http::response(['7229' => 'KOTLOV.BY']),
            '*/getVibernames*' => Http::response(['status' => 'OK', 'result' => []]),
            '*/sendQuickSMS*' => Http::response(['sms_id' => 9001, 'status' => 'NEW', 'parts' => 1]),
        ]);
        $viber = CrmMailingCampaign::query()->create(['mailing_list_id' => $list->id, 'name' => 'V', 'channel' => 'viber', 'text' => 'Текст']);
        try {
            app(MailingSender::class)->start($viber);
            $this->fail('Viber campaign started without an active Viber name.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Viber-имя', $e->getMessage());
        }
        $this->assertSame('draft', $viber->refresh()->status);

        $sms = CrmMailingCampaign::query()->create(['mailing_list_id' => $list->id, 'name' => 'S', 'channel' => 'sms', 'text' => 'Скидки до 30%']);
        app(MailingSender::class)->start($sms);
        app(MailingSender::class)->sendQueued();
        $this->assertSame(2, $sms->messages()->where('status', 'sent')->where('channel', 'sms')->count());
        $this->assertSame('Скидки до 30%', Http::recorded(fn (Request $r) => str_contains($r->url(), 'sendQuickSMS'))->first()[0]['message']);
    }

    public function test_stop_reply_opts_the_phone_out_and_webhook_needs_the_secret(): void
    {
        $this->postJson('/integrations/smsby/'.str_repeat('x', 32), ['event_type' => 'incoming_message', 'from' => '375291112233', 'message' => 'СТОП'])->assertNotFound();
        $this->postJson('/integrations/smsby/'.str_repeat('s', 32), ['event_type' => 'incoming_message', 'from' => '375291112233', 'message' => 'Спасибо'])->assertOk();
        $this->assertSame(0, CrmMailingOptout::query()->count());
        $this->postJson('/integrations/smsby/'.str_repeat('s', 32), ['channel' => 'VIBER', 'event_type' => 'incoming_message', 'from' => '375291112233', 'message' => ' стоп '])->assertOk();
        $this->assertSame(['375291112233'], CrmMailingOptout::query()->pluck('phone')->all());
    }

    public function test_admin_pages_render(): void
    {
        $list = $this->listWithPartners();
        $campaign = CrmMailingCampaign::query()->create(['mailing_list_id' => $list->id, 'name' => 'Новинки', 'channel' => 'viber_sms', 'text' => 'Текст']);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));

        $this->get(CrmMailingCampaignResource::getUrl('index'))->assertOk()->assertSee('Новинки');
        $this->get(CrmMailingCampaignResource::getUrl('create', ['list' => $list->id]))->assertOk();
        $this->get(CrmMailingCampaignResource::getUrl('edit', ['record' => $campaign]))->assertOk()->assertSee('Запустить рассылку');
        $this->get(CrmMailingOptoutResource::getUrl('index'))->assertOk();
    }

    private function listWithPartners(): CrmMailingList
    {
        $a = CrmCounterparty::query()->create(['type' => 'organization', 'classification' => 'b2b_partner', 'status' => 'active', 'source' => 'manual', 'name' => 'Гранд Тепло']);
        CrmContactPoint::query()->create(['counterparty_id' => $a->id, 'type' => 'phone', 'value' => '+375 29 111-22-33']);
        CrmContactPoint::query()->create(['counterparty_id' => $a->id, 'type' => 'phone', 'value' => '+375 17 222-33-44']);
        $b = CrmCounterparty::query()->create(['type' => 'organization', 'classification' => 'b2b_partner', 'status' => 'active', 'source' => 'manual', 'name' => 'ТД Пинск']);
        CrmContactPoint::query()->create(['counterparty_id' => $b->id, 'type' => 'phone', 'value' => '80291112233']);
        CrmContactPoint::query()->create(['counterparty_id' => $b->id, 'type' => 'phone', 'value' => '+375 44 777-88-99']);
        CrmContactPoint::query()->create(['counterparty_id' => $b->id, 'type' => 'phone', 'value' => '+375 33 000-00-01']);
        CrmMailingOptout::query()->create(['phone' => '375330000001']);

        $list = CrmMailingList::query()->create(['name' => 'Дилеры']);
        $audience = app(MailingAudience::class);
        foreach ([$a, $b] as $c) {
            $audience->addMember($list, ['kind' => 'counterparty', 'id' => $c->id, 'counterparty' => $c->id]);
        }

        return $list;
    }
}
