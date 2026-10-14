<?php

namespace Tests\Feature;

use App\Filament\Resources\CommercialDocuments\CommercialDocumentResource;
use App\Filament\Resources\CrmCounterparties\Pages\ViewCrmCounterparty;
use App\Models\CommercialDocument;
use App\Models\CrmContact;
use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\User;
use App\Services\CommercialDocuments\CommercialDocumentReadiness;
use App\Services\Crm\BelarusTaxpayerRegistry;
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

    public function test_taxpayer_registry_prefers_existing_crm_and_does_not_call_remote_service(): void
    {
        Http::fake();
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

        $result = app(BelarusTaxpayerRegistry::class)->lookup('193000002');

        $this->assertSame('CRM / 1С', $result['source']);
        $this->assertSame($counterparty->id, $result['crm_counterparty_id']);
        Http::assertNothingSent();
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
            ->callAction('create_commercial_document')
            ->assertRedirect(CommercialDocumentResource::getUrl('create'));

        $this->assertSame($counterparty->id, session('commercial_document_prefill.crm_counterparty_id'));
        $this->assertSame('ООО «Тестовый покупатель»', session('commercial_document_prefill.client_legal_name'));
        $this->assertSame('anna@example.com', session('commercial_document_prefill.client_email'));
        $this->assertSame(7.5, session('commercial_document_prefill.client_default_discount_percent'));
        $this->assertSame('50% предоплата', session('commercial_document_prefill.payment_terms'));
        $this->assertSame('Поставка за 5 рабочих дней', session('commercial_document_prefill.delivery_terms'));
    }

    public function test_pdf_template_prints_legal_name_and_address_of_buyer(): void
    {
        $template = file_get_contents(resource_path('views/pdf/commercial-document.blade.php'));

        $this->assertStringContainsString('$document->client_legal_name', $template);
        $this->assertStringContainsString('$document->client_address', $template);
    }
}
