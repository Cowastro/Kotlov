<?php

namespace Tests\Feature;

use App\Models\CommercialDocument;
use App\Models\CrmContact;
use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\User;
use App\Services\CommercialDocuments\CommercialDocumentClientResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialDocumentClientResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_links_an_existing_counterparty_by_email_without_creating_a_duplicate(): void
    {
        $counterparty = CrmCounterparty::query()->create([
            'type' => 'organization',
            'classification' => 'b2b_partner',
            'business_unit' => 'wholesale',
            'status' => 'active',
            'name' => 'ООО Существующий клиент',
            'source' => 'manual',
        ]);
        $contact = CrmContact::query()->create([
            'counterparty_id' => $counterparty->id,
            'full_name' => 'Иван Петров',
            'is_primary' => true,
            'is_active' => true,
        ]);
        CrmContactPoint::query()->create([
            'counterparty_id' => $counterparty->id,
            'contact_id' => $contact->id,
            'type' => 'email',
            'value' => 'Client@Example.com',
            'is_primary' => true,
            'is_active' => true,
        ]);
        $document = CommercialDocument::query()->create([
            'type' => 'quote',
            'client_company' => 'Другое написание компании',
            'client_name' => 'Иван Петров',
            'client_email' => 'client@example.com',
        ]);

        app(CommercialDocumentClientResolver::class)->sync($document);

        $this->assertSame($counterparty->id, $document->fresh()->crm_counterparty_id);
        $this->assertSame($contact->id, $document->fresh()->crm_contact_id);
        $this->assertSame(1, CrmCounterparty::query()->count());
    }

    public function test_it_creates_a_counterparty_contact_and_normalized_contact_points_for_a_new_client(): void
    {
        $manager = User::factory()->create();
        $document = CommercialDocument::query()->create([
            'type' => 'quote',
            'manager_id' => $manager->id,
            'client_company' => 'ООО Новый заказчик',
            'client_name' => 'Анна Сидорова',
            'client_email' => 'ANNA@EXAMPLE.COM',
            'client_phone' => '+375 (29) 123-45-67',
            'client_unp' => '193000001',
            'client_address' => 'г. Минск, ул. Тестовая, 1',
        ]);

        $counterparty = app(CommercialDocumentClientResolver::class)->sync($document);
        $document->refresh();

        $this->assertNotNull($counterparty);
        $this->assertSame($counterparty->id, $document->crm_counterparty_id);
        $this->assertSame('ООО Новый заказчик', $counterparty->name);
        $this->assertSame('193000001', $counterparty->unp);
        $this->assertSame($manager->id, $counterparty->responsible_user_id);
        $this->assertDatabaseHas('crm_contacts', [
            'id' => $document->crm_contact_id,
            'counterparty_id' => $counterparty->id,
            'full_name' => 'Анна Сидорова',
        ]);
        $this->assertDatabaseHas('crm_contact_points', [
            'counterparty_id' => $counterparty->id,
            'type' => 'email',
            'normalized_value' => 'anna@example.com',
        ]);
        $this->assertDatabaseHas('crm_contact_points', [
            'counterparty_id' => $counterparty->id,
            'type' => 'phone',
            'normalized_value' => '375291234567',
        ]);
    }

    public function test_it_finds_an_existing_counterparty_by_normalized_phone(): void
    {
        $counterparty = CrmCounterparty::query()->create([
            'type' => 'organization',
            'classification' => 'b2b_partner',
            'business_unit' => 'wholesale',
            'status' => 'active',
            'name' => 'Телефонный клиент',
            'source' => 'telephony',
        ]);
        CrmContactPoint::query()->create([
            'counterparty_id' => $counterparty->id,
            'type' => 'phone',
            'value' => '8 (029) 555-12-34',
            'is_primary' => true,
            'is_active' => true,
        ]);
        $document = CommercialDocument::query()->create([
            'type' => 'quote',
            'client_company' => 'Клиент из нового КП',
            'client_phone' => '+375 29 555-12-34',
        ]);

        app(CommercialDocumentClientResolver::class)->sync($document);

        $this->assertSame($counterparty->id, $document->fresh()->crm_counterparty_id);
        $this->assertSame(1, CrmCounterparty::query()->count());
    }

    public function test_selected_crm_contact_is_authoritative_over_stale_document_details(): void
    {
        $counterparty = CrmCounterparty::query()->create([
            'type' => 'organization',
            'classification' => 'b2b_partner',
            'business_unit' => 'wholesale',
            'status' => 'active',
            'name' => 'Мозырский лесозавод',
            'public_name' => 'Мозырский лесозавод',
            'legal_name' => 'ООО «Мозырский лесозавод»',
            'unp' => '400000001',
            'legal_address' => 'г. Мозырь, ул. Лесная, 1',
            'source' => 'manual',
        ]);
        $contact = CrmContact::query()->create([
            'counterparty_id' => $counterparty->id,
            'full_name' => 'Павел Иванов',
            'is_primary' => true,
            'is_active' => true,
        ]);
        CrmContactPoint::query()->create([
            'counterparty_id' => $counterparty->id,
            'contact_id' => $contact->id,
            'type' => 'email',
            'value' => 'new@example.com',
            'is_primary' => true,
            'is_active' => true,
        ]);
        $document = CommercialDocument::query()->create([
            'type' => 'quote',
            'crm_counterparty_id' => $counterparty->id,
            'crm_contact_id' => $contact->id,
            'client_company' => 'Pavel',
            'client_name' => 'Pavel',
            'client_email' => 'old@example.com',
            'client_phone' => '+375 29 000-00-00',
        ]);

        app(CommercialDocumentClientResolver::class)->sync($document);

        $document->refresh();
        $this->assertSame('Павел Иванов', $contact->fresh()->full_name);
        $this->assertSame('Павел Иванов', $document->client_name);
        $this->assertSame('Мозырский лесозавод', $document->client_company);
        $this->assertSame('ООО «Мозырский лесозавод»', $document->client_legal_name);
        $this->assertSame('new@example.com', $document->client_email);
        $this->assertNull($document->client_phone);
        $this->assertSame('400000001', $document->client_unp);
        $this->assertSame('г. Мозырь, ул. Лесная, 1', $document->client_address);
        $this->assertDatabaseMissing('crm_contact_points', [
            'counterparty_id' => $counterparty->id,
            'normalized_value' => 'old@example.com',
        ]);
    }
}
