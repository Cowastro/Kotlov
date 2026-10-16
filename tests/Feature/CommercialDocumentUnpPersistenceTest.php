<?php

namespace Tests\Feature;

use App\Filament\Resources\CommercialDocuments\Pages\CreateCommercialDocument;
use App\Models\CommercialDocument;
use App\Models\CrmContact;
use App\Models\CrmCounterparty;
use App\Models\User;
use App\Services\CommercialDocuments\CommercialDocumentClientResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CommercialDocumentUnpPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_entered_unp_is_saved_corrected_and_reused_without_changing_old_quotes(): void
    {
        $company = CrmCounterparty::query()->create(['type' => 'organization', 'name' => 'UNP fixture']);
        $contact = CrmContact::query()->create(['counterparty_id' => $company->id, 'full_name' => 'Fixture contact', 'is_active' => true]);
        $old = CommercialDocument::query()->create(['type' => 'quote', 'status' => 'sent', 'crm_counterparty_id' => $company->id, 'client_unp' => '193900001']);
        $oldBefore = $old->fresh()->getAttributes();
        $quote = CommercialDocument::query()->create([
            'type' => 'quote', 'crm_counterparty_id' => $company->id, 'crm_contact_id' => $contact->id,
            'client_unp' => ' 193900002 ',
        ]);
        $resolver = app(CommercialDocumentClientResolver::class);
        $resolver->sync($quote);
        $this->assertSame('193900002', $company->fresh()->unp);
        $this->assertSame('193900002', $quote->fresh()->client_unp);
        $quote->client_unp = '193900003';
        $resolver->sync($quote);
        $this->assertSame('193900003', $company->fresh()->unp);

        // Also covers the no-selected-contact path, which previously did not fill from CRM.
        $next = CommercialDocument::query()->create(['type' => 'quote', 'crm_counterparty_id' => $company->id, 'client_unp' => '']);
        $resolver->sync($next);
        $this->assertSame('193900003', $next->fresh()->client_unp);
        $this->assertSame('193900003', $company->fresh()->unp);
        $this->assertSame($oldBefore, $old->fresh()->getAttributes());
    }

    public function test_create_form_reads_unp_on_hydration_without_reselecting_the_company(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $company = CrmCounterparty::query()->create(['type' => 'organization', 'name' => 'UNP form fixture', 'unp' => '193900004']);
        session()->put('commercial_document_prefill', ['crm_counterparty_id' => $company->id, 'client_unp' => null]);
        Livewire::actingAs($manager)->test(CreateCommercialDocument::class)
            ->assertFormSet(['crm_counterparty_id' => $company->id, 'client_unp' => '193900004']);
    }

    public function test_recovery_only_fills_empty_directory_records_with_one_unambiguous_saved_number(): void
    {
        $migration = require database_path('migrations/2026_10_16_190000_recover_counterparty_unp_from_saved_quotes.php');
        $good = CrmCounterparty::query()->create(['name' => 'One known number']);
        $conflict = CrmCounterparty::query()->create(['name' => 'Conflicting history']);
        $known = CrmCounterparty::query()->create(['name' => 'Known directory number', 'unp' => '193900005']);
        foreach ([[$good, '193900006'], [$good, ' 193900006 '], [$conflict, '193900007'], [$conflict, '193900008'], [$known, '193900009']] as [$company, $unp]) {
            CommercialDocument::query()->create(['type' => 'quote', 'status' => 'sent', 'crm_counterparty_id' => $company->id, 'client_unp' => $unp]);
        }
        $before = CommercialDocument::query()->get()->map->getAttributes()->all();
        $migration->up();
        $migration->up();
        $this->assertSame('193900006', $good->fresh()->unp);
        $this->assertNull($conflict->fresh()->unp);
        $this->assertSame('193900005', $known->fresh()->unp);
        $this->assertSame($before, CommercialDocument::query()->get()->map->getAttributes()->all());
    }

    public function test_updating_unp_cannot_take_another_counterpartys_number(): void
    {
        $company = CrmCounterparty::query()->create(['name' => 'Selected fixture', 'unp' => '193900010']);
        CrmCounterparty::query()->create(['name' => 'Other fixture', 'unp' => '193900011']);
        $quote = CommercialDocument::query()->create(['type' => 'quote', 'crm_counterparty_id' => $company->id, 'client_unp' => '193900011']);
        try {
            app(CommercialDocumentClientResolver::class)->sync($quote);
            $this->fail('Conflicting UNP was accepted');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('client_unp', $exception->errors());
        }
        $this->assertSame('193900010', $company->fresh()->unp);
    }
}
