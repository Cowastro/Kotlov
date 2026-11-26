<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Category;
use App\Models\CrmCounterparty;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\OneCAgentTask;
use App\Models\OneCAgentToken;
use App\Models\OneCOrderProfile;
use App\Models\OneCReference;
use App\Models\OneCSupplierOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Integrations\OneCBridge\DocumentStatusTaskHandler;
use App\Services\Integrations\OneCBridge\OneCOrderJourney;
use App\Services\Integrations\OneCBridge\OneCOrderProfiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class OneCSupplierOrderTest extends TestCase
{
    use RefreshDatabase;

    private const ITEM = 'adcaedcf-5146-11ea-a246-1c1b0d1de7c2';

    private const ORG = 'c5322188-0f48-11e9-bad6-1c1b0d1de7c2';

    private const COUNTERPARTY = 'cccccccc-0000-0000-0000-000000000001';

    private const PARTNER = 'cccccccc-0000-0000-0000-0000000000aa';

    private const CONTRACT = 'dddddddd-0000-0000-0000-000000000001';

    private const VAT20 = 'dd2c0701-8b4a-11ed-a689-1c1b0d1de7c2';

    /** A supplier order of СанБизнесГруп as 1C gives it. */
    private function sample(): array
    {
        return ['Ref_Key' => 'f5d08765-0000-0000-0000-000000000655', 'DataVersion' => 'X', 'Number' => '00УТ-000655', 'Date' => '2026-11-20T15:43:26',
            'Posted' => true, 'DeletionMark' => false, 'Партнер_Key' => '78154467-0000-0000-0000-000000000001', 'Контрагент_Key' => '53bd873c-0000-0000-0000-000000000001',
            'Организация_Key' => self::ORG, 'Склад_Key' => 'c532218f-0f48-11e9-bad6-1c1b0d1de7c2', 'Валюта_Key' => '11867b41-09b8-11e9-bad6-1c1b0d1de7c2',
            'Договор_Key' => '99999999-0000-0000-0000-000000000001', 'ЦенаВключаетНДС' => false, 'Статус' => 'Закрыт', 'СуммаДокумента' => 1.2,
            'НалогообложениеНДС' => 'ПродажаОблагаетсяНДС', 'ХозяйственнаяОперация' => 'ЗакупкаУПоставщика', 'ПорядокРасчетов' => 'ПоДоговорамКонтрагентов',
            'ДатаСогласования' => '2026-11-20T00:00:00', 'ЖелаемаяДатаПоступления' => '0001-01-01T00:00:00', 'ДатаПоступления' => '2026-11-24T00:00:00', 'Комментарий' => 'старый', 'ИдентификаторПлатежа' => 'ЗП2611', 'МаксимальныйКодСтроки' => '1',
            'Товары' => [['LineNumber' => '1', 'Номенклатура_Key' => 'ffffffff-0000-0000-0000-000000000001', 'Количество' => 1, 'Цена' => 1,
                'СтавкаНДС_Key' => self::VAT20, 'ВидЦеныПоставщика_Key' => 'eeeeeeee-0000-0000-0000-000000000001', 'КодСтроки' => 1, 'Отменено' => false, 'ДатаПоступления' => '2026-11-24T00:00:00']],
            'ЭтапыГрафикаОплаты' => [['LineNumber' => '1', 'ВариантОплаты' => 'КредитПослеПоступления', 'ВариантОтсчета' => 'ОтДатыЗаказа',
                'ДатаПлатежа' => '2026-11-20T00:00:00', 'ПроцентПлатежа' => 100, 'СуммаПлатежа' => 1.2]]];
    }

    public function test_the_lines_bought_from_a_supplier_become_a_1c_supplier_order(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $source = IntegrationSource::query()->updateOrCreate(['code' => 'onec'], ['name' => '1C']);
        $category = Category::query()->firstOrCreate(['slug' => 'obogrev'], ['name' => 'Обогрев', 'is_active' => true]);
        $card = Product::query()->create(['category_id' => $category->id, 'name' => 'Ballu BEC/EMT-2000', 'slug' => 'ballu', 'sku' => 'KOTLOV-000878', 'price' => 210]);
        IntegrationProduct::query()->create(['integration_source_id' => $source->id, 'external_id' => self::ITEM, 'external_code' => 'УТ-00000878',
            'name' => 'Конвектор Ballu BEC/EMT-2000', 'price' => 0, 'product_id' => $card->id, 'match_status' => 'matched']);
        $crm = CrmCounterparty::query()->create(['name' => 'Поставщик-тест', 'type' => 'organization', 'classification' => 'supplier', 'unp' => '100000002']);
        $supplier = Supplier::query()->create(['code' => 'test-supplier', 'name' => 'Поставщик-тест', 'crm_counterparty_id' => $crm->id]);
        foreach ([['counterparty', self::COUNTERPARTY, 'Поставщик-тест ООО', self::PARTNER, '100000002', []],
            ['contract', self::CONTRACT, 'Договор 1', self::PARTNER, null, ['Контрагент_Key' => self::COUNTERPARTY, 'Организация_Key' => self::ORG]],
            ['vat_rate', self::VAT20, '20%', null, null, []]] as [$kind, $key, $name, $owner, $inn, $data]) {
            OneCReference::query()->create(['kind' => $kind, 'ref_key' => $key, 'description' => $name, 'owner_key' => $owner, 'inn' => $inn, 'data' => $data]);
        }
        $profile = app(OneCOrderProfiles::class)->fromTemplate($this->sample(), 'Закупка СанБизнесГруп', OneCOrderProfile::RETAIL, OneCOrderProfile::SUPPLIER_ORDER);
        $this->assertSame(OneCOrderProfile::SUPPLIER_ORDER, $profile->document);

        $order = (new Order)->forceFill(['number' => 'K-7007', 'status' => 'confirmed', 'customer_name' => 'Тестов Иван', 'customer_phone' => '+375 29 000-11-22',
            'delivery_type' => 'pickup', 'subtotal' => 210, 'total' => 210, 'onec_order_number' => 'МПУТ-000800']);
        $order->save();
        OrderItem::query()->create(['order_id' => $order->id, 'product_id' => $card->id, 'product_name' => $card->name, 'price' => 210, 'quantity' => 1, 'total' => 210,
            'pricing_type' => 'retail', 'fulfillment_route' => 'supplier_purchase', 'fulfillment_supplier_id' => $supplier->id, 'fulfillment_purchase_price' => 168, 'supply_delivery_days' => 5, 'fulfillment_confirmed_at' => now(), 'fulfillment_confirmed_by' => $admin->id]);
        app(\App\Services\Orders\OrderWorkflow::class)->advance($order->fresh(), $admin);
        app(\App\Services\Orders\OrderDeliveryPreparation::class)->confirm($order->fresh(), $admin, [
            'method' => $order->delivery_type, 'from' => 'Тестовый склад', 'to' => 'Согласованный адрес выдачи',
            'date' => now()->addDay()->toDateString(),
        ]);


        $order->refresh();
        $this->actingAs($admin);

        // The order shows what to do next in 1C, and the list finds it among «нужно заказать у поставщика».
        $journey = app(OneCOrderJourney::class);
        $this->assertSame('Нажмите «Создать заказ в 1С».', $journey->next($order));
        $steps = collect($journey->steps($order))->keyBy('title');
        $this->assertSame('done', $steps['Товары связаны с 1С']['state']);
        $this->assertSame('todo', $steps['Заказы поставщикам в 1С']['state']);
        $this->assertSame('Поставщик-тест: не заказан', $steps['Заказы поставщикам в 1С']['detail']);
        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])->assertSee('1С: что сделано и что дальше')->assertSee('Создать заказ в 1С');
        $listed = fn (): array => tap(Livewire::test(ListOrders::class), fn ($p) => $p->filterTable('onec_order', 'supplier_todo'))
            ->instance()->getTableRecords()->pluck('id')->all();
        $this->assertSame([$order->id], $listed());
        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionVisible('onecSupplierOrders')->assertActionHasLabel('onecSupplierOrders', 'Поставщикам в 1С: 0 из 1')
            ->mountAction('onecSupplierOrders')
            ->assertActionDataSet(['counterparty.s'.$supplier->id => self::COUNTERPARTY, 'contract.s'.$supplier->id => self::CONTRACT, 'send.s'.$supplier->id => true])
            ->callMountedAction()->assertHasNoActionErrors()->assertNotified('В очереди агента 1С: Поставщик-тест');

        $task = OneCAgentTask::query()->where('type', 'onec_supplier_order')->sole();
        $body = $task->payload['body'];
        $this->assertSame(self::COUNTERPARTY, $body['Контрагент_Key']);
        $this->assertSame(self::PARTNER, $body['Партнер_Key']);
        $this->assertSame(self::CONTRACT, $body['Договор_Key']);
        $this->assertSame('ЗакупкаУПоставщика', $body['ХозяйственнаяОперация']);
        $this->assertSame('Под заказ клиента kotlov.by №K-7007 (1С: МПУТ-000800)', $body['Комментарий']);
        $this->assertArrayNotHasKey('ИдентификаторПлатежа', $body);
        $this->assertEquals(140, $body['Товары'][0]['Цена']);
        $this->assertEquals(168, $body['Товары'][0]['СуммаСНДС']);
        $this->assertSame(self::ITEM, $body['Товары'][0]['Номенклатура_Key']);
        $this->assertArrayNotHasKey('ВидЦеныПоставщика_Key', $body['Товары'][0]);
        // The goods come in 5 days (the supplier's delivery time of the line).
        $arrive = now('Europe/Minsk')->startOfDay()->addDays(5)->format('Y-m-d\TH:i:s');
        $this->assertSame($arrive, $body['ЖелаемаяДатаПоступления']);
        $this->assertSame($arrive, $body['ДатаПоступления']);
        $this->assertSame($arrive, $body['Товары'][0]['ДатаПоступления']);
        $this->assertEquals(168, $body['ЭтапыГрафикаОплаты'][0]['СуммаПлатежа']);

        [, $key] = OneCAgentToken::issue('test', $admin->id);
        $agent = fn (string $v) => $this->withHeaders(['Authorization' => 'Bearer '.$key, 'X-Kotlov-Agent' => $v]);
        $agent('5')->get('/1c/agent/tasks/next')->assertOk()->assertSee('none');
        $lines = explode("\n", trim($agent('6')->get('/1c/agent/tasks/next')->assertOk()->getContent()));
        $this->assertSame(['task|'.$task->id.'|onec_supplier_order', 'entity|Document_ЗаказПоставщику'], array_slice($lines, 0, 2));
        $agent('6')->post('/1c/agent/tasks/'.$task->id.'/result', ['ok' => '1', 'document_number' => '00УТ-000700',
            'document_key' => 'abcdabcd-0000-0000-0000-000000000700', 'existed' => '0'])->assertOk();
        $record = OneCSupplierOrder::query()->sole();
        $this->assertSame('00УТ-000700', $record->onec_order_number);
        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])->assertActionHasLabel('onecSupplierOrders', 'Поставщикам в 1С: 1 из 1');
        $this->assertSame([], $listed());
        $this->assertStringStartsWith('Поставщик-тест: 00УТ-000700', collect($journey->steps($order->fresh()))->keyBy('title')['Заказы поставщикам в 1С']['detail']);

        // The accountant posts it: the site sees it at the next check.
        $check = app(DocumentStatusTaskHandler::class)->enqueueCheck(null);
        $agent('6')->get('/1c/agent/tasks/next')->assertOk();
        $this->call('POST', '/1c/agent/tasks/'.$check->id.'/part?name=supplier_orders_1&code=200', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$key,
            'HTTP_X_KOTLOV_AGENT' => '6', 'CONTENT_TYPE' => 'application/octet-stream'],
            json_encode(['value' => [['Ref_Key' => 'abcdabcd-0000-0000-0000-000000000700', 'Number' => '00УТ-000700', 'Posted' => true, 'DeletionMark' => false, 'Статус' => 'Закрыт']]], JSON_UNESCAPED_UNICODE))->assertOk();
        $agent('6')->post('/1c/agent/tasks/'.$check->id.'/result', ['ok' => '1'])->assertOk();
        $this->assertTrue($record->fresh()->onec_posted);
    }
}
