<?php

namespace Tests\Feature;

use App\Models\CommercialDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialDocumentControlNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_gets_one_daily_notification_for_documents_requiring_action(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $quote = CommercialDocument::query()->create([
            'type' => 'quote',
            'status' => 'sent',
            'manager_id' => $manager->id,
            'valid_until' => today()->addDay(),
            'total' => 1500,
        ]);

        $this->artisan('commercial-documents:notify-control')
            ->expectsOutput('КП с отправленным напоминанием: 1')
            ->assertSuccessful();

        $this->assertSame(1, $manager->notifications()->count());
        $this->assertNotNull($quote->fresh()->last_control_notified_at);
        $this->assertStringContainsString($quote->number, $manager->notifications()->firstOrFail()->data['body']);

        $this->artisan('commercial-documents:notify-control')
            ->expectsOutput('КП с отправленным напоминанием: 0')
            ->assertSuccessful();

        $this->assertSame(1, $manager->notifications()->count());
    }

    public function test_unassigned_quote_is_notified_to_active_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        CommercialDocument::query()->create([
            'type' => 'quote',
            'status' => 'accepted',
            'manager_id' => null,
            'total' => 3000,
        ]);

        $this->artisan('commercial-documents:notify-control')->assertSuccessful();

        $this->assertSame(1, $admin->notifications()->count());
    }

    public function test_requested_revision_is_included_in_the_manager_control_queue(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $quote = CommercialDocument::query()->create([
            'type' => 'quote',
            'status' => 'needs_revision',
            'manager_id' => $manager->id,
            'total' => 1800,
        ]);

        $this->artisan('commercial-documents:notify-control')
            ->expectsOutput('КП с отправленным напоминанием: 1')
            ->assertSuccessful();

        $notification = $manager->notifications()->firstOrFail();
        $this->assertStringContainsString($quote->number, $notification->data['body']);
        $this->assertStringContainsString('Создать новую редакцию', $notification->data['body']);
    }
}
