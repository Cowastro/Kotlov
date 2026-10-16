<?php

namespace Tests\Feature;

use App\Models\CommercialDocument;
use App\Models\CrmMailbox;
use App\Models\CrmMailThread;
use App\Models\User;
use App\Services\CommercialDocuments\CommercialDocumentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialDocumentRevisionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_sent_offer_is_copied_to_numbered_revisions_without_changing_original(): void
    {
        $manager = User::factory()->create();
        $original = CommercialDocument::query()->create([
            'type' => 'quote',
            'status' => 'sent',
            'client_company' => 'ООО «Редакции»',
            'client_email' => 'revision@example.com',
            'payment_terms' => '100% предоплата',
            'delivery_terms' => '5 рабочих дней',
        ]);
        $original->items()->create([
            'name' => 'Насос',
            'sku' => 'PUMP-1',
            'quantity' => 2,
            'unit_price' => 500,
            'discount_percent' => 5,
            'vat_rate' => 20,
        ]);

        $second = app(CommercialDocumentWorkflow::class)->createRevision($original, $manager);
        $third = app(CommercialDocumentWorkflow::class)->createRevision($second, $manager);

        $this->assertSame('sent', $original->fresh()->status);
        $this->assertSame(1, $original->fresh()->revision_number);
        $this->assertSame('draft', $second->status);
        $this->assertSame($original->id, $second->revision_of_id);
        $this->assertSame(2, $second->revision_number);
        $this->assertSame($original->id, $third->revision_of_id);
        $this->assertSame(3, $third->revision_number);
        $this->assertSame('Насос', $third->items->first()->name);
        $this->assertSame('950.00', $third->total);
        $this->assertSame($original->number, $second->number);
        $this->assertSame($original->number, $third->number);
        $this->assertSame($original->number.', редакция №2', $second->reference());
    }

    public function test_acceptance_and_rejection_update_the_mail_sales_stage(): void
    {
        $thread = $this->thread();
        $accepted = CommercialDocument::query()->create([
            'type' => 'quote',
            'status' => 'sent',
            'crm_mail_thread_id' => $thread->id,
        ]);

        app(CommercialDocumentWorkflow::class)->accept($accepted);

        $this->assertSame('accepted', $accepted->fresh()->status);
        $this->assertNotNull($accepted->fresh()->accepted_at);
        $this->assertSame('won', $thread->fresh()->sales_stage);
        $this->assertSame('closed', $thread->fresh()->status);

        $thread = $this->thread();
        $rejected = CommercialDocument::query()->create([
            'type' => 'quote',
            'status' => 'sent',
            'crm_mail_thread_id' => $thread->id,
        ]);

        app(CommercialDocumentWorkflow::class)->reject($rejected, 'price', 'Бюджет ниже предложения');

        $rejected->refresh();
        $this->assertSame('rejected', $rejected->status);
        $this->assertSame('price', $rejected->outcome_reason);
        $this->assertSame('Бюджет ниже предложения', $rejected->outcome_note);
        $this->assertNotNull($rejected->closed_at);
        $this->assertSame('lost', $thread->fresh()->sales_stage);
        $this->assertSame('closed', $thread->fresh()->status);
    }

    public function test_only_an_invoice_can_be_marked_paid(): void
    {
        $invoice = CommercialDocument::query()->create(['type' => 'invoice', 'status' => 'sent']);

        app(CommercialDocumentWorkflow::class)->markPaid($invoice);

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->closed_at);
    }

    public function test_manager_can_record_a_requested_correction_without_overwriting_the_sent_quote(): void
    {
        $thread = $this->thread();
        $quote = CommercialDocument::query()->create([
            'type' => 'quote',
            'status' => 'sent',
            'crm_mail_thread_id' => $thread->id,
        ]);

        app(CommercialDocumentWorkflow::class)->requestRevision($quote, 'Заменить модель и пересчитать доставку');

        $quote->refresh();
        $this->assertSame('needs_revision', $quote->status);
        $this->assertSame('Заменить модель и пересчитать доставку', $quote->outcome_note);
        $this->assertTrue($quote->needsManagerControl());
        $this->assertSame('Создать новую редакцию', $quote->controlLabel());
        $this->assertSame('negotiation', $thread->fresh()->sales_stage);
        $this->assertSame('waiting_manager', $thread->fresh()->status);
        $this->assertNotNull($thread->fresh()->next_follow_up_at);
    }

    private function thread(): CrmMailThread
    {
        $mailbox = CrmMailbox::query()->create([
            'name' => 'KOTLOV',
            'email' => fake()->unique()->safeEmail(),
            'username' => fake()->unique()->safeEmail(),
        ]);

        return CrmMailThread::query()->create([
            'mailbox_id' => $mailbox->id,
            'thread_key' => hash('sha256', fake()->uuid()),
            'participant_email' => fake()->safeEmail(),
            'status' => 'waiting_customer',
            'sales_stage' => 'proposal_sent',
            'next_follow_up_at' => now()->addDay(),
        ]);
    }
}
