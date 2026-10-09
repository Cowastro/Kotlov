<?php

namespace Tests\Feature;

use App\Filament\Resources\IntegrationIssues\IntegrationIssueResource;
use App\Models\IntegrationIssue;
use App\Models\IntegrationSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationIssueAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_queue_shows_contextual_next_action_without_opening_a_modal(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $source = IntegrationSource::query()->create([
            'code' => 'issue-ui-source',
            'name' => 'Тестовая 1С',
        ]);

        IntegrationIssue::query()->create([
            'integration_source_id' => $source->id,
            'fingerprint' => 'issue-ui-stale',
            'type' => 'integration_stale',
            'severity' => 'danger',
            'status' => 'open',
            'title' => 'Нет свежего обмена с Тестовая 1С',
            'message' => 'Последний успешный обмен был слишком давно.',
            'context' => ['stale_after_minutes' => 15],
            'first_detected_at' => now(),
            'last_detected_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(IntegrationIssueResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Следующий шаг')
            ->assertSeeText('Восстановить автоматический обмен')
            ->assertSeeText('Откройте регламентное задание обмена на стороне 1С.')
            ->assertSeeText('Что делать')
            ->assertSeeText('ИИ-разбор')
            ->assertSeeText('Открыть');
    }
}
