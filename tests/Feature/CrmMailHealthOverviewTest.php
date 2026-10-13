<?php

namespace Tests\Feature;

use App\Filament\Widgets\CrmMailHealthOverview;
use App\Models\CrmMailbox;
use App\Models\CrmMailMessage;
use App\Models\CrmMailThread;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class CrmMailHealthOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_current_mail_synchronization_state(): void
    {
        Carbon::setTestNow('2026-10-13 14:30:00');

        $mailbox = CrmMailbox::query()->firstOrFail();
        $mailbox->forceFill([
            'is_enabled' => true,
            'last_sync_at' => now()->subMinute(),
            'last_success_at' => now()->subMinute(),
            'last_error' => null,
        ])->save();

        $thread = CrmMailThread::query()->create([
            'mailbox_id' => $mailbox->id,
            'thread_key' => 'health-widget-test',
            'subject' => 'Проверка почты',
            'participant_email' => 'client@example.com',
        ]);

        CrmMailMessage::query()->create([
            'thread_id' => $thread->id,
            'mailbox_id' => $mailbox->id,
            'direction' => 'incoming',
            'from_email' => 'client@example.com',
            'received_at' => now()->subMinutes(2),
        ]);

        $method = new ReflectionMethod(CrmMailHealthOverview::class, 'getStats');
        $stats = $method->invoke(app(CrmMailHealthOverview::class));

        $this->assertCount(4, $stats);
        $this->assertSame('Подключена', $stats[0]->getValue());
        $this->assertSame('13.10.2026 17:29:00', $stats[1]->getValue());
        $this->assertSame('13.10.2026 17:28:00', $stats[2]->getValue());
        $this->assertSame('13.10.2026 17:30:00', $stats[3]->getValue());
    }
}
