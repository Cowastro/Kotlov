<?php

namespace Tests\Feature;

use App\Filament\Resources\CommercialDocuments\Pages\ListCommercialDocuments;
use App\Filament\Resources\CommercialDocuments\Pages\ViewCommercialDocument;
use App\Models\CommercialDocument;
use App\Models\User;
use App\Services\CommercialDocuments\CommercialDocumentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CommercialDocumentFollowUpTest extends TestCase
{
    use RefreshDatabase;

    public function test_sent_quotes_near_deadline_are_marked_for_manager_control(): void
    {
        $quote = CommercialDocument::query()->create([
            'type' => 'quote',
            'status' => 'sent',
            'valid_until' => today()->addDays(2),
        ]);

        $this->assertTrue($quote->needsManagerControl());
        $this->assertSame('Связаться до '.today()->addDays(2)->format('d.m'), $quote->controlLabel());
    }

    public function test_command_expires_only_sent_quotes_past_their_deadline(): void
    {
        $expired = CommercialDocument::query()->create([
            'type' => 'quote',
            'status' => 'sent',
            'valid_until' => today()->subDay(),
        ]);
        $active = CommercialDocument::query()->create([
            'type' => 'quote',
            'status' => 'sent',
            'valid_until' => today()->addDay(),
        ]);

        $this->artisan('commercial-documents:expire-quotes')
            ->expectsOutput('Просроченных КП закрыто: 1')
            ->assertSuccessful();

        $this->assertSame('expired', $expired->fresh()->status);
        $this->assertSame('no_response', $expired->fresh()->outcome_reason);
        $this->assertNotNull($expired->fresh()->closed_at);
        $this->assertSame('sent', $active->fresh()->status);
    }

    public function test_manager_can_record_that_client_is_thinking_and_schedule_a_callback(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $quote = CommercialDocument::query()->create([
            'type' => 'quote',
            'status' => 'sent',
            'manager_id' => $manager->id,
            'valid_until' => today()->addDays(10),
        ]);
        $callbackAt = now()->addDays(2)->setTime(11, 30);

        app(CommercialDocumentWorkflow::class)->recordContact(
            $quote,
            $manager,
            'decision_pending',
            'decision_pending',
            'Клиент согласует бюджет, перезвонить после планёрки.',
            $callbackAt,
        );

        $quote->refresh();
        $this->assertSame('decision_pending', $quote->follow_up_stage);
        $this->assertSame('decision_pending', $quote->last_contact_result);
        $this->assertSame($callbackAt->timestamp, $quote->next_follow_up_at?->timestamp);
        $this->assertSame('Клиент согласует бюджет, перезвонить после планёрки.', $quote->follow_up_note);
        $this->assertCount(1, $quote->contact_history);
        $this->assertStringStartsWith('Перезвонить ', $quote->controlLabel());
        $this->assertFalse($quote->needsManagerControl());

        $quote->forceFill(['next_follow_up_at' => now()->subMinute()])->save();
        $this->assertTrue($quote->fresh()->needsManagerControl());
        $this->assertStringStartsWith('Просрочен контакт ', $quote->fresh()->controlLabel());
    }

    public function test_terminal_contact_results_close_the_follow_up_and_keep_history(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $quote = CommercialDocument::query()->create([
            'type' => 'quote',
            'status' => 'sent',
            'manager_id' => $manager->id,
            'next_follow_up_at' => now()->addDay(),
        ]);

        app(CommercialDocumentWorkflow::class)->recordContact(
            $quote,
            $manager,
            'decision_pending',
            'accepted',
            'Клиент согласовал предложение.',
        );

        $quote->refresh();
        $this->assertSame('accepted', $quote->status);
        $this->assertSame('won', $quote->follow_up_stage);
        $this->assertNull($quote->next_follow_up_at);
        $this->assertSame('accepted', $quote->contact_history[0]['result']);
    }

    public function test_contact_action_is_available_in_the_list_and_quote_card(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $quote = CommercialDocument::query()->create([
            'type' => 'quote',
            'status' => 'sent',
            'manager_id' => $manager->id,
            'client_company' => 'ООО «Тест обзвона»',
            'next_follow_up_at' => now()->subMinute(),
        ]);

        Livewire::actingAs($manager)
            ->test(ListCommercialDocuments::class)
            ->assertSuccessful()
            ->assertSeeText('Результат контакта');

        Livewire::actingAs($manager)
            ->test(ViewCommercialDocument::class, ['record' => $quote->getRouteKey()])
            ->assertSuccessful()
            ->assertSeeText('Результат контакта')
            ->assertSeeText('Стадия работы')
            ->assertSeeText('Следующий контакт');
    }
}
