<?php

namespace Tests\Feature;

use App\Filament\Resources\OneCOrderProfiles\Pages\EditOneCOrderProfile;
use App\Filament\Resources\OneCOrderProfiles\Pages\ListOneCOrderProfiles;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\OneCAgentTask;
use App\Models\OneCAgentToken;
use App\Models\OneCItem;
use App\Models\OneCOrderProfile;
use App\Models\OneCReference;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Integrations\OneCBridge\DocumentStatusTaskHandler;
use App\Services\Integrations\OneCBridge\OneCOrderExport;
use App\Services\Integrations\OneCBridge\OneCOrderMatcher;
use App\Services\Integrations\OneCBridge\OneCOrderPayments;
use App\Services\Integrations\OneCBridge\OneCOrderProfiles;
use App\Services\Integrations\OneCBridge\OneCOrderStages;
use App\Services\Integrations\OneCBridge\OneCReferences;
use App\Services\Integrations\OneCBridge\OneCStock;
use App\Services\Orders\OrderStatusCustomerNotifier;
use DomainException;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

class OneCOrderExportTest extends TestCase
{
    use RefreshDatabase;

    private const ORG = '6e310ed3-97e4-11e9-b722-1c1b0d1de7c2';

    private const RETAIL = '012b6a71-09b8-11e9-bad6-1c1b0d1de7c2';

    private const ITEM = 'adcaedcf-5146-11ea-a246-1c1b0d1de7c2';

    private User $admin;

    private string $key;

    private Product $card;

    private IntegrationSource $source;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'name' => 'Иванова Анна Петровна']);
        $this->source = IntegrationSource::query()->updateOrCreate(['code' => 'onec'], ['name' => '1C']);
        $category = Category::query()->firstOrCreate(['slug' => 'kotly'], ['name' => 'Котлы', 'is_active' => true]);
        $this->card = Product::query()->create(['category_id' => $category->id, 'name' => 'Переход Моно', 'slug' => 'perehod', 'price' => 24.99]);
        IntegrationProduct::query()->create(['integration_source_id' => $this->source->id, 'external_id' => self::ITEM,
            'external_code' => 'УТ-00000001', 'name' => 'Переход Моно П-М 430-0.8 D100/115 (У)', 'stock_quantity' => 2, 'price' => 24.99,
            'product_id' => $this->card->id, 'match_status' => 'matched']);
        [, $this->key] = OneCAgentToken::issue('test', $this->admin->id);
    }

    private function agent(string $version = '8'): static
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.$this->key, 'X-Kotlov-Agent' => $version]);
    }

    private function sendPart(int $task, string $name, int $code, array $json): TestResponse
    {
        return $this->call('POST', '/1c/agent/tasks/'.$task.'/part?name='.$name.'&code='.$code, [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$this->key,
            'HTTP_X_KOTLOV_AGENT' => '8', 'CONTENT_TYPE' => 'application/octet-stream'], json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** A retail order of «СБГ бай» as 1C gives it (МПУТ-000756). */
    private function template(): array
    {
        return [
            'Ref_Key' => 'bbbbbbbb-0000-0000-0000-000000000756', 'DataVersion' => 'AAA', 'Number' => 'МПУТ-000756', 'Date' => '2026-11-20T12:17:39',
            'Posted' => true, 'DeletionMark' => false, 'Организация_Key' => self::ORG, 'Партнер_Key' => self::RETAIL,
            'Контрагент_Key' => '09e8f503-0000-0000-0000-000000000001', 'Соглашение_Key' => 'a56e299f-d38a-0000-0000-000000000001',
            'Договор_Key' => '00000000-0000-0000-0000-000000000000', 'Склад_Key' => 'c532218f-0f48-11e9-bad6-1c1b0d1de7c2',
            'Подразделение_Key' => 'fc5d183e-0000-0000-0000-000000000001', 'Менеджер_Key' => 'eeeeeeee-0000-0000-0000-000000000001',
            'Валюта_Key' => '11867b41-09b8-11e9-bad6-1c1b0d1de7c2', 'ЦенаВключаетНДС' => true, 'НалогообложениеНДС' => 'ПродажаОблагаетсяНДС',
            'ХозяйственнаяОперация' => 'РеализацияКлиенту', 'Статус' => 'КВыполнению', 'СпособДоставки' => 'ДоКлиента',
            'АдресДоставки' => 'Минская область, Тестовск, Ул. Примерная,1', 'АдресДоставкиЗначенияПолей' => '<xml/>',
            'ДополнительнаяИнформацияПоДоставке' => "Тестовск\nТелефон: +375 (29) 000-11-22", 'Комментарий' => 'старый',
            'НомерПоДаннымКлиента' => '', 'ЖелаемаяДатаОтгрузки' => '2026-11-21T00:00:00', 'ДатаОтгрузки' => '2026-11-21T00:00:00', 'ИдентификаторПлатежа' => 'ЗК2611МПУТ0007560001', 'Назначение_Key' => '36a5fee6-0000-0000-0000-000000000001', 'МаксимальныйКодСтроки' => 2, 'АдресДоставкиПеревозчика' => 'склад перевозчика', 'ДатаСогласования' => '2026-11-20T00:00:00', 'СуммаДокумента' => 440.99,
            'Сделка_Key' => '00000000-0000-0000-0000-000000000000', 'Автор_Key' => 'eeeeeeee-0000-0000-0000-000000000001',
            'Товары' => [['LineNumber' => '1', 'Номенклатура_Key' => 'ffffffff-0000-0000-0000-000000000001', 'Количество' => 1, 'Цена' => 24.99,
                'СтавкаНДС_Key' => 'dd2c0701-8b4a-11ed-a689-1c1b0d1de7c2', 'ВариантОбеспечения' => 'Отгрузить', 'КодСтроки' => 1, 'ДатаОтгрузки' => '2026-11-21T00:00:00', 'ИдентификаторСтроки' => 'x']],
            'ЭтапыГрафикаОплаты' => [['LineNumber' => '1', 'ВариантОплаты' => 'ПредоплатаДоОтгрузки', 'ДатаПлатежа' => '2026-11-20T00:00:00',
                'ПроцентПлатежа' => 100, 'СуммаПлатежа' => 440.99]],
            'Товары@navigationLinkUrl' => 'x',
        ];
    }

    /** The agent reads the lists and recent 1C orders; the site keeps them. */
    private function syncReferences(array $orders = []): void
    {
        $task = app(OneCReferences::class)->enqueueSync($this->admin);
        $this->agent()->get('/1c/agent/tasks/next')->assertOk()->assertSee('task|'.$task->id.'|odata_read');
        $this->sendPart($task->id, 'organizations', 200, ['value' => [['Ref_Key' => self::ORG, 'Description' => 'СБГ бай ООО', 'DeletionMark' => false]]])->assertOk();
        $this->sendPart($task->id, 'partners', 200, ['value' => [['Ref_Key' => self::RETAIL, 'Description' => 'Розничный покупатель', 'DeletionMark' => false]]])->assertOk();
        $this->sendPart($task->id, 'counterparties', 200, ['value' => [['Ref_Key' => 'cccccccc-0000-0000-0000-000000000001', 'Description' => 'Тест-Компания ООО',
            'ИНН' => '100000001', 'Партнер_Key' => 'cccccccc-0000-0000-0000-0000000000aa', 'DeletionMark' => false]]])->assertOk();
        $this->sendPart($task->id, 'vat_rates', 200, ['value' => [['Ref_Key' => 'dd2c0701-8b4a-11ed-a689-1c1b0d1de7c2', 'Description' => '20%', 'DeletionMark' => false]]])->assertOk();
        $this->sendPart($task->id, 'users', 200, ['value' => [['Ref_Key' => 'eeeeeeee-0000-0000-0000-000000000002', 'Description' => 'Иванова Анна Петровна', 'DeletionMark' => false]]])->assertOk();
        $this->sendPart($task->id, 'order_templates', 200, ['value' => [$this->template()]])->assertOk();
        $this->sendPart($task->id, 'orders_1', 200, ['value' => $orders])->assertOk();
        $this->agent()->post('/1c/agent/tasks/'.$task->id.'/result', ['ok' => '1'])->assertOk();
    }

    private function order(array $attributes = []): Order
    {
        $order = (new Order)->forceFill(array_merge(['number' => 'K-1001', 'status' => 'confirmed', 'customer_name' => 'Тестов Иван',
            'customer_phone' => '+375 29 000-11-22', 'delivery_type' => 'courier', 'delivery_city' => 'Тестовск', 'delivery_address' => 'Ул. Примерная, 1',
            'subtotal' => 49.98, 'total' => 49.98], $attributes));
        $order->save();
        OrderItem::query()->create(['order_id' => $order->id, 'product_id' => $this->card->id, 'product_name' => 'Переход Моно',
            'price' => 24.99, 'quantity' => 2, 'total' => 49.98, 'pricing_type' => 'retail']);

        return $order->fresh();
    }

    public function test_an_order_goes_to_1c_by_the_retail_scheme_made_from_a_real_1c_order(): void
    {
        $this->syncReferences();
        $this->assertSame('СБГ бай ООО', OneCReference::query()->where('kind', 'organization')->value('description'));

        $profile = app(OneCOrderProfiles::class)->fromTemplate(app(OneCReferences::class)->templates()[0], 'Розница СБГ бай', OneCOrderProfile::RETAIL);
        $this->assertTrue($profile->is_default);
        $this->assertSame('', $profile->header['АдресДоставки']);
        $this->assertStringNotContainsString('000-11-22', json_encode($profile->header, JSON_UNESCAPED_UNICODE));
        $this->assertArrayNotHasKey('Number', $profile->header);
        $this->assertEquals(['ВариантОбеспечения' => 'Отгрузить', 'СтавкаНДС_Key' => 'dd2c0701-8b4a-11ed-a689-1c1b0d1de7c2'], $profile->row_defaults);

        $order = $this->order(['manager_id' => $this->admin->id]);
        $export = app(OneCOrderExport::class);
        $form = $export->defaults($order);
        $this->assertSame($profile->id, $form['profile_id']);
        $this->assertSame(self::RETAIL, $form['h']['Партнер_Key']);
        $this->assertSame('eeeeeeee-0000-0000-0000-000000000002', $form['h']['Менеджер_Key']);
        $this->assertStringContainsString('К оплате 49,98р.', $form['info']);
        $this->assertSame([], $export->problems($order, $profile, $form));

        $task = $export->enqueue($order, $form, $this->admin);
        $body = $task->payload['body'];
        $this->assertSame('КВыполнению', $body['Статус']);
        $this->assertSame('ДоКлиента', $body['СпособДоставки']);
        $this->assertSame('Тестовск, Ул. Примерная, 1', $body['АдресДоставки']);
        $this->assertSame('', $body['АдресДоставкиПеревозчика']);
        $this->assertArrayNotHasKey('ИдентификаторПлатежа', $body);
        $this->assertArrayNotHasKey('Назначение_Key', $body);
        $this->assertSame(1, $body['МаксимальныйКодСтроки']);
        $this->assertSame('kotlov-K-1001', $body['НомерПоДаннымКлиента']);
        $this->assertSame(49.98, $body['СуммаДокумента']);
        $this->assertSame(self::ITEM, $body['Товары'][0]['Номенклатура_Key']);
        $this->assertSame(24.99, $body['Товары'][0]['Цена']);
        $this->assertSame(8.33, $body['Товары'][0]['СуммаНДС']);
        $this->assertSame('Отгрузить', $body['Товары'][0]['ВариантОбеспечения']);
        // 1C has 2, the order wants 3: not «Отгрузить» (1C refuses to write it) but «Обеспечить».
        $more = $this->order(['number' => 'K-1003']);
        $more->items()->update(['quantity' => 3, 'total' => 74.97]);
        $this->assertSame('Требуется', $export->body($more->fresh(), $profile, $export->defaults($more->fresh()))['Товары'][0]['ВариантОбеспечения']);
        // No planned delivery: tomorrow, in the header and on the line.
        $tomorrow = now('Europe/Minsk')->addDay()->format('Y-m-d').'T00:00:00';
        $this->assertSame($tomorrow, $body['ЖелаемаяДатаОтгрузки']);
        $this->assertSame($tomorrow, $body['ДатаОтгрузки']);
        $this->assertSame($tomorrow, $body['Товары'][0]['ДатаОтгрузки']);
        $this->assertSame(49.98, $body['ЭтапыГрафикаОплаты'][0]['СуммаПлатежа']);

        // An old agent cannot create orders: the order waits for agent 4.
        $this->agent('3')->get('/1c/agent/tasks/next')->assertOk()->assertSee('none');
        $lines = explode("\n", trim($this->agent()->get('/1c/agent/tasks/next')->assertOk()->getContent()));
        $this->assertSame('task|'.$task->id.'|onec_order', $lines[0]);
        $this->assertStringStartsWith('find|Document_', $lines[2]);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9%_.~-]+\?[A-Za-z0-9%_.~$=&-]+$/', substr($lines[2], 5));
        $this->assertEquals($body, json_decode(base64_decode(substr($lines[3], 5)), true));

        $this->agent()->post('/1c/agent/tasks/'.$task->id.'/result', ['ok' => '1', 'document_number' => 'МПУТ-000800',
            'document_key' => 'DDDDDDDD-0000-0000-0000-000000000800', 'existed' => '0'])->assertOk();
        $order->refresh();
        $this->assertSame('МПУТ-000800', $order->onec_order_number);
        $this->assertSame('dddddddd-0000-0000-0000-000000000800', $order->onec_order_guid);
        $this->assertSame('Заказ 1С МПУТ-000800, не проведён, к выполнению', $task->fresh()->summary);

        $this->expectException(DomainException::class);
        $export->enqueue($order, $form, $this->admin);
    }

    public function test_the_manager_sends_the_order_from_its_page_and_links_a_line_on_the_way(): void
    {
        $this->syncReferences();
        app(OneCOrderProfiles::class)->fromTemplate(app(OneCReferences::class)->templates()[0], 'Розница СБГ бай', OneCOrderProfile::RETAIL);
        $category = Category::query()->firstOrFail();
        $plate = Product::query()->create(['category_id' => $category->id, 'name' => 'Плита Skamol', 'slug' => 'plita', 'price' => 208]);
        $order = $this->order();
        $item = OrderItem::query()->create(['order_id' => $order->id, 'product_id' => $plate->id, 'product_name' => 'Плита Skamol',
            'price' => 208, 'quantity' => 1, 'total' => 208, 'pricing_type' => 'retail']);
        $free = IntegrationProduct::query()->create(['integration_source_id' => $this->source->id, 'external_id' => 'abababab-0000-0000-0000-000000000001',
            'external_code' => 'УТ-00000002', 'name' => 'Плита каминная SkamoEnclosure', 'stock_quantity' => 3, 'price' => 0]);
        $this->actingAs($this->admin);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionVisible('onecOrder')->assertActionHidden('onecLink')
            ->mountAction('onecOrder')
            ->setActionData(['links' => [$item->id => $free->id]])
            ->callMountedAction()->assertHasNoActionErrors();

        $task = OneCAgentTask::query()->where('type', 'onec_order')->sole();
        $this->assertCount(2, $task->payload['body']['Товары']);
        $this->assertSame($plate->id, $free->fresh()->product_id);
    }

    public function test_a_scheme_is_made_from_a_1c_order_and_edited_in_the_admin(): void
    {
        $this->syncReferences();
        // The hourly read of recent orders comes later and has no samples: the samples stay.
        $hourly = app(OneCReferences::class)->enqueueSync(null, 3, false);
        $this->agent()->get('/1c/agent/tasks/next')->assertOk()->assertSee('task|'.$hourly->id.'|odata_read');
        $this->sendPart($hourly->id, 'orders_1', 200, ['value' => []])->assertOk();
        $this->agent()->post('/1c/agent/tasks/'.$hourly->id.'/result', ['ok' => '1'])->assertOk();
        $this->assertCount(1, app(OneCReferences::class)->templates());
        $this->actingAs($this->admin);
        Livewire::test(ListOneCOrderProfiles::class)
            ->callAction('fromTemplate', ['template' => 't0', 'name' => 'Розница СБГ бай', 'customer_mode' => OneCOrderProfile::RETAIL])
            ->assertHasNoActionErrors();
        $profile = OneCOrderProfile::query()->sole();
        $this->assertSame('МПУТ-000756', $profile->template_number);

        Livewire::test(EditOneCOrderProfile::class, ['record' => $profile->getRouteKey()])
            ->assertFormSet(['f.org' => self::ORG, 'f.partner' => self::RETAIL, 'status' => 'КВыполнению', 'with_vat' => true])
            ->fillForm(['status' => 'НеСогласован'])
            ->call('save')->assertHasNoFormErrors();
        $profile->refresh();
        $this->assertSame('НеСогласован', $profile->header['Статус']);
        $this->assertSame(self::ORG, $profile->header['Организация_Key']);
        $this->assertSame('РеализацияКлиенту', $profile->header['ХозяйственнаяОперация']);
    }

    /** The agent answers the document check for one 1C order. */
    private function check1cOrder(string $status, bool $posted, array $lines): void
    {
        $task = app(DocumentStatusTaskHandler::class)->enqueueCheck(null);
        $this->agent()->get('/1c/agent/tasks/next')->assertOk();
        $this->sendPart($task->id, 'orders_1', 200, ['value' => [['Ref_Key' => 'dddddddd-0000-0000-0000-000000000800', 'Number' => 'МПУТ-000800',
            'Posted' => $posted, 'DeletionMark' => false, 'Статус' => $status, 'Товары' => $lines]]])->assertOk();
        $this->agent()->post('/1c/agent/tasks/'.$task->id.'/result', ['ok' => '1'])->assertOk();
    }

    public function test_an_order_never_confirmed_on_the_site_follows_1c_silently(): void
    {
        // An old order the manager agreed with the customer outside the site: in work in 1C, never confirmed here.
        $notifier = $this->spy(OrderStatusCustomerNotifier::class);
        $order = $this->order(['status' => 'new', 'onec_order_guid' => 'dddddddd-0000-0000-0000-000000000800', 'onec_order_number' => 'МПУТ-000800',
            'onec_exported_at' => now(), 'onec_checked_at' => now(), 'onec_doc_status' => 'НеСогласован', 'onec_posted' => false]);
        $this->check1cOrder('КОбеспечению', true, [['Номенклатура_Key' => self::ITEM, 'Отменено' => false]]);
        $this->assertSame('processing', $order->fresh()->status);
        $this->assertStringContainsString('клиенту не сообщали', (string) $order->statusHistory()->latest('id')->value('comment'));
        $notifier->shouldNotHaveReceived('notify');

        // Once it is in work, every next move is the customer's news: closed in 1C → delivered, with the message.
        $this->check1cOrder('Закрыт', true, [['Номенклатура_Key' => self::ITEM, 'Отменено' => false]]);
        $this->assertSame('delivered', $order->fresh()->status);
        $notifier->shouldHaveReceived('notify')->once();
    }

    public function test_the_order_stages_follow_1c_once_the_customer_got_the_confirmation(): void
    {
        $order = $this->order(['status' => 'confirmed', 'onec_order_guid' => 'dddddddd-0000-0000-0000-000000000800', 'onec_order_number' => 'МПУТ-000800',
            'onec_profile_id' => 1, 'onec_exported_at' => now(), 'onec_doc_status' => 'НеСогласован', 'onec_posted' => false]);
        $line = ['Номенклатура_Key' => self::ITEM, 'Отменено' => false];

        // Confirmed to the customer and posted «К выполнению» in 1C: «В исполнении».
        $order->items()->update(['fulfillment_confirmed_at' => now()]);
        $this->check1cOrder('КВыполнению', true, [$line]);
        $this->assertSame('processing', $order->fresh()->status);
        $this->assertStringStartsWith('Заказ в 1С (МПУТ-000800)', $order->fresh()->workflowNextStep());

        // Closed in 1C with every line cancelled: not a delivery, the manager gets a note.
        $this->check1cOrder('Закрыт', true, [array_merge($line, ['Отменено' => true])]);
        $this->assertSame('processing', $order->fresh()->status);
        $this->assertSame(1, $order->managerComments()->where('body', 'like', '%закрыт в 1С без отгрузки%')->count());
    }

    public function test_a_closed_1c_order_is_delivered_on_the_site(): void
    {
        $order = $this->order(['status' => 'processing', 'onec_order_guid' => 'dddddddd-0000-0000-0000-000000000800', 'onec_order_number' => 'МПУТ-000800',
            'onec_profile_id' => 1, 'onec_exported_at' => now(), 'onec_doc_status' => 'КВыполнению', 'onec_posted' => true]);
        $this->check1cOrder('Закрыт', true, [['Номенклатура_Key' => self::ITEM, 'Отменено' => false]]);
        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertSame('По 1С: заказ МПУТ-000800 проведён, «Закрыт».', $order->statusHistory()->latest('id')->value('comment'));

        // Never backwards, never a cancelled order.
        $cancelled = $this->order(['number' => 'K-3003', 'status' => 'cancelled', 'onec_order_guid' => 'eeeeeeee-0000-0000-0000-000000000801',
            'onec_profile_id' => 1, 'onec_exported_at' => now(), 'onec_doc_status' => 'КВыполнению', 'onec_posted' => true]);
        $this->assertNull(app(OneCOrderStages::class)->apply($cancelled));
        $this->assertNull(app(OneCOrderStages::class)->apply($order->fresh()));
    }

    public function test_an_order_closed_in_1c_but_behind_on_the_site_is_checked_again_and_catches_up(): void
    {
        // Linked by hand before stages went all the way: closed in 1C, still «Передача клиенту» on the site.
        $order = $this->order(['status' => 'shipped', 'payment_status' => 'paid', 'onec_order_guid' => 'dddddddd-0000-0000-0000-000000000800', 'onec_order_number' => 'МПУТ-000800',
            'onec_exported_at' => now(), 'onec_checked_at' => now(), 'onec_doc_status' => 'Закрыт', 'onec_posted' => true]);
        $this->check1cOrder('Закрыт', true, [['Номенклатура_Key' => self::ITEM, 'Отменено' => false]]);
        $this->assertSame('delivered', $order->fresh()->status);

        // Once the site caught up, a closed 1C order is not read again.
        $this->assertNull(app(DocumentStatusTaskHandler::class)->enqueueCheck(null));
    }

    public function test_an_order_paid_in_1c_becomes_paid_on_the_site(): void
    {
        $guid = 'dddddddd-0000-0000-0000-000000000900';
        $object = 'ffffffff-0000-0000-0000-000000000900';
        $order = $this->order(['status' => 'delivered', 'payment_status' => 'pending', 'onec_order_guid' => $guid, 'onec_order_number' => 'МПУТ-000900',
            'onec_exported_at' => now(), 'onec_checked_at' => now(), 'onec_doc_status' => 'Закрыт', 'onec_posted' => true]);
        $read = function (string $name, array $rows): void {
            $task = app(DocumentStatusTaskHandler::class)->enqueueCheck(null);
            $this->assertArrayHasKey($name, $task->payload['queries']);
            $this->agent()->get('/1c/agent/tasks/next')->assertOk();
            $this->sendPart($task->id, $name, 200, ['value' => $rows])->assertOk();
            $this->agent()->post('/1c/agent/tasks/'.$task->id.'/result', ['ok' => '1'])->assertOk();
        };

        // First the settlement object of the order itself; one «по договору» (no order in it) is never taken.
        $read('pay_keys_1', [['Ref_Key' => $object, 'Объект' => $guid, 'Объект_Type' => 'StandardODATA.Document_ЗаказКлиента'],
            ['Ref_Key' => 'ffffffff-0000-0000-0000-0000000000aa', 'Объект' => $guid, 'Объект_Type' => 'StandardODATA.Catalog_ДоговорыКонтрагентов']]);
        $this->assertSame($object, $order->fresh()->onec_settlement_key);

        // Then its records: 49.98 planned by the order, 30 paid by bank — not yet paid.
        $plan = ['ОбъектРасчетов_Key' => $object, 'Recorder_Type' => 'StandardODATA.Document_ЗаказКлиента', 'RecordType' => 'Receipt', 'Active' => true, 'КОплате' => 49.98, 'Сумма' => 0];
        $bank = ['ОбъектРасчетов_Key' => $object, 'Recorder_Type' => 'StandardODATA.Document_ПоступлениеБезналичныхДенежныхСредств', 'RecordType' => 'Expense', 'Active' => true, 'КОплате' => 30, 'Сумма' => 30];
        $read('pay_1', [$plan, $bank]);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame('30.00', $order->fresh()->onec_paid);

        // The rest comes by cash: paid on the site, the manager sees it.
        $read('pay_1', [$plan, $bank, array_merge($bank, ['Recorder_Type' => 'StandardODATA.Document_ПриходныйКассовыйОрдер', 'КОплате' => 19.98, 'Сумма' => 19.98])]);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(1, $order->managerComments()->where('body', 'like', '%МПУТ-000900 оплачен полностью (49,98 BYN)%')->count());
        $this->assertNull(app(DocumentStatusTaskHandler::class)->enqueueCheck(null));
    }

    public function test_the_stock_of_the_main_warehouse_comes_from_1c_and_decides_ship_or_provide(): void
    {
        OneCReference::query()->create(['kind' => 'warehouse', 'ref_key' => 'aaaaaaaa-0000-0000-0000-00000000000a', 'description' => 'Основной', 'is_folder' => false, 'deletion_mark' => false]);
        OneCReference::query()->create(['kind' => 'warehouse', 'ref_key' => 'aaaaaaaa-0000-0000-0000-00000000000b', 'description' => 'Магазин Борисов', 'is_folder' => false, 'deletion_mark' => false]);
        $item = OneCItem::query()->create(['ref_key' => self::ITEM, 'code' => 'УТ-00000001', 'description' => 'Переход Моно', 'product_id' => $this->card->id]);
        OneCItem::query()->create(['ref_key' => 'bbbbbbbb-0000-0000-0000-000000000002', 'code' => 'УТ-00000002', 'description' => 'Другое', 'stock_on_hand' => 5]);

        $task = app(OneCStock::class)->enqueue(null);
        $this->assertStringContainsString('aaaaaaaa-0000-0000-0000-00000000000a', urldecode($task->payload['queries']['stock']));
        $this->assertStringNotContainsString('00000000000b', $task->payload['queries']['stock']);
        $this->agent()->get('/1c/agent/tasks/next')->assertOk();
        // Two lines of the same item (two series): 3 in stock; the orders hold 2 (1 reserved, 1 to ship) — this 1C keeps
        // the reserve in «Товары к отгрузке», not in «Товары на складах».
        $this->sendPart($task->id, 'stock', 200, ['value' => [
            ['Номенклатура_Key' => self::ITEM, 'ВНаличииBalance' => 2, 'КОтгрузкеBalance' => 0],
            ['Номенклатура_Key' => self::ITEM, 'ВНаличииBalance' => 1, 'КОтгрузкеBalance' => 0],
        ]])->assertOk();
        $this->sendPart($task->id, 'reserves', 200, ['value' => [
            ['Номенклатура_Key' => self::ITEM, 'ВРезервеBalance' => 1, 'КОтгрузкеBalance' => 0],
            ['Номенклатура_Key' => self::ITEM, 'ВРезервеBalance' => 0, 'КОтгрузкеBalance' => 1],
        ]])->assertOk();
        $this->agent()->post('/1c/agent/tasks/'.$task->id.'/result', ['ok' => '1'])->assertOk();

        $item->refresh();
        $this->assertSame([3.0, 2.0], [$item->stock_on_hand, $item->stock_reserved]);
        $this->assertSame('своб. 1 (в нал. 3, резерв 2)', OneCStock::label($item));
        $this->assertSame(0.0, OneCItem::query()->where('code', 'УТ-00000002')->value('stock_on_hand') + 0.0, 'not in the answer — none in stock');

        // The order wants 2: the exchange says 2 in stock, but only 1 is free in 1C.
        $lines = app(OneCOrderExport::class)->lines($this->order());
        $this->assertSame(1.0, $lines[0]['stock']);
    }

    public function test_a_vamexpress_order_goes_by_its_own_scheme(): void
    {
        $retail = OneCOrderProfile::query()->create(['name' => 'Розница СБГ бай', 'document' => OneCOrderProfile::CUSTOMER_ORDER, 'customer_mode' => 'retail',
            'is_active' => true, 'is_default' => true, 'header' => [], 'row_defaults' => []]);
        $vam = OneCOrderProfile::query()->create(['name' => 'ВАМэкспресс', 'document' => OneCOrderProfile::CUSTOMER_ORDER, 'customer_mode' => 'retail',
            'is_active' => true, 'is_default' => false, 'header' => [], 'row_defaults' => [], 'meta' => ['delivery_types' => ['transport']]]);
        $profiles = app(OneCOrderProfiles::class);

        $this->assertSame($vam->id, $profiles->forOrder(false, 'transport')->id);
        $this->assertSame($retail->id, $profiles->forOrder(false, 'courier')->id);
        $this->assertSame($retail->id, $profiles->forOrder(false, 'evropochta')->id, 'Европочта — not a client in 1C yet');
    }

    public function test_an_order_shipped_and_paid_in_1c_is_ready_to_close_and_delivered(): void
    {
        $object = 'ffffffff-0000-0000-0000-000000000901';
        $order = $this->order(['status' => 'processing', 'payment_status' => 'pending', 'onec_order_guid' => 'dddddddd-0000-0000-0000-000000000901',
            'onec_order_number' => 'МПУТ-000733', 'onec_exported_at' => now(), 'onec_checked_at' => now(), 'onec_doc_status' => 'КОбеспечению',
            'onec_posted' => true, 'onec_settlement_key' => $object]);
        $plan = ['ОбъектРасчетов_Key' => $object, 'Recorder_Type' => 'StandardODATA.Document_ЗаказКлиента', 'RecordType' => 'Receipt', 'КОплате' => 49.98, 'Сумма' => 0];
        $sale = ['ОбъектРасчетов_Key' => $object, 'Recorder_Type' => 'StandardODATA.Document_РеализацияТоваровУслуг', 'RecordType' => 'Receipt', 'КОплате' => 0, 'Сумма' => 49.98];
        $cash = ['ОбъектРасчетов_Key' => $object, 'Recorder_Type' => 'StandardODATA.Document_ПриходныйКассовыйОрдер', 'RecordType' => 'Expense', 'КОплате' => 49.98, 'Сумма' => 49.98];
        $payments = app(OneCOrderPayments::class);

        // Shipped, not paid yet: still in work.
        $payments->apply([$plan, $sale]);
        $this->assertSame('processing', $order->fresh()->status);

        // Paid too: «Готов к закрытию» in 1C — delivered on the site.
        $payments->apply([$plan, $sale, $cash]);
        $order->refresh();
        $this->assertSame(['delivered', 'paid'], [$order->status, $order->payment_status]);
        $this->assertSame('По 1С: заказ МПУТ-000733 проведён, «Готов к закрытию».', $order->statusHistory()->latest('id')->value('comment'));
    }

    public function test_an_order_1c_refuses_on_write_goes_again_as_a_draft_and_the_scheme_keeps_drafts(): void
    {
        $this->syncReferences();
        $profile = app(OneCOrderProfiles::class)->fromTemplate(app(OneCReferences::class)->templates()[0], 'Розница СБГ бай', OneCOrderProfile::RETAIL);
        $order = $this->order();
        $export = app(OneCOrderExport::class);
        $first = $export->enqueue($order, $export->defaults($order), $this->admin);
        $this->assertFalse($first->payload['load']);

        $lines = explode("\n", trim($this->agent()->get('/1c/agent/tasks/next')->assertOk()->getContent()));
        $this->assertNotContains('mode|load', $lines);
        $this->agent()->post('/1c/agent/tasks/'.$first->id.'/result', ['ok' => '0',
            'error' => '1С ответила 500: { "odata.error": { "message": { "value": "Ошибка при выполнении обработчика - \'ПриЗаписи\'" } } }'])->assertOk();

        // The same full order again, as a draft: 1C saves it without its write handlers.
        $draft = OneCAgentTask::query()->where('type', 'onec_order')->latest('id')->first();
        $this->assertNotSame($first->id, $draft->id);
        $this->assertStringStartsWith('1С не приняла заказ — отправляю черновиком', $first->fresh()->summary);
        $this->assertTrue($draft->payload['load']);
        $this->assertSame('Тестовск, Ул. Примерная, 1', $draft->payload['body']['АдресДоставки']);
        $this->assertArrayHasKey('ЭтапыГрафикаОплаты', $draft->payload['body']);
        $this->agent('7')->get('/1c/agent/tasks/next')->assertOk()->assertSee('none');
        $lines = explode("\n", trim($this->agent()->get('/1c/agent/tasks/next')->assertOk()->getContent()));
        $this->assertContains('mode|load', $lines);

        $this->agent()->post('/1c/agent/tasks/'.$draft->id.'/result', ['ok' => '1', 'document_number' => 'МПУТ-000801',
            'document_key' => 'dddddddd-0000-0000-0000-000000000801', 'existed' => '0'])->assertOk();
        $this->assertStringContainsString('черновиком: проведите в 1С', $draft->fresh()->summary);
        $this->assertTrue($profile->fresh()->meta['load_mode']);
        $this->assertTrue($export->enqueue($this->order(['number' => 'K-1009']), $export->defaults($order), $this->admin)->payload['load']);

        // A draft refused again is not sent a third time.
        $this->assertNull($export->retryAsDraft($draft->fresh()));

        // The simplest variant still keeps what a manager needs.
        $minimal = OneCOrderExport::simplify(['Date' => 'd', 'Организация_Key' => 'o', 'СкидкиРассчитаны' => true, 'ДополнительнаяИнформацияПоДоставке' => 'адрес',
            'Товары' => [['Номенклатура_Key' => 'i', 'Количество' => 1, 'КлючСвязи' => 1]]], OneCOrderExport::MAX_VARIANT);
        $this->assertSame(['Date' => 'd', 'Организация_Key' => 'o', 'ДополнительнаяИнформацияПоДоставке' => 'адрес',
            'Товары' => [['Номенклатура_Key' => 'i', 'Количество' => 1]]], $minimal);
    }

    public function test_the_accountant_posting_the_order_in_1c_shows_on_the_site(): void
    {
        $order = $this->order(['onec_order_guid' => 'dddddddd-0000-0000-0000-000000000800', 'onec_order_number' => 'МПУТ-000800',
            'onec_profile_id' => 1, 'onec_exported_at' => now(), 'onec_doc_status' => 'КВыполнению', 'onec_posted' => false]);
        $task = app(DocumentStatusTaskHandler::class)->enqueueCheck(null);
        $this->assertArrayHasKey('orders_1', $task->payload['queries']);
        $this->agent()->get('/1c/agent/tasks/next')->assertOk();
        $this->sendPart($task->id, 'orders_1', 200, ['value' => [['Ref_Key' => 'dddddddd-0000-0000-0000-000000000800', 'Number' => 'МПУТ-000800',
            'Posted' => true, 'DeletionMark' => false, 'Статус' => 'Закрыт']]])->assertOk();
        $this->agent()->post('/1c/agent/tasks/'.$task->id.'/result', ['ok' => '1'])->assertOk();
        $order->refresh();
        $this->assertTrue($order->onec_posted);
        $this->assertSame('Закрыт', $order->onec_doc_status);
    }

    public function test_a_line_without_a_1c_item_is_linked_to_a_free_one_never_to_one_of_another_card(): void
    {
        $category = Category::query()->firstOrFail();
        $plate = Product::query()->create(['category_id' => $category->id, 'name' => 'Плита Skamol', 'slug' => 'plita', 'price' => 208]);
        $order = $this->order();
        $item = OrderItem::query()->create(['order_id' => $order->id, 'product_id' => $plate->id, 'product_name' => 'Плита Skamol',
            'price' => 208, 'quantity' => 1, 'total' => 208, 'pricing_type' => 'retail']);
        $export = app(OneCOrderExport::class);
        $this->assertSame('карточка не связана с товаром 1С', collect($export->lines($order->fresh()))->firstWhere('name', 'Плита Skamol')['problem']);

        $free = IntegrationProduct::query()->create(['integration_source_id' => $this->source->id, 'external_id' => 'abababab-0000-0000-0000-000000000001',
            'external_code' => 'УТ-00000002', 'name' => 'Плита каминная SkamoEnclosure 1220/1000/30', 'stock_quantity' => 3, 'price' => 0]);
        $results = $export->searchItems('Skamo');
        $this->assertSame([$free->id], array_keys($results));
        $this->assertSame([], $export->searchItems('Переход'), 'a 1C item of another card is not offered');

        $taken = IntegrationProduct::query()->where('external_id', self::ITEM)->firstOrFail();
        try {
            $export->link($item, $taken->id);
            $this->fail('linked an item of another card');
        } catch (DomainException) {
        }
        $export->link($item, $free->id);
        $this->assertNull(collect($export->lines($order->fresh()))->firstWhere('name', 'Плита Skamol')['problem']);
    }

    public function test_an_order_a_manager_already_entered_in_1c_is_found_and_linked_instead_of_sent_again(): void
    {
        $order = $this->order();
        $this->syncReferences([
            ['Ref_Key' => 'aaaa0000-0000-0000-0000-000000002318', 'Number' => '00УТ-002318', 'Date' => now()->format('Y-m-d\TH:i:s'),
                'Партнер_Key' => self::RETAIL, 'СуммаДокумента' => 49.98, 'Статус' => 'НеСогласован', 'Posted' => false, 'DeletionMark' => false,
                'ДополнительнаяИнформацияПоДоставке' => "Тестовск\nКлиент: Иван Тестов\nТелефон: +375 (29) 000-11-22",
                'Товары' => [['Номенклатура_Key' => self::ITEM, 'Количество' => 2, 'СуммаСНДС' => 49.98]]],
            ['Ref_Key' => 'aaaa0000-0000-0000-0000-000000002319', 'Number' => '00УТ-002319', 'Date' => now()->format('Y-m-d\TH:i:s'),
                'Партнер_Key' => self::RETAIL, 'СуммаДокумента' => 617.54, 'Posted' => true, 'DeletionMark' => false, 'Товары' => []],
        ]);

        $candidates = app(OneCOrderMatcher::class)->candidates($order);
        $this->assertCount(1, $candidates);
        $this->assertSame('00УТ-002318', $candidates[0]['ref']->description);
        $this->assertSame(['телефон', 'сумма', 'товары 1 из 1', 'фамилия'], $candidates[0]['reasons']);

        app(OneCOrderMatcher::class)->link($order, 'aaaa0000-0000-0000-0000-000000002318', $this->admin);
        $order->refresh();
        $this->assertSame('00УТ-002318', $order->onec_order_number);
        $this->assertContains('Заказ уже есть в 1С: 00УТ-002318', app(OneCOrderExport::class)->problems($order, new OneCOrderProfile(['header' => [], 'customer_mode' => 'retail']), ['h' => []]));

        // The same 1C order is not offered to another site order.
        $this->assertSame([], app(OneCOrderMatcher::class)->candidates($this->order(['number' => 'K-1002'])));
        $this->assertNotNull(app(DocumentStatusTaskHandler::class)->enqueueCheck(null));
        $this->assertSame(1, OneCAgentTask::query()->where('type', 'doc_status')->count());
    }

    public function test_linking_with_a_closed_1c_order_delivers_the_site_order_at_once(): void
    {
        $order = $this->order();
        $this->syncReferences([
            ['Ref_Key' => 'aaaa0000-0000-0000-0000-000000000728', 'Number' => 'МПУТ-000728', 'Date' => now()->format('Y-m-d\TH:i:s'),
                'Партнер_Key' => self::RETAIL, 'СуммаДокумента' => 49.98, 'Статус' => 'Закрыт', 'Posted' => true, 'DeletionMark' => false,
                'Товары' => [['Номенклатура_Key' => self::ITEM, 'Количество' => 2, 'СуммаСНДС' => 49.98]]],
        ]);

        app(OneCOrderMatcher::class)->link($order, 'aaaa0000-0000-0000-0000-000000000728', $this->admin);
        $order->refresh();
        $this->assertSame('delivered', $order->status);
        $this->assertSame('По 1С: заказ МПУТ-000728 проведён, «Закрыт».', $order->statusHistory()->latest('id')->value('comment'));
    }

    public function test_a_found_order_shows_only_linking_and_once_linked_only_its_1c_number(): void
    {
        $order = $this->order(['delivery_type' => 'pickup', 'delivery_city' => 'Минск', 'delivery_address' => null]);
        $other = $this->order(['number' => 'K-2002', 'customer_phone' => '+375 33 999-88-77', 'customer_name' => 'Другой Клиент', 'total' => 300]);
        OrderItem::query()->where('order_id', $other->id)->update(['quantity' => 12, 'total' => 300]);
        $this->syncReferences([
            ['Ref_Key' => 'aaaa0000-0000-0000-0000-000000000758', 'Number' => 'МПУТ-000758', 'Date' => now()->format('Y-m-d\TH:i:s'),
                'Партнер_Key' => self::RETAIL, 'СуммаДокумента' => 49.98, 'Статус' => 'КОбеспечению', 'Posted' => false, 'DeletionMark' => false,
                'ДополнительнаяИнформацияПоДоставке' => "Самовывоз\nТелефон: +375 (29) 000-11-22",
                'Товары' => [['Номенклатура_Key' => self::ITEM, 'Количество' => 2, 'СуммаСНДС' => 49.98]]],
        ]);
        app(OneCOrderProfiles::class)->fromTemplate(app(OneCReferences::class)->templates()[0], 'Розница СБГ бай', OneCOrderProfile::RETAIL);
        $this->actingAs($this->admin);

        // The read of 1C orders remembers the match for the order list.
        $this->assertSame('МПУТ-000758', $order->fresh()->onec_match_number);
        $this->assertNull($other->fresh()->onec_match_number);
        Livewire::test(ListOrders::class)->filterTable('onec_order', 'found')
            ->assertCanSeeTableRecords([$order])->assertCanNotSeeTableRecords([$other]);

        // Found in 1C: only «Найден в 1С: МПУТ-000758», no «Создать заказ в 1С».
        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionVisible('onecLink')->assertActionHasLabel('onecLink', 'Найден в 1С: МПУТ-000758')
            ->assertActionHidden('onecOrder')
            ->mountAction('onecLink')->assertSuccessful()
            ->assertActionDataSet(['candidate' => 'aaaa0000-0000-0000-0000-000000000758'])
            ->callMountedAction()->assertHasNoActionErrors()->assertNotified('Заказ связан с 1С: МПУТ-000758');
        $order->refresh();
        $this->assertSame('МПУТ-000758', $order->onec_order_number);
        $this->assertNull($order->onec_match_guid);

        // Linked: one button «1С: МПУТ-000758», the link can be undone from it.
        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionHidden('onecLink')->assertActionHasLabel('onecOrder', '1С: МПУТ-000758')
            ->assertSee('МПУТ-000758')
            ->callAction([TestAction::make('onecOrder'), TestAction::make('onecUnlink')])->assertNotified('Связь с 1С снята');
        $this->assertNull($order->fresh()->onec_order_guid);

        // Nothing like it in 1C: «Создать заказ в 1С», linking by number is inside its window.
        Livewire::test(ViewOrder::class, ['record' => $other->getRouteKey()])
            ->assertActionHidden('onecLink')->assertActionHasLabel('onecOrder', 'Создать заказ в 1С')
            ->callAction([TestAction::make('onecOrder'), TestAction::make('onecLinkManual')], ['manual' => 'aaaa0000-0000-0000-0000-000000000758'])
            ->assertNotified('Заказ связан с 1С: МПУТ-000758');
        $this->assertSame('МПУТ-000758', $other->fresh()->onec_order_number);
    }
}
