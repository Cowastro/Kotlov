<?php

namespace Tests\Feature;

use App\Filament\Pages\LeadChannelSettings;
use App\Filament\Resources\ContactRequests\Pages\ListContactRequests;
use App\Filament\Resources\ContactRequests\Pages\ViewContactRequest;
use App\Livewire\ContactRequestDialog;
use App\Livewire\ContactRequestIndicator;
use App\Models\ContactRequest;
use App\Models\LeadChannel;
use App\Models\User;
use App\Services\LeadInbox;
use Filament\Actions\Testing\TestAction;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class LeadInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_manager_replies_to_original_telegram_chat_and_replays_are_idempotent(): void
    {
        Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 88]])]);
        config([
            'services.telegram.leads_bot_token' => '321:bot-token',
            'services.telegram.leads_webhook_secret' => 'incoming-token',
        ]);

        foreach ([10, 11, 10] as $id) {
            $this->postJson('/integrations/telegram/leads', ['message' => [
                'chat' => ['type' => 'private', 'id' => 555],
                'message_id' => $id,
                'text' => 'Вопрос '.$id,
            ]], ['X-Telegram-Bot-Api-Secret-Token' => 'incoming-token'])->assertOk();
        }

        $lead = ContactRequest::sole();
        $this->assertSame(2, $lead->leadMessages()->count());
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $this->actingAs($manager)->get('/admin/lead-channels')->assertForbidden();

        Livewire::test(ViewContactRequest::class, ['record' => $lead->getRouteKey()])
            ->assertSee('Вопрос 11')
            ->assertSee('Ответ в Telegram');

        $queue = Livewire::test(ListContactRequests::class)->assertSet('columnsLayoutVersion', 'compact-v2');
        $this->assertTrue($queue->instance()->isTableColumnToggledHidden('product_name'));
        $this->assertTrue($queue->instance()->isTableColumnToggledHidden('next_follow_up_at'));
        $queue
            ->assertCanSeeTableRecords([$lead])
            ->mountAction(TestAction::make('dialog')->table($lead))
            ->assertActionMounted(TestAction::make('dialog')->table($lead));

        $dialog = Livewire::test(ContactRequestDialog::class, ['contactRequestId' => $lead->id])
            ->set('draft', 'Есть в наличии');
        $sendKey = $dialog->get('sendKey');
        $dialog->call('sendReply')->assertHasNoErrors()->assertSet('draft', '')->assertSee('Принято каналом');
        $this->assertFalse($lead->fresh()->needs_attention);

        app(LeadInbox::class)->reply($lead, $manager->id, 'Есть в наличии', $sendKey);
        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://api.telegram.org/bot321:bot-token/sendMessage'
            && $request['chat_id'] === '555' && $request['text'] === 'Есть в наличии');
        $this->assertDatabaseHas('lead_messages', [
            'contact_request_id' => $lead->id, 'user_id' => $manager->id, 'state' => 'sent', 'provider_message_id' => '88',
        ]);
    }

    public function test_new_message_flags_existing_work_and_returns_archived_dialog_to_the_queue(): void
    {
        $this->freezeTime();
        config([
            'services.telegram.leads_bot_token' => '321:bot-token',
            'services.telegram.leads_webhook_secret' => 'incoming-token',
        ]);
        $lead = ContactRequest::create([
            'name' => 'Клиент', 'message' => 'Прошлый вопрос', 'status' => 'in_progress',
            'source' => 'telegram_channel', 'external_source' => 'telegram_bot', 'external_id' => 'chat:555',
            'archived_at' => now(), 'needs_attention' => false,
        ]);
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $this->actingAs($manager);
        Notification::make()->title('Старое уведомление')->viewData(['inboxAlert' => true])->sendToDatabase($manager);
        $indicator = Livewire::test(ContactRequestIndicator::class)->assertDontSee('Входящие · 1');
        $indicator->call('pollInbox')->assertNotNotified();

        $this->postJson('/integrations/telegram/leads', ['message' => [
            'chat' => ['type' => 'private', 'id' => 555], 'message_id' => 12, 'text' => 'Новый вопрос',
        ]], ['X-Telegram-Bot-Api-Secret-Token' => 'incoming-token'])->assertOk();
        $indicator->call('pollInbox')->assertSee('Входящие · 1')->assertNotified('Новое сообщение · Telegram');
        $indicator->call('pollInbox')->assertNotNotified();
        $alert = $manager->unreadNotifications()->where('data->title', 'Новое сообщение · Telegram')->sole();
        $this->assertSame('Клиент · Новый вопрос', $alert->data['body']);
        $this->assertSame(url('/admin/contact-requests/'.$lead->id), $alert->data['actions'][0]['url']);
        $this->assertSame('Ответить', $alert->data['actions'][0]['label']);

        $lead->refresh();
        $this->assertNull($lead->archived_at);
        $this->assertSame('in_progress', $lead->status);
        Livewire::test(ContactRequestDialog::class, ['contactRequestId' => $lead->id])
            ->call('markSeen')->assertDontSee('Требует реакции');
        $indicator->call('$refresh')->assertDontSee('Входящие · 1');
    }

    public function test_admin_can_connect_and_pause_a_channel_with_encrypted_credentials(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $this->get('/admin/lead-channels')->assertOk()->assertSee('Каналы обращений');

        $page = Livewire::test(LeadChannelSettings::class)
            ->mountAction('configureChannel', arguments: ['provider' => 'telegram'])
            ->setActionData([
                'enabled' => true,
                'access_token' => '123:shop-bot',
                'webhook_secret' => 'secure_shop_webhook_secret',
                'public_url' => 'https://t.me/example_shop_bot',
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified('Настройки канала сохранены');

        $channel = LeadChannel::findOrFail('telegram');
        $this->assertTrue($channel->acceptsMessages());
        $this->assertSame('123:shop-bot', $channel->access_token);
        $this->assertNotSame('123:shop-bot', DB::table('lead_channels')->where('provider', 'telegram')->value('access_token'));
        LeadChannel::create([
            'provider' => 'whatsapp',
            'enabled' => true,
            'public_url' => 'https://wa.me/375290000000',
        ]);

        $widget = view('partials.write-to-store')->render();
        $this->assertStringContainsString('Написать в магазин · Минск', $widget);
        $this->assertStringContainsString('https://t.me/example_shop_bot', $widget);
        $this->assertStringNotContainsString('https://wa.me/', $widget);
        $page->assertSee('Входящих сообщений пока нет');

        $page->mountAction('configureChannel', arguments: ['provider' => 'telegram'])
            ->setActionData(['enabled' => false])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertFalse($channel->fresh()->acceptsMessages());
        $this->assertSame('123:shop-bot', $channel->fresh()->access_token);
        $this->assertStringNotContainsString('store-contact-widget', view('partials.write-to-store')->render());
        $this->postJson('/integrations/telegram/leads', [], [
            'X-Telegram-Bot-Api-Secret-Token' => 'secure_shop_webhook_secret',
        ])->assertNotFound();
    }

    public function test_meta_and_viber_callbacks_are_signed_and_route_to_their_original_accounts(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wa-reply']]]),
            'https://graph.instagram.com/*' => Http::response(['message_id' => 'ig-reply']),
            'https://chatapi.viber.com/*' => Http::response(['status' => 0, 'message_token' => 'vb-reply']),
        ]);
        foreach (['whatsapp' => '777', 'instagram' => '888', 'viber' => 'store-bot'] as $provider => $account) {
            LeadChannel::create([
                'provider' => $provider, 'enabled' => true, 'account_id' => $account, 'api_version' => 'v25.0',
                'access_token' => 'channel-access-token', 'webhook_secret' => 'meta-app-secret', 'verify_token' => 'meta-verify-token',
            ]);
        }

        $this->get('/integrations/leads/whatsapp?hub.mode=subscribe&hub.challenge=321&hub.verify_token=meta-verify-token')
            ->assertOk()->assertContent('321');

        $whatsApp = ['object' => 'whatsapp_business_account', 'entry' => [['changes' => [[
            'field' => 'messages',
            'value' => ['metadata' => ['phone_number_id' => '777'], 'messages' => [[
                'from' => '375290000001', 'id' => 'wa-incoming', 'type' => 'text', 'text' => ['body' => 'WhatsApp'],
            ]]],
        ]]]]];
        $instagram = ['object' => 'instagram', 'entry' => [['id' => '888', 'messaging' => [
            ['sender' => ['id' => '44'], 'message' => ['mid' => 'ig-incoming', 'text' => 'Instagram']],
            ['sender' => ['id' => '888'], 'message' => ['mid' => 'ig-echo', 'text' => 'Echo', 'is_echo' => true]],
        ]]]];
        $viber = [
            'event' => 'message', 'message_token' => '999888777666555444', 'sender' => ['id' => 'A+/='],
            'message' => ['type' => 'text', 'text' => 'Viber'],
        ];

        $this->postJson('/integrations/leads/whatsapp', $whatsApp)->assertNotFound();
        $this->signedCallback('whatsapp', $whatsApp);
        $this->signedCallback('instagram', $instagram);
        $this->signedCallback('viber', $viber);
        $this->assertDatabaseCount('lead_messages', 3);

        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        foreach (ContactRequest::all() as $lead) {
            $this->assertSame('sent', app(LeadInbox::class)->reply($lead, $manager->id, 'Ответ', (string) Str::uuid())->state);
        }
        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://graph.facebook.com/v25.0/777/messages'
            && $request['to'] === '375290000001');
        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://graph.instagram.com/v25.0/888/messages'
            && $request['recipient']['id'] === '44');
        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://chatapi.viber.com/pa/send_message'
            && $request['receiver'] === 'A+/=');

        $this->assertNotNull(LeadChannel::settings('viber')->lastIncomingAt());
        LeadChannel::findOrFail('viber')->update(['account_id' => 'different-bot']);
        $this->assertNull(app(LeadInbox::class)->replyChannel(ContactRequest::where('source', 'viber')->sole()));
    }

    public function test_ambiguous_send_result_is_stored_without_automatic_retry(): void
    {
        Http::fake(['https://api.telegram.org/*' => Http::failedConnection()]);
        config([
            'services.telegram.leads_bot_token' => '321:bot-token',
            'services.telegram.leads_webhook_secret' => 'incoming-token',
        ]);

        $lead = ContactRequest::create([
            'name' => 'Клиент', 'message' => 'Предыдущая переписка', 'source' => 'telegram_channel',
            'external_source' => 'telegram_bot', 'external_id' => 'chat:555', 'last_external_event_id' => '555:12',
        ]);
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $key = (string) Str::uuid();

        $inbox = app(LeadInbox::class);
        $this->assertSame('unknown', $inbox->reply($lead, $manager->id, 'Ответ', $key)->state);
        $this->assertSame('unknown', $inbox->reply($lead, $manager->id, 'Ответ', $key)->state);
        $this->assertSame(2, $lead->leadMessages()->count());
        $this->assertSame('Предыдущая переписка', $lead->leadMessages()->where('direction', 'legacy')->sole()->body);
    }

    private function signedCallback(string $provider, array $payload): void
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $viber = $provider === 'viber';
        $signature = ($viber ? '' : 'sha256=').hash_hmac('sha256', $body, $viber ? 'channel-access-token' : 'meta-app-secret');
        $this->call('POST', '/integrations/leads/'.$provider, server: [
            'CONTENT_TYPE' => 'application/json',
            $viber ? 'HTTP_X_VIBER_CONTENT_SIGNATURE' : 'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], content: $body)->assertOk();
    }
}
