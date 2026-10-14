<?php

namespace Tests\Feature;

use App\Models\CommercialDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
