<?php

namespace Tests\Feature;

use App\Filament\Resources\CommercialDocuments\CommercialDocumentResource;
use App\Filament\Resources\CommercialDocuments\Pages\CreateCommercialDocument;
use App\Filament\Resources\CrmCounterparties\Pages\ViewCrmCounterparty;
use App\Models\Category;
use App\Models\CommercialDocument;
use App\Models\CrmContact;
use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\Product;
use App\Models\User;
use App\Services\CommercialDocuments\CommercialDocumentReadiness;
use App\Services\CommercialDocuments\CommercialOfferCopilotService;
use App\Services\Crm\BelarusTaxpayerRegistry;
use App\Services\Crm\CrmCounterpartyFromTaxpayerLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class CommercialClientWorkflowImprovementTest extends TestCase
{
    use RefreshDatabase;

    public function test_taxpayer_registry_maps_official_requisites(): void
    {
        Http::fake([
            'grp.nalog.gov.by/*' => Http::response([
                'row' => [
                    'vunp' => '191355768',
                    'vnaimp' => 'Общество с ограниченной ответственностью «СанБизнесГруп»',
                    'vnaimk' => 'ООО «СанБизнесГруп»',
                    'vpadres' => 'г. Минск, ул. Селицкого, д. 39Б',
                    'vkods' => 'Действующий',
                    'dreg' => '2010-07-22',
                    'vmns' => 'Инспекция МНС по Заводскому району г. Минска',
                ],
            ]),
        ]);

        $result = app(BelarusTaxpayerRegistry::class)->lookup('191 355 768');

        $this->assertSame('Реестр МНС Республики Беларусь', $result['source']);
        $this->assertSame('ООО «СанБизнесГруп»', $result['short_name']);
        $this->assertSame('Общество с ограниченной ответственностью «СанБизнесГруп»', $result['legal_name']);
        $this->assertSame('г. Минск, ул. Селицкого, д. 39Б', $result['address']);
    }

    public function test_taxpayer_registry_enriches_existing_crm_record_with_official_requisites(): void
    {
        Http::fake([
            'grp.nalog.gov.by/*' => Http::response([
                'row' => [
                    'vunp' => '193000002',
                    'vnaimp' => 'Общество с ограниченной ответственностью «Официальный клиент»',
                    'vnaimk' => 'ООО «Официальный клиент»',
                    'vpadres' => 'г. Минск, ул. Реестровая, 10',
                    'vkods' => 'Действующий',
                ],
            ]),
        ]);
        $counterparty = CrmCounterparty::query()->create([
            'type' => 'organization',
            'classification' => 'b2b_partner',
            'business_unit' => 'wholesale',
            'status' => 'active',
            'name' => 'Локальный клиент',
            'legal_name' => 'ООО «Локальный клиент»',
            'unp' => '193000002',
            'source' => '1c_trade',
        ]);
        CrmContactPoint::query()->create([
            'counterparty_id' => $counterparty->id,
            'type' => 'email',
            'value' => 'local@example.by',
            'normalized_value' => 'local@example.by',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $result = app(BelarusTaxpayerRegistry::class)->lookup('193000002');

        $this->assertSame('CRM / 1С + Реестр МНС Республики Беларусь', $result['source']);
        $this->assertSame($counterparty->id, $result['crm_counterparty_id']);
        $this->assertSame('ООО «Официальный клиент»', $result['short_name']);
        $this->assertSame('г. Минск, ул. Реестровая, 10', $result['address']);
        $this->assertSame('Действующий', $result['status']);
        $this->assertSame('local@example.by', $result['email']);
        Http::assertSentCount(1);
    }

    public function test_taxpayer_registry_falls_back_to_crm_when_public_registry_is_unavailable(): void
    {
        Http::fake(['grp.nalog.gov.by/*' => Http::response([], 503)]);
        $counterparty = CrmCounterparty::query()->create([
            'type' => 'organization',
            'classification' => 'b2b_partner',
            'business_unit' => 'wholesale',
            'status' => 'active',
            'name' => 'Локальный клиент',
            'legal_name' => 'ООО «Локальный клиент»',
            'unp' => '193000005',
            'legal_address' => 'г. Минск, ул. Локальная, 5',
            'source' => '1c_trade',
        ]);

        $result = app(BelarusTaxpayerRegistry::class)->lookup('193000005');

        $this->assertSame('CRM / 1С', $result['source']);
        $this->assertSame($counterparty->id, $result['crm_counterparty_id']);
        $this->assertSame('г. Минск, ул. Локальная, 5', $result['address']);
    }

    public function test_applying_taxpayer_lookup_creates_and_then_updates_one_crm_counterparty(): void
    {
        $manager = User::factory()->create();
        $service = app(CrmCounterpartyFromTaxpayerLookup::class);

        $created = $service->upsert([
            'unp' => '300012734',
            'short_name' => 'Браслав-коммунальник КУП ЖКХ',
            'legal_name' => 'Коммунальное унитарное предприятие «Браслав-коммунальник»',
            'address' => 'г. Браслав, ул. Дзержинского, 77',
            'email' => 'BRASMTS@MAIL.RU',
            'phone' => '8 02153 68518',
            'registered_at' => '2001-02-03',
        ], $manager->id, 'Браслав ЖКХ');

        $this->assertTrue($created['created']);
        $this->assertSame('tax_registry', $created['counterparty']->source);
        $this->assertSame($manager->id, $created['counterparty']->responsible_user_id);
        $this->assertDatabaseHas('crm_contact_points', [
            'counterparty_id' => $created['counterparty']->id,
            'normalized_value' => 'brasmts@mail.ru',
        ]);

        $updated = $service->upsert([
            'unp' => '300012734',
            'short_name' => 'Браслав-коммунальник',
            'legal_name' => 'КУП «Браслав-коммунальник»',
            'address' => 'новый юридический адрес',
            'email' => 'brasmts@mail.ru',
            'phone' => '8 02153 68518',
        ], $manager->id, 'Браслав ЖКХ');

        $this->assertFalse($updated['created']);
        $this->assertSame($created['counterparty']->id, $updated['counterparty']->id);
        $this->assertSame('новый юридический адрес', $updated['counterparty']->legal_address);
        $this->assertSame(1, CrmCounterparty::query()->where('unp', '300012734')->count());
        $this->assertSame(1, CrmContactPoint::query()->where('counterparty_id', $created['counterparty']->id)->where('type', 'email')->count());
    }

    public function test_selected_crm_contact_remains_valid_after_counterparty_refresh(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $counterparty = CrmCounterparty::query()->create([
            'type' => 'organization',
            'classification' => 'b2b_prospect',
            'business_unit' => 'wholesale',
            'status' => 'lead',
            'name' => 'Браслав-коммунальник',
            'unp' => '300012734',
            'source' => 'tax_registry',
        ]);
        $contact = CrmContact::query()->create([
            'counterparty_id' => $counterparty->id,
            'full_name' => 'Браслав ЖКХ',
            'is_primary' => true,
            'is_active' => true,
        ]);
        CrmContactPoint::query()->create([
            'counterparty_id' => $counterparty->id,
            'contact_id' => $contact->id,
            'type' => 'email',
            'value' => 'brasmts@mail.ru',
            'is_primary' => true,
            'is_active' => true,
        ]);

        Livewire::actingAs($manager)
            ->test(CreateCommercialDocument::class)
            ->fillForm([
                'crm_counterparty_id' => $counterparty->id,
                'crm_contact_id' => $contact->id,
                'client_company' => $counterparty->name,
                'client_name' => $contact->full_name,
                'client_email' => 'brasmts@mail.ru',
                'client_unp' => $counterparty->unp,
                'payment_terms' => '100% предоплата',
                'delivery_terms' => '3 рабочих дня',
            ])
            ->call('create')
            ->assertHasNoErrors(['data.crm_contact_id']);
    }

    public function test_readiness_reports_missing_requisites_and_accepts_a_complete_document(): void
    {
        $counterparty = CrmCounterparty::query()->create([
            'type' => 'organization',
            'classification' => 'b2b_partner',
            'business_unit' => 'wholesale',
            'status' => 'active',
            'name' => 'Готовый клиент',
            'source' => 'manual',
        ]);
        $document = CommercialDocument::query()->create([
            'type' => 'quote',
            'crm_counterparty_id' => $counterparty->id,
            'client_company' => 'Готовый клиент',
            'client_legal_name' => 'ООО «Готовый клиент»',
            'client_email' => 'client@example.com',
            'client_unp' => '193000003',
            'client_address' => 'г. Минск, ул. Коммерческая, 1',
            'payment_terms' => '100% предоплата',
            'delivery_terms' => '3 рабочих дня',
        ]);

        $readiness = app(CommercialDocumentReadiness::class);
        $this->assertContains('в документе нет товаров или услуг', $readiness->issues($document));

        $document->items()->create([
            'name' => 'Насос',
            'quantity' => 1,
            'unit_price' => 100,
            'vat_rate' => 20,
        ]);

        $this->assertSame([], $readiness->issues($document->fresh()));
        $this->assertSame('Готов к отправке', $readiness->summary($document->fresh()));
    }

    public function test_counterparty_card_starts_a_prefilled_commercial_document(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $counterparty = CrmCounterparty::query()->create([
            'type' => 'organization',
            'classification' => 'b2b_partner',
            'business_unit' => 'wholesale',
            'status' => 'active',
            'name' => 'Тестовый покупатель',
            'public_name' => 'Тестовый покупатель',
            'legal_name' => 'ООО «Тестовый покупатель»',
            'unp' => '193000004',
            'legal_address' => 'г. Минск, ул. Покупателей, 2',
            'commercial_discount_percent' => 7.5,
            'commercial_payment_terms' => '50% предоплата',
            'commercial_delivery_terms' => 'Поставка за 5 рабочих дней',
            'source' => 'manual',
        ]);
        $contact = CrmContact::query()->create([
            'counterparty_id' => $counterparty->id,
            'full_name' => 'Анна Иванова',
            'is_primary' => true,
            'is_active' => true,
        ]);
        CrmContactPoint::query()->create([
            'counterparty_id' => $counterparty->id,
            'contact_id' => $contact->id,
            'type' => 'email',
            'value' => 'anna@example.com',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->actingAs($manager);
        Livewire::test(ViewCrmCounterparty::class, ['record' => $counterparty->getRouteKey()])
            ->assertSee('Быстрая связь')
            ->assertSee('Коммерческие условия')
            ->assertSee('Документов пока нет')
            ->assertSee('Заказов пока нет')
            ->callAction('create_commercial_document')
            ->assertRedirect(CommercialDocumentResource::getUrl('create'));

        $this->assertSame($counterparty->id, session('commercial_document_prefill.crm_counterparty_id'));
        $this->assertSame('ООО «Тестовый покупатель»', session('commercial_document_prefill.client_legal_name'));
        $this->assertSame('anna@example.com', session('commercial_document_prefill.client_email'));
        $this->assertSame(7.5, session('commercial_document_prefill.client_default_discount_percent'));
        $this->assertSame('50% предоплата', session('commercial_document_prefill.payment_terms'));
        $this->assertSame('Поставка за 5 рабочих дней', session('commercial_document_prefill.delivery_terms'));
    }

    public function test_readiness_flags_unconfirmed_prices_and_margin_below_the_minimum(): void
    {
        $category = Category::query()->create([
            'name' => 'Проверка цен КП',
            'slug' => 'commercial-readiness-prices',
            'is_active' => true,
        ]);
        $staleProduct = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Котёл с неподтверждённой ценой',
            'slug' => 'commercial-stale-price',
            'price' => 1000,
            'requires_price_confirmation' => true,
        ]);
        $lowMarginProduct = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Насос с низкой маржой',
            'slug' => 'commercial-low-margin',
            'price' => 100,
        ]);
        $document = CommercialDocument::query()->create([
            'type' => 'quote',
            'client_name' => 'Розничный клиент',
            'client_email' => 'client@example.com',
            'payment_terms' => '100% предоплата',
            'delivery_terms' => '3 рабочих дня',
        ]);
        $document->items()->create([
            'product_id' => $staleProduct->id,
            'name' => $staleProduct->name,
            'quantity' => 1,
            'unit_price' => 1000,
        ]);
        $document->items()->create([
            'product_id' => $lowMarginProduct->id,
            'name' => $lowMarginProduct->name,
            'quantity' => 1,
            'unit_price' => 100,
            'discount_percent' => 10,
        ]);

        $this->mock(CommercialOfferCopilotService::class)
            ->shouldReceive('pricing')
            ->once()
            ->with($lowMarginProduct->id, 90.0)
            ->andReturn([
                'purchase_price' => 85.0,
                'margin_percent' => 5.6,
                'minimum_margin_percent' => 10.0,
            ]);

        $issues = app(CommercialDocumentReadiness::class)->issues($document->fresh());

        $this->assertContains('нужно подтвердить актуальность цены: '.$staleProduct->name, $issues);
        $this->assertContains('маржа ниже допустимой: '.$lowMarginProduct->name.' (5,6%)', $issues);
    }

    public function test_manager_can_allow_quote_without_confirmed_purchase_price_and_keeps_a_warning(): void
    {
        $category = Category::query()->create([
            'name' => 'Товары без входной цены',
            'slug' => 'commercial-unconfirmed-purchase-price',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Котёл без подтверждённой цены',
            'slug' => 'commercial-unconfirmed-price-product',
            'price' => 1000,
            'requires_price_confirmation' => true,
        ]);
        $document = CommercialDocument::query()->create([
            'type' => 'quote',
            'client_name' => 'Розничный клиент',
            'client_email' => 'client@example.com',
            'payment_terms' => '100% предоплата',
            'delivery_terms' => '3 рабочих дня',
            'allow_unconfirmed_purchase_price' => true,
        ]);
        $document->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'quantity' => 1,
            'unit_price' => 1000,
        ]);

        $assessment = app(CommercialDocumentReadiness::class)->assessment($document->fresh());

        $this->assertNotContains('нужно подтвердить актуальность цены: '.$product->name, $assessment['issues']);
        $this->assertContains('нет подтверждённой входной цены: '.$product->name, $assessment['warnings']);
    }

    public function test_pdf_template_prints_legal_name_and_address_of_buyer(): void
    {
        $template = file_get_contents(resource_path('views/pdf/commercial-document.blade.php'));

        $this->assertStringContainsString('$document->client_legal_name', $template);
        $this->assertStringContainsString('$document->client_address', $template);
        $this->assertStringContainsString("@include('commercial-documents.delivery-terms')", $template);
    }
}
