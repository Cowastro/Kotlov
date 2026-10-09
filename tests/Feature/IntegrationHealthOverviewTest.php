<?php

namespace Tests\Feature;

use App\Filament\Widgets\IntegrationHealthOverview;
use App\Models\IntegrationExchangeRun;
use App\Models\IntegrationSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IntegrationHealthOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_names_sources_that_require_attention(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        IntegrationSource::query()->update(['is_active' => false]);
        $healthy = IntegrationSource::query()->create([
            'code' => 'healthy-widget-source',
            'name' => 'Исправный источник',
            'is_active' => true,
        ]);
        $stale = IntegrationSource::query()->create([
            'code' => 'stale-widget-source',
            'name' => 'Просроченный источник',
            'is_active' => true,
            'settings' => ['stale_after_minutes' => 15],
        ]);

        foreach ([
            [$healthy, now()->subMinute()],
            [$stale, now()->subMinutes(30)],
        ] as [$source, $finishedAt]) {
            IntegrationExchangeRun::query()->create([
                'integration_source_id' => $source->id,
                'direction' => 'inbound',
                'operation' => 'catalog',
                'status' => 'success',
                'started_at' => $finishedAt->copy()->subMinute(),
                'finished_at' => $finishedAt,
            ]);
        }

        $this->actingAs($admin);

        Livewire::test(IntegrationHealthOverview::class)
            ->assertSeeText('Обмен интеграций')
            ->assertSeeText('Задержка')
            ->assertSeeText('Требуют внимания: Просроченный источник')
            ->assertSeeText('Работают: 1 · требуют внимания: 1');
    }
}
