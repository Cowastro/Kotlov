<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\OneCAgentTask;
use App\Models\OneCAgentToken;
use App\Models\OneCOrderProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Integrations\OneCBridge\OneCItemCreator;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

class OneCItemCreateTest extends TestCase
{
    use RefreshDatabase;

    private const SAMPLE = 'adcaedcf-5146-11ea-a246-1c1b0d1de7c2';

    private User $admin;

    private string $key;

    private IntegrationSource $source;

    private Category $category;

    private Product $card;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->source = IntegrationSource::query()->updateOrCreate(['code' => 'onec'], ['name' => '1C']);
        $this->category = Category::query()->firstOrCreate(['slug' => 'datchiki'], ['name' => 'Датчики', 'is_active' => true]);
        $linked = Product::query()->create(['category_id' => $this->category->id, 'name' => 'Датчик CO GKB CO777', 'slug' => 'co777', 'price' => 60]);
        IntegrationProduct::query()->create(['integration_source_id' => $this->source->id, 'external_id' => self::SAMPLE, 'external_code' => 'УТ-00000777',
            'name' => 'Датчик загазованности CO GKB CO777', 'price' => 60, 'product_id' => $linked->id, 'match_status' => 'matched', 'matched_at' => now()]);
        $this->card = Product::query()->create(['category_id' => $this->category->id, 'name' => 'Датчик загазованности CO+CH GKB CO888',
            'slug' => 'co888', 'sku' => 'KOTLOV-004900', 'price' => 70]);
        [, $this->key] = OneCAgentToken::issue('test', $this->admin->id);
    }

    private function agent(string $version = '5'): static
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.$this->key, 'X-Kotlov-Agent' => $version]);
    }

    private function sendPart(int $task, string $name, array $json): TestResponse
    {
        return $this->call('POST', '/1c/agent/tasks/'.$task.'/part?name='.$name.'&code=200', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$this->key,
            'HTTP_X_KOTLOV_AGENT' => '5', 'CONTENT_TYPE' => 'application/octet-stream'], json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** The sample item as 1C gives it. */
    private function sample(): array
    {
        return ['Ref_Key' => self::SAMPLE, 'DataVersion' => 'X', 'Code' => 'УТ-00000777', 'Description' => 'Датчик загазованности CO GKB CO777',
            'НаименованиеПолное' => 'Датчик загазованности CO GKB CO777 полный', 'Артикул' => 'CO777', 'DeletionMark' => false, 'IsFolder' => false,
            'Parent_Key' => 'aaaaaaaa-0000-0000-0000-00000000000a', 'ВидНоменклатуры_Key' => 'bbbbbbbb-0000-0000-0000-00000000000b',
            'ЕдиницаИзмерения_Key' => 'cccccccc-0000-0000-0000-00000000000c', 'СтавкаНДС_Key' => 'dd2c0701-8b4a-11ed-a689-1c1b0d1de7c2',
            'Производитель_Key' => 'eeeeeeee-0000-0000-0000-00000000000e', 'ФайлКартинки_Key' => 'ffffffff-0000-0000-0000-00000000000f',
            'ТипНоменклатуры' => 'Товар', 'ИспользованиеХарактеристик' => 'НеИспользовать', 'Описание' => 'Датчик для котельной, старый текст',
            'ВесЧислитель' => 0.5, 'Штрихкоды' => [['Штрихкод' => '481']], 'Parent@navigationLinkUrl' => 'x'];
    }

    /** The agent answers the read task: the sample and what 1C has with the same article / name. */
    private function answerRead(OneCAgentTask $read, array $byArticle = [], array $byName = []): void
    {
        $this->agent()->get('/1c/agent/tasks/next')->assertOk()->assertSee('task|'.$read->id.'|odata_read');
        $this->sendPart($read->id, 'template', ['value' => [$this->sample()]])->assertOk();
        $this->sendPart($read->id, 'by_article', ['value' => $byArticle])->assertOk();
        $this->sendPart($read->id, 'by_name', ['value' => $byName])->assertOk();
        $this->agent()->post('/1c/agent/tasks/'.$read->id.'/result', ['ok' => '1'])->assertOk();
    }

    public function test_a_card_becomes_a_1c_item_made_after_a_similar_one_and_is_linked(): void
    {
        $creator = app(OneCItemCreator::class);
        $this->assertSame(self::SAMPLE, $creator->defaultTemplate($this->card));
        $this->assertNotEmpty($creator->similar($this->card));

        $this->actingAs($this->admin);
        Livewire::test(EditProduct::class, ['record' => $this->card->getRouteKey()])
            ->assertActionHasLabel('onecItem', 'Создать в 1С')
            ->callAction('onecItem', ['template' => self::SAMPLE, 'name' => 'Датчик CO+CH GKB CO888'])->assertHasNoActionErrors();
        $read = OneCAgentTask::query()->where('type', 'odata_read')->sole();
        $this->assertStringContainsString('Ref_Key%20eq%20guid', $read->payload['queries']['template']);

        $this->answerRead($read);
        $this->assertStringStartsWith('Образец УТ-00000777 прочитан', $read->fresh()->summary);
        $create = OneCAgentTask::query()->where('type', 'onec_item')->sole();
        $body = $create->payload['body'];
        $this->assertSame('Датчик CO+CH GKB CO888', $body['Description']);
        $this->assertSame('Датчик загазованности CO+CH GKB CO888', $body['НаименованиеПолное']);
        $this->assertSame('KOTLOV-004900', $body['Артикул']);
        $this->assertSame('bbbbbbbb-0000-0000-0000-00000000000b', $body['ВидНоменклатуры_Key']);
        $this->assertSame('aaaaaaaa-0000-0000-0000-00000000000a', $body['Parent_Key']);
        $this->assertSame('00000000-0000-0000-0000-000000000000', $body['Производитель_Key']);
        $this->assertSame('00000000-0000-0000-0000-000000000000', $body['ФайлКартинки_Key']);
        $this->assertSame('Товар', $body['ТипНоменклатуры']);
        $this->assertArrayNotHasKey('Описание', $body);
        $this->assertArrayNotHasKey('Ref_Key', $body);
        $this->assertArrayNotHasKey('Code', $body);
        $this->assertArrayNotHasKey('Штрихкоды', $body);

        // An agent older than 5 does not get it.
        $this->agent('4')->get('/1c/agent/tasks/next')->assertOk()->assertSee('none');
        $lines = explode("\n", trim($this->agent()->get('/1c/agent/tasks/next')->assertOk()->getContent()));
        $this->assertSame(['task|'.$create->id.'|onec_item', 'entity|Catalog_Номенклатура'], array_slice($lines, 0, 2));
        $this->assertStringStartsWith('find|Catalog_', $lines[2]);
        $this->assertEquals($body, json_decode(base64_decode(substr($lines[3], 5)), true));

        $this->agent()->post('/1c/agent/tasks/'.$create->id.'/result', ['ok' => '1', 'document_code' => 'УТ-00009999',
            'document_key' => 'ABCDABCD-0000-0000-0000-000000009999', 'existed' => '0'])->assertOk();
        $row = $creator->linked($this->card);
        $this->assertSame('abcdabcd-0000-0000-0000-000000009999', $row->external_id);
        $this->assertSame('УТ-00009999', $row->external_code);
        $this->assertStringStartsWith('Товар 1С УТ-00009999 создан и привязан', $create->fresh()->summary);
        Livewire::test(EditProduct::class, ['record' => $this->card->getRouteKey()])->assertActionHasLabel('onecItem', '1С: УТ-00009999');

        $this->expectException(DomainException::class);
        $creator->request($this->card, self::SAMPLE, null, $this->admin);
    }

    public function test_the_sample_is_the_1c_item_most_like_the_card_not_the_latest_of_its_category(): void
    {
        $add = function (string $key, string $name, ?Product $card = null): void {
            IntegrationProduct::query()->create(['integration_source_id' => $this->source->id, 'external_id' => $key, 'name' => $name, 'price' => 0,
                'product_id' => $card?->id, 'match_status' => $card ? 'matched' : 'unmatched', 'matched_at' => now()]);
        };
        $boilerCard = Product::query()->create(['category_id' => $this->category->id, 'name' => 'Электрический котел Arderia E6', 'slug' => 'arderia', 'price' => 900]);
        $add('11111111-0000-0000-0000-000000000001', 'Модуль отопительный электрического конвектора Ballu Evolution Transformer BEC/EVU-2000');
        $add('11111111-0000-0000-0000-000000000002', 'Конвектор электрический Ballu BEC/HMM-1500');
        $add('11111111-0000-0000-0000-000000000003', 'Электрический котел Arderia E6, v3', $boilerCard);
        $convector = Product::query()->create(['category_id' => $this->category->id, 'name' => 'Ballu BEC/EMT-2000', 'slug' => 'ballu-emt', 'sku' => 'KOTLOV-000878', 'price' => 210]);

        $this->assertSame('11111111-0000-0000-0000-000000000002', app(OneCItemCreator::class)->defaultTemplate($convector));
    }

    public function test_an_item_1c_already_has_is_linked_instead_of_a_second_one(): void
    {
        $read = app(OneCItemCreator::class)->request($this->card, self::SAMPLE, null, $this->admin);
        $this->answerRead($read, [['Ref_Key' => '12341234-0000-0000-0000-000000000001', 'Code' => 'УТ-00004900', 'Description' => 'Датчик CO888']]);
        $this->assertSame(0, OneCAgentTask::query()->where('type', 'onec_item')->count());
        $this->assertSame('УТ-00004900', app(OneCItemCreator::class)->linked($this->card)->external_code);
        $this->assertStringStartsWith('В 1С уже есть УТ-00004900', $read->fresh()->summary);
    }

    public function test_a_1c_item_of_another_card_is_not_taken(): void
    {
        $read = app(OneCItemCreator::class)->request($this->card, self::SAMPLE, null, $this->admin);
        $this->answerRead($read, [], [['Ref_Key' => self::SAMPLE, 'Code' => 'УТ-00000777', 'Description' => 'Датчик']]);
        $this->assertNull(app(OneCItemCreator::class)->linked($this->card));
        $this->assertStringContainsString('привязан к другой карточке', $read->fresh()->summary);
    }

    public function test_the_order_form_creates_missing_items_first(): void
    {
        $order = (new Order)->forceFill(['number' => 'K-5005', 'status' => 'confirmed', 'customer_name' => 'Тестов Иван', 'customer_phone' => '+375 29 000-11-22',
            'delivery_type' => 'pickup', 'subtotal' => 140, 'total' => 140]);
        $order->save();
        $item = OrderItem::query()->create(['order_id' => $order->id, 'product_id' => $this->card->id, 'product_name' => $this->card->name,
            'price' => 70, 'quantity' => 2, 'total' => 140, 'pricing_type' => 'retail']);
        OneCOrderProfile::query()->create(['name' => 'Розница СБГ бай', 'customer_mode' => OneCOrderProfile::RETAIL, 'is_default' => true,
            'header' => ['Организация_Key' => '6e310ed3-97e4-11e9-b722-1c1b0d1de7c2'], 'meta' => ['keys' => []]]);
        $this->actingAs($this->admin);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->mountAction('onecOrder')->setActionData(['create' => [$item->id => true]])->callMountedAction()
            ->assertNotified('Товары создаются в 1С: 1');
        $this->assertSame(1, OneCAgentTask::query()->where('type', 'odata_read')->whereMorphedTo('subject', $this->card)->count());
        $this->assertSame(0, OneCAgentTask::query()->where('type', 'onec_order')->count());
    }

    public function test_each_order_line_shows_its_1c_item_or_offers_to_link_one(): void
    {
        $order = (new Order)->forceFill(['number' => 'K-6006', 'status' => 'checking', 'customer_name' => 'Тестов Иван', 'customer_phone' => '+375 29 000-11-22',
            'delivery_type' => 'pickup', 'subtotal' => 200, 'total' => 200]);
        $order->save();
        $linkedCard = Product::query()->where('slug', 'co777')->firstOrFail();
        OrderItem::query()->create(['order_id' => $order->id, 'product_id' => $linkedCard->id, 'product_name' => $linkedCard->name,
            'price' => 60, 'quantity' => 1, 'total' => 60, 'pricing_type' => 'retail']);
        OrderItem::query()->create(['order_id' => $order->id, 'product_id' => $this->card->id, 'product_name' => $this->card->name,
            'price' => 70, 'quantity' => 2, 'total' => 140, 'pricing_type' => 'retail']);
        $this->actingAs($this->admin);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])->assertOk()
            ->assertSee('1С: УТ-00000777')->assertSee('нет в 1С — нажмите ⊕');

        // ⊕ on the line: link the card to a free 1C item right from the order.
        $free = IntegrationProduct::query()->create(['integration_source_id' => $this->source->id, 'external_id' => 'abababab-0000-0000-0000-000000000888',
            'external_code' => 'УТ-00000888', 'name' => 'Датчик загазованности CO+CH GKB CO888', 'price' => 0]);
        $line = $order->items()->orderBy('id')->pluck('product_id')->search($this->card->id);
        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertInfolistActionExists('items.'.$line.'.product_sku', 'orderItemOneC')
            ->callInfolistAction('items.'.$line.'.product_sku', 'orderItemOneC', ['existing' => $free->id])
            ->assertNotified('Карточка привязана к товару 1С УТ-00000888');
        $this->assertSame($this->card->id, $free->fresh()->product_id);
        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])->assertSee('1С: УТ-00000888')->assertDontSee('нет в 1С — нажмите ⊕');
    }
}
