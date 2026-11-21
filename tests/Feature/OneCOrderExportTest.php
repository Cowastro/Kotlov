<?php

namespace Tests\Feature;

use App\Filament\Resources\OneCOrderProfiles\Pages\EditOneCOrderProfile;
use App\Filament\Resources\OneCOrderProfiles\Pages\ListOneCOrderProfiles;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\OneCAgentTask;
use App\Models\OneCAgentToken;
use App\Models\OneCOrderProfile;
use App\Models\OneCReference;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Integrations\OneCBridge\DocumentStatusTaskHandler;
use App\Services\Integrations\OneCBridge\OneCOrderExport;
use App\Services\Integrations\OneCBridge\OneCOrderMatcher;
use App\Services\Integrations\OneCBridge\OneCOrderProfiles;
use App\Services\Integrations\OneCBridge\OneCReferences;
use DomainException;
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

    private function agent(string $version = '4'): static
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.$this->key, 'X-Kotlov-Agent' => $version]);
    }

    private function sendPart(int $task, string $name, int $code, array $json): TestResponse
    {
        return $this->call('POST', '/1c/agent/tasks/'.$task.'/part?name='.$name.'&code='.$code, [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$this->key,
            'HTTP_X_KOTLOV_AGENT' => '4', 'CONTENT_TYPE' => 'application/octet-stream'], json_encode($json, JSON_UNESCAPED_UNICODE));
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
            'НомерПоДаннымКлиента' => '', 'ДатаСогласования' => '2026-11-20T00:00:00', 'СуммаДокумента' => 440.99,
            'Сделка_Key' => '00000000-0000-0000-0000-000000000000', 'Автор_Key' => 'eeeeeeee-0000-0000-0000-000000000001',
            'Товары' => [['LineNumber' => '1', 'Номенклатура_Key' => 'ffffffff-0000-0000-0000-000000000001', 'Количество' => 1, 'Цена' => 24.99,
                'СтавкаНДС_Key' => 'dd2c0701-8b4a-11ed-a689-1c1b0d1de7c2', 'ВариантОбеспечения' => 'Отгрузить', 'КодСтроки' => 1, 'ИдентификаторСтроки' => 'x']],
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
        $this->assertSame('kotlov-K-1001', $body['НомерПоДаннымКлиента']);
        $this->assertSame(49.98, $body['СуммаДокумента']);
        $this->assertSame(self::ITEM, $body['Товары'][0]['Номенклатура_Key']);
        $this->assertSame(24.99, $body['Товары'][0]['Цена']);
        $this->assertSame(8.33, $body['Товары'][0]['СуммаНДС']);
        $this->assertSame('Отгрузить', $body['Товары'][0]['ВариантОбеспечения']);
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
            ->assertActionVisible('onecOrder')->assertActionVisible('onecLink')
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
}
