<?php

namespace Tests\Feature;

use App\Models\B2bClient;
use App\Models\B2bOrder;
use App\Models\B2bSavedCart;
use App\Models\CommercialDocument;
use App\Models\CrmCounterparty;
use App\Models\IntegrationSource;
use App\Models\Order;
use App\Models\OrderIntegrationDelivery;
use App\Models\User;
use App\Services\Integrations\CommerceMlOrderImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class B2bCabinetTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_approved_b2b_client_can_open_partner_cabinet(): void
    {
        $retail = User::factory()->create(['client_type' => 'retail', 'b2b_approved' => false]);

        $this->actingAs($retail)->get(route('b2b.cabinet'))->assertForbidden();

        [$partner] = $this->partner();
        $this->actingAs($partner)
            ->get(route('b2b.cabinet'))
            ->assertOk()
            ->assertSeeText('Кабинет партнёра')
            ->assertSeeText('ООО «Партнёр»')
            ->assertSeeText('Сверка взаиморасчётов');
    }

    public function test_partner_profile_updates_b2b_client_user_and_crm_counterparty(): void
    {
        [$partner, $client] = $this->partner();

        $this->actingAs($partner)->withSession(['auth.password_confirmed_at' => time()])->put(route('b2b.cabinet.profile'), [
            'company_name' => 'ООО «Новый партнёр»',
            'legal_name' => 'Общество с ограниченной ответственностью «Новый партнёр»',
            'unp' => '190000001',
            'contact_name' => 'Иван Петров',
            'contact_phone' => '+375291112233',
            'contact_email' => 'office@example.by',
            'default_delivery_city' => 'Минск',
            'default_delivery_address' => 'ул. Складская, 7',
            'legal_address' => 'г. Минск, ул. Тестовая, 1',
            'postal_address' => '220000, г. Минск, а/я 1',
            'bank_name' => 'Тестовый банк',
            'bank_bic' => 'pjcbby2x',
            'bank_iban' => 'BY44 PJCB 3012 0211 4710 0000 0933',
            'authorized_representative' => 'Иванов Иван Иванович',
            'representative_position' => 'Директор',
            'authority_basis' => 'Устава',
        ])->assertRedirect(route('b2b.cabinet', ['section' => 'profile']));

        $client->refresh();
        $this->assertSame('ООО «Новый партнёр»', $client->company_name);
        $this->assertSame('Минск', $client->default_delivery_city);
        $this->assertSame('ул. Складская, 7', $client->default_delivery_address);
        $this->assertNotNull($client->counterparty_id);
        $this->assertDatabaseHas('crm_counterparties', [
            'id' => $client->counterparty_id,
            'unp' => '190000001',
            'bank_bic' => 'PJCBBY2X',
            'bank_iban' => 'BY44PJCB30120211471000000933',
            'authorized_representative' => 'Иванов Иван Иванович',
            'authority_basis' => 'Устава',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $partner->id,
            'name' => 'Иван Петров',
            'phone' => '+375291112233',
        ]);

        $this->actingAs($partner->fresh())
            ->get(route('b2b.cabinet', ['section' => 'profile']))
            ->assertOk()
            ->assertSeeText('100%')
            ->assertSeeText('Все данные для счетов и договоров заполнены.');
    }

    public function test_partner_can_save_current_b2b_cart_and_cannot_delete_another_clients_cart(): void
    {
        [$partner] = $this->partner();
        [$other] = $this->partner('other@example.by', 'ООО «Другой»');
        $foreignCart = B2bSavedCart::query()->create([
            'user_id' => $other->id,
            'name' => 'Чужая корзина',
            'items' => [['id' => 999, 'quantity' => 1]],
            'items_count' => 1,
            'total' => 50,
        ]);

        $this->actingAs($partner)
            ->withSession(['cart' => [
                10 => ['id' => 10, 'name' => 'B2B товар', 'quantity' => 3, 'price' => 12.5, 'pricing_type' => 'b2b'],
                11 => ['id' => 11, 'name' => 'Розничный товар', 'quantity' => 1, 'price' => 9, 'pricing_type' => 'retail'],
            ]])
            ->post(route('b2b.cabinet.carts.store'), ['name' => 'Объект на Лесной'])
            ->assertRedirect(route('b2b.cabinet', ['section' => 'carts']));

        $this->assertDatabaseHas('b2b_saved_carts', [
            'user_id' => $partner->id,
            'name' => 'Объект на Лесной',
            'items_count' => 3,
            'total' => 37.5,
        ]);

        $this->actingAs($partner)
            ->delete(route('b2b.cabinet.carts.destroy', $foreignCart))
            ->assertNotFound();
        $this->assertDatabaseHas('b2b_saved_carts', ['id' => $foreignCart->id]);
    }

    public function test_cabinet_only_lists_orders_owned_by_authenticated_partner(): void
    {
        [$partner, $partnerClient] = $this->partner();
        [$other, $otherClient] = $this->partner('orders@example.by', 'ООО «Заказы»');
        foreach ([[$partner, $partnerClient, 'B2B-OWN-1'], [$other, $otherClient, 'B2B-FOREIGN-1']] as [$user, $client, $number]) {
            B2bOrder::query()->create([
                'b2b_client_id' => $client->id,
                'created_by' => $user->id,
                'number' => $number,
                'status' => 'new',
                'contact_name' => $user->name,
                'contact_phone' => '+375290000000',
                'company_name' => $client->company_name,
                'subtotal' => 100,
                'total' => 100,
            ]);
        }

        $this->actingAs($partner)
            ->get(route('b2b.cabinet', ['section' => 'orders']))
            ->assertOk()
            ->assertSeeText('B2B-OWN-1')
            ->assertDontSeeText('B2B-FOREIGN-1');
    }

    public function test_partner_can_open_only_its_own_commercial_document_pdf(): void
    {
        [$partner] = $this->partner();
        [$other] = $this->partner('pdf-other@example.by', 'ООО «Другой PDF»');
        $own = CommercialDocument::query()->create([
            'type' => 'invoice',
            'status' => 'sent',
            'client_name' => $partner->name,
            'client_email' => $partner->email,
            'currency' => 'BYN',
            'subtotal' => 125,
            'total' => 125,
        ]);
        $foreign = CommercialDocument::query()->create([
            'type' => 'invoice',
            'status' => 'sent',
            'client_name' => $other->name,
            'client_email' => $other->email,
            'currency' => 'BYN',
            'subtotal' => 250,
            'total' => 250,
        ]);

        $this->actingAs($partner)
            ->get(route('b2b.cabinet.documents.pdf', $own))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('cache-control', 'max-age=0, no-store, private');
        $this->assertDatabaseHas('b2b_client_audit_logs', [
            'b2b_client_id' => $partner->activeB2bClient()->id,
            'actor_user_id' => $partner->id,
            'event' => 'document_downloaded',
        ]);
        $this->actingAs($partner)
            ->get(route('b2b.cabinet.documents.pdf', $foreign))
            ->assertNotFound();
    }

    public function test_partner_generates_one_immutable_invoice_from_confirmed_b2b_order_and_audit_is_visible(): void
    {
        [$partner, $client] = $this->partner();
        $this->completeInvoiceProfile($client);
        $order = $this->order($partner, 'B2B-INVOICE-1');
        $order->update([
            'status' => 'confirmed',
            'availability_confirmed_at' => now(),
            'company_name' => $client->company_name,
            'company_unp' => $client->unp,
            'company_address' => $client->counterparty->legal_address,
            'company_email' => $client->contact_email,
            'delivery_type' => 'transport',
            'delivery_address' => 'Минск, Складская, 5',
            'delivery_price' => 50,
            'discount' => 5,
            'subtotal' => 200,
            'total' => 245,
        ]);
        $order->items()->create([
            'product_name' => 'Подтверждённый B2B товар',
            'product_sku' => 'B2B-100',
            'price' => 100,
            'quantity' => 2,
            'pricing_type' => 'b2b',
            'supply_captured_at' => now(),
            'fulfillment_route' => 'own_stock',
            'fulfillment_availability_status' => 'reserved',
            'fulfillment_confirmed_at' => now(),
        ]);
        $draft = CommercialDocument::query()->create([
            'type' => 'invoice',
            'status' => 'draft',
            'client_email' => $partner->email,
            'currency' => 'BYN',
        ]);
        $draft->items()->create([
            'name' => 'Старая строка',
            'quantity' => 1,
            'unit_price' => 1,
        ]);
        $order->update(['commercial_document_id' => $draft->id]);

        $this->actingAs($partner)
            ->post(route('b2b.cabinet.orders.invoice', $order))
            ->assertRedirect(route('b2b.cabinet', ['section' => 'documents']))
            ->assertSessionHas('success');

        $invoice = $order->fresh()->commercialDocument()->with('items')->firstOrFail();
        $this->assertSame($draft->id, $invoice->id);
        $this->assertSame('sent', $invoice->status);
        $this->assertSame('245.00', $invoice->total);
        $this->assertSame('5.00', $invoice->discount_total);
        $this->assertCount(1, $invoice->items);
        $this->assertSame('Подтверждённый B2B товар', $invoice->items->first()->name);
        $this->assertSame('100.00', $invoice->items->first()->unit_price);
        $this->assertDatabaseHas('b2b_client_audit_logs', [
            'b2b_client_id' => $client->id,
            'actor_user_id' => $partner->id,
            'event' => 'invoice_generated',
        ]);

        $this->post(route('b2b.cabinet.orders.invoice', $order))
            ->assertRedirect(route('b2b.cabinet', ['section' => 'documents']));
        $this->assertSame(1, CommercialDocument::query()->where('type', 'invoice')->count());
        $this->assertSame(1, $client->auditLogs()->where('event', 'invoice_generated')->count());

        $invoice->update([
            'accounting_number' => 'СФ-00000456',
            'accounting_status' => 'Проведён',
            'accounting_payment_status' => 'paid',
            'accounting_confirmed_at' => now(),
        ]);
        $client->auditLogs()->create([
            'event' => 'invoice_accounting_synced',
            'metadata' => [
                'document_number' => $invoice->number,
                'accounting_number' => 'СФ-00000456',
                'order_number' => $order->number,
            ],
        ]);

        $this->get(route('b2b.cabinet', ['section' => 'documents']))
            ->assertOk()
            ->assertSeeText($invoice->number)
            ->assertSeeText('Подтверждено 1С')
            ->assertSeeText('счёт № СФ-00000456')
            ->assertSeeText('Оплачен')
            ->assertSeeText('История действий с документами')
            ->assertSeeText('Сформирован счёт')
            ->assertSeeText('Счёт подтверждён 1С')
            ->assertSeeText('заказ B2B-INVOICE-1');
    }

    public function test_invoice_generation_rejects_unconfirmed_non_b2b_and_foreign_orders(): void
    {
        [$partner, $client] = $this->partner();
        $this->completeInvoiceProfile($client);
        $unconfirmed = $this->order($partner, 'B2B-NOT-CONFIRMED');

        $this->actingAs($partner)
            ->post(route('b2b.cabinet.orders.invoice', $unconfirmed))
            ->assertSessionHasErrors('invoice');
        $this->assertNull($unconfirmed->fresh()->commercial_document_id);

        $retail = $this->order($partner, 'B2B-RETAIL-LINE');
        $retail->update(['status' => 'confirmed', 'availability_confirmed_at' => now()]);
        $retail->items()->create([
            'product_name' => 'Розничная строка',
            'price' => 100,
            'quantity' => 1,
            'pricing_type' => 'retail',
            'supply_captured_at' => now(),
            'fulfillment_confirmed_at' => now(),
        ]);
        $this->post(route('b2b.cabinet.orders.invoice', $retail))
            ->assertSessionHasErrors('invoice');

        [$other] = $this->partner('foreign-invoice@example.by', 'ООО «Чужой счёт»');
        $foreign = $this->order($other, 'B2B-FOREIGN-INVOICE');
        $this->post(route('b2b.cabinet.orders.invoice', $foreign))->assertNotFound();
    }

    public function test_onec_invoice_confirmation_is_synced_to_b2b_document_once(): void
    {
        [$partner, $client] = $this->partner('onec-invoice@example.by', 'ООО «Тест 1С»');
        $source = IntegrationSource::query()->firstOrCreate(['code' => 'onec'], ['name' => '1С']);
        $invoice = CommercialDocument::query()->create([
            'type' => 'invoice',
            'status' => 'sent',
            'client_email' => $partner->email,
            'currency' => 'BYN',
            'total' => 120,
        ]);
        $order = $this->order($partner, 'ORD-1C-INVOICE-CONFIRMATION');
        $order->update([
            'commercial_document_id' => $invoice->id,
            'status' => 'processing',
            'onec_exported_at' => now(),
            'b2b_commercial_terms_snapshot' => ['b2b_client_id' => $client->id],
        ]);
        OrderIntegrationDelivery::query()->create([
            'order_id' => $order->id,
            'integration_source_id' => $source->id,
            'status' => OrderIntegrationDelivery::STATUS_SENT,
            'external_id' => 'kotlov-order-'.$order->id,
            'exported_at' => now(),
        ]);
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<КоммерческаяИнформация>
  <Документ>
    <Ид>kotlov-order-{$order->id}</Ид>
    <Номер>{$order->number}</Номер>
    <ЗначенияРеквизитов>
      <ЗначениеРеквизита><Наименование>GUID счёта 1С</Наименование><Значение>invoice-guid-456</Значение></ЗначениеРеквизита>
      <ЗначениеРеквизита><Наименование>Номер счёта 1С</Наименование><Значение>СФ-00000456</Значение></ЗначениеРеквизита>
      <ЗначениеРеквизита><Наименование>Статус счёта</Наименование><Значение>Проведён</Значение></ЗначениеРеквизита>
      <ЗначениеРеквизита><Наименование>Статус оплаты</Наименование><Значение>Оплачен</Значение></ЗначениеРеквизита>
    </ЗначенияРеквизитов>
  </Документ>
</КоммерческаяИнформация>
XML;

        $importer = app(CommerceMlOrderImporter::class);
        $this->assertSame(1, $importer->import($xml, $source)['matched']);
        $this->assertSame(1, $importer->import($xml, $source)['matched']);

        $exactPaymentXml = str_replace(
            '    </ЗначенияРеквизитов>',
            <<<'XML'
      <ЗначениеРеквизита><Наименование>GUID платежа 1С</Наименование><Значение>payment-guid-182</Значение></ЗначениеРеквизита>
      <ЗначениеРеквизита><Наименование>Номер платёжного поручения</Наименование><Значение>ПП-00000182</Значение></ЗначениеРеквизита>
      <ЗначениеРеквизита><Наименование>Дата платежа 1С</Наименование><Значение>20.10.2026</Значение></ЗначениеРеквизита>
      <ЗначениеРеквизита><Наименование>Сумма платежа 1С</Наименование><Значение>80,00 BYN</Значение></ЗначениеРеквизита>
      <ЗначениеРеквизита><Наименование>GUID корректировки 1С</Наименование><Значение>adjustment-guid-19</Значение></ЗначениеРеквизита>
      <ЗначениеРеквизита><Наименование>Номер корректировки 1С</Наименование><Значение>КР-00000019</Значение></ЗначениеРеквизита>
      <ЗначениеРеквизита><Наименование>Дата корректировки 1С</Наименование><Значение>22.10.2026</Значение></ЗначениеРеквизита>
      <ЗначениеРеквизита><Наименование>Сумма корректировки 1С</Наименование><Значение>20,00 BYN</Значение></ЗначениеРеквизита>
      <ЗначениеРеквизита><Наименование>Вид корректировки</Наименование><Значение>Уменьшение</Значение></ЗначениеРеквизита>
    </ЗначенияРеквизитов>
XML,
            $xml,
        );
        $this->assertSame(1, $importer->import($exactPaymentXml, $source)['matched']);
        $this->assertSame(1, $importer->import($exactPaymentXml, $source)['matched']);

        $invoice->refresh();
        $this->assertSame($source->id, $invoice->accounting_source_id);
        $this->assertSame('invoice-guid-456', $invoice->accounting_external_id);
        $this->assertSame('СФ-00000456', $invoice->accounting_number);
        $this->assertSame('Проведён', $invoice->accounting_status);
        $this->assertSame('paid', $invoice->accounting_payment_status);
        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($invoice->accounting_confirmed_at);
        $this->assertNotNull($invoice->closed_at);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(1, $client->auditLogs()->where('event', 'invoice_accounting_synced')->count());
        $this->assertSame(2, $client->auditLogs()->where('event', 'accounting_ledger_synced')->count());
        $this->assertDatabaseCount('b2b_accounting_entries', 3);
        $this->assertDatabaseHas('b2b_accounting_entries', [
            'b2b_client_id' => $client->id,
            'entry_type' => 'charge',
            'document_number' => 'СФ-00000456',
            'debit' => 120,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('b2b_accounting_entries', [
            'b2b_client_id' => $client->id,
            'entry_type' => 'payment',
            'external_key' => 'payment:payment-guid-182',
            'document_number' => 'ПП-00000182',
            'occurred_at' => '2026-10-20 00:00:00',
            'debit' => 0,
            'credit' => 80,
        ]);
        $this->assertSame(
            'payment-guid-182',
            $client->accountingEntries()->where('entry_type', 'payment')->sole()->metadata['external_document_id']
        );
        $this->assertDatabaseHas('b2b_accounting_entries', [
            'b2b_client_id' => $client->id,
            'entry_type' => 'adjustment',
            'external_key' => 'adjustment:adjustment-guid-19',
            'document_number' => 'КР-00000019',
            'occurred_at' => '2026-10-22 00:00:00',
            'debit' => 0,
            'credit' => 20,
        ]);

        $this->actingAs($partner)
            ->get(route('b2b.cabinet', ['section' => 'reconciliation']))
            ->assertOk()
            ->assertSeeText('Акт по подтверждённым данным 1С')
            ->assertSeeText('Акт сверки по данным 1С')
            ->assertSeeText('СФ-00000456 · Начисление по счёту')
            ->assertSeeText('ПП-00000182 · Оплата')
            ->assertSeeText('КР-00000019 · Корректировка')
            ->assertSeeText('20,00 BYN')
            ->assertSeeText('Закупочные цены и внутренняя маржа не раскрываются')
            ->assertDontSeeText('Предварительная сверка сформирована');
    }

    /** @return array{User, B2bClient} */
    private function partner(string $email = 'partner@example.by', string $company = 'ООО «Партнёр»'): array
    {
        $user = User::factory()->create([
            'email' => $email,
            'client_type' => 'wholesale',
            'b2b_approved' => true,
            'company_name' => $company,
            'company_inn' => '190000000',
            'is_active' => true,
        ]);
        $client = B2bClient::query()->create([
            'user_id' => $user->id,
            'status' => 'approved',
            'partner_type' => 'wholesale',
            'company_name' => $company,
            'unp' => '190000000',
            'contact_name' => $user->name,
            'contact_email' => $email,
        ]);

        return [$user->fresh(), $client];
    }

    private function order(User $user, string $number): Order
    {
        return Order::query()->create([
            'user_id' => $user->id,
            'number' => $number,
            'status' => 'new',
            'customer_name' => $user->name,
            'customer_phone' => '+375290000000',
            'customer_email' => $user->email,
            'delivery_type' => 'pickup',
            'payment_type' => 'invoice',
            'payment_status' => 'pending',
            'subtotal' => 100,
            'total' => 100,
        ]);
    }

    private function completeInvoiceProfile(B2bClient $client): void
    {
        $counterparty = CrmCounterparty::query()->create([
            'type' => 'organization',
            'classification' => 'b2b_partner',
            'business_unit' => 'wholesale',
            'status' => 'active',
            'source' => 'manual',
            'name' => $client->company_name,
            'public_name' => $client->company_name,
            'legal_name' => 'Общество с ограниченной ответственностью «Партнёр»',
            'unp' => $client->unp,
            'legal_address' => '220000, г. Минск, ул. Партнёрская, 1',
        ]);
        $client->update([
            'counterparty_id' => $counterparty->id,
            'contact_phone' => '+375291234567',
        ]);
        $client->refresh()->load('counterparty');
    }
}
