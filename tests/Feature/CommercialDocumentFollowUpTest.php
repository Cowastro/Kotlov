<?php

namespace Tests\Feature;

use App\Filament\Resources\CommercialDocuments\Pages\ListCommercialDocuments;
use App\Filament\Resources\CommercialDocuments\Pages\ViewCommercialDocument;
use App\Models\CommercialDocument;
use App\Models\User;
use App\Services\CommercialDocuments\CommercialDocumentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
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
            ->expectsOutput('Просроченных КП поставлено на контроль: 1')
            ->assertSuccessful();

        $expired->refresh();
        $this->assertSame('expired', $expired->status);
        $this->assertSame('contact_planned', $expired->follow_up_stage);
        $this->assertNull($expired->outcome_reason);
        $this->assertNull($expired->closed_at);
        $this->assertNotNull($expired->next_follow_up_at);
        $this->assertTrue($expired->needsManagerControl());
        $this->assertSame('sent', $active->fresh()->status);
    }

    public function test_no_answer_keeps_quote_active_and_requires_next_contact(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $quote = CommercialDocument::query()->create(['type' => 'quote', 'status' => 'sent']);
        $nextContact = now()->addDay()->setTime(10, 0);

        app(CommercialDocumentWorkflow::class)->recordContact(
            $quote,
            $manager,
            'contact_planned',
            'no_answer',
            'Не дозвонились, повторить звонок завтра утром.',
            $nextContact,
        );

        $quote->refresh();
        $this->assertSame('sent', $quote->status);
        $this->assertSame('contact_planned', $quote->follow_up_stage);
        $this->assertSame('no_answer', $quote->last_contact_result);
        $this->assertNull($quote->outcome_reason);
        $this->assertNull($quote->closed_at);
        $this->assertSame($nextContact->timestamp, $quote->next_follow_up_at?->timestamp);
    }

    public function test_no_response_cannot_be_used_as_a_client_rejection_reason(): void
    {
        $quote = CommercialDocument::query()->create(['type' => 'quote', 'status' => 'sent']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('допустимую причину отказа');

        app(CommercialDocumentWorkflow::class)->reject($quote, 'no_response');
    }

    public function test_manager_can_extend_expired_quote_and_schedule_reminder(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $quote = CommercialDocument::query()->create([
            'type' => 'quote',
            'status' => 'expired',
            'follow_up_stage' => 'contact_planned',
            'valid_until' => today()->subDay(),
            'outcome_reason' => 'no_response',
            'closed_at' => now(),
        ]);
        $newDeadline = today()->addDays(10);
        $nextContact = now()->addDay()->setTime(10, 0);

        app(CommercialDocumentWorkflow::class)->extendValidity(
            $quote,
            $manager,
            $newDeadline,
            $nextContact,
            'Клиент попросил продлить предложение на десять дней.',
        );

        $quote->refresh();
        $this->assertSame('sent', $quote->status);
        $this->assertSame($newDeadline->toDateString(), $quote->valid_until?->toDateString());
        $this->assertSame($nextContact->timestamp, $quote->next_follow_up_at?->timestamp);
        $this->assertNull($quote->outcome_reason);
        $this->assertNull($quote->closed_at);
        $this->assertSame('validity_extended', $quote->contact_history[0]['result']);
    }

    public function test_manager_can_correct_wrong_rejection_and_return_quote_to_work(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $quote = CommercialDocument::query()->create([
            'type' => 'quote',
            'status' => 'rejected',
            'follow_up_stage' => 'lost',
            'outcome_reason' => 'no_response',
            'closed_at' => now(),
        ]);
        $nextContact = now()->addDay()->setTime(13, 0);

        app(CommercialDocumentWorkflow::class)->returnToWork(
            $quote,
            $manager,
            'no_answer',
            $nextContact,
            'Отказ был установлен ошибочно, клиенту нужно перезвонить.',
        );

        $quote->refresh();
        $this->assertSame('sent', $quote->status);
        $this->assertSame('contact_planned', $quote->follow_up_stage);
        $this->assertSame('no_answer', $quote->last_contact_result);
        $this->assertNull($quote->outcome_reason);
        $this->assertNull($quote->closed_at);
        $this->assertSame('returned_to_work', $quote->contact_history[0]['result']);
    }

    public function test_migration_reopens_quotes_closed_automatically_when_validity_expired(): void
    {
        $quote = CommercialDocument::query()->create([
            'type' => 'quote',
            'status' => 'rejected',
            'follow_up_stage' => 'lost',
            'last_contact_result' => 'rejected',
            'valid_until' => today()->subDays(2),
            'outcome_reason' => 'no_response',
            'outcome_note' => 'Срок действия предложения истёк.',
            'closed_at' => now(),
            'contact_history' => [
                [
                    'result' => 'no_answer',
                    'stage' => 'contact_planned',
                    'note' => 'Не дозвонились.',
                ],
                [
                    'result' => 'rejected',
                    'stage' => 'lost',
                    'note' => 'Срок действия коммерческого предложения истёк.',
                ],
            ],
        ]);

        $migration = require dirname(__DIR__, 2).'/database/migrations/2026_11_03_280000_reopen_automatically_expired_commercial_quotes.php';
        $migration->up();

        $quote->refresh();
        $this->assertSame('expired', $quote->status);
        $this->assertSame('contact_planned', $quote->follow_up_stage);
        $this->assertSame('no_answer', $quote->last_contact_result);
        $this->assertNull($quote->outcome_reason);
        $this->assertNull($quote->closed_at);
        $this->assertNotNull($quote->next_follow_up_at);
        $this->assertCount(1, $quote->contact_history);
        $this->assertSame('no_answer', $quote->contact_history[0]['result']);
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

    public function test_stale_second_manager_cannot_record_the_same_terminal_result_twice(): void
    {
        $firstManager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $secondManager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $quote = CommercialDocument::query()->create(['type' => 'quote', 'status' => 'sent']);
        $staleCopy = CommercialDocument::query()->findOrFail($quote->id);

        app(CommercialDocumentWorkflow::class)->recordContact(
            $quote,
            $firstManager,
            'lost',
            'rejected',
            'Выбран другой поставщик',
            null,
            'competitor',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('уже зафиксирован другим менеджером');

        app(CommercialDocumentWorkflow::class)->recordContact(
            $staleCopy,
            $secondManager,
            'lost',
            'rejected',
            'Выбран другой поставщик',
            null,
            'competitor',
        );
    }

    public function test_contact_note_must_contain_meaningful_text(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $quote = CommercialDocument::query()->create(['type' => 'quote', 'status' => 'sent']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('осмысленный результат');

        app(CommercialDocumentWorkflow::class)->recordContact(
            $quote,
            $manager,
            'lost',
            'rejected',
            ' , ',
            null,
            'competitor',
        );
    }

    public function test_stale_second_manager_cannot_accept_the_same_quote_twice(): void
    {
        $firstManager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $secondManager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $quote = CommercialDocument::query()->create(['type' => 'quote', 'status' => 'sent']);
        $staleCopy = CommercialDocument::query()->findOrFail($quote->id);

        app(CommercialDocumentWorkflow::class)->recordContact(
            $quote,
            $firstManager,
            'won',
            'accepted',
            'Клиент подтвердил заказ',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('уже зафиксирован другим менеджером');

        app(CommercialDocumentWorkflow::class)->recordContact(
            $staleCopy,
            $secondManager,
            'won',
            'accepted',
            'Клиент подтвердил заказ',
        );
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
            ->assertTableActionVisible('record_contact', $quote);

        Livewire::actingAs($manager)
            ->test(ViewCommercialDocument::class, ['record' => $quote->getRouteKey()])
            ->assertSuccessful()
            ->assertActionVisible('record_contact');
    }
}
