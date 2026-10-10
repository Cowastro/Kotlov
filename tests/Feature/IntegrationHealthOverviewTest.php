<?php

namespace Tests\Feature;

use App\Filament\Resources\IntegrationExchangeRuns\IntegrationExchangeRunResource;
use App\Filament\Widgets\IntegrationHealthOverview;
use App\Filament\Widgets\RecentIntegrationRuns;
use App\Models\IntegrationExchangeRun;
use App\Models\IntegrationMonitorHeartbeat;
use App\Models\IntegrationProduct;
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

        IntegrationMonitorHeartbeat::query()->create([
            'name' => IntegrationMonitorHeartbeat::ISSUE_SCANNER,
            'status' => 'success',
            'started_at' => now()->subSeconds(10),
            'finished_at' => now()->subSeconds(5),
            'summary' => ['detected' => 1],
        ]);

        $this->actingAs($admin);

        Livewire::test(IntegrationHealthOverview::class)
            ->assertSeeText('Обмен интеграций')
            ->assertSeeText('Задержка')
            ->assertSeeText('Требуют внимания: Просроченный источник')
            ->assertSeeText('Работают: 1 · требуют внимания: 1')
            ->assertSeeText('Монитор очереди')
            ->assertSeeText('Очередь проверяется каждую минуту');
    }

    public function test_widget_distinguishes_legacy_staging_from_an_empty_integration(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        IntegrationSource::query()->update(['is_active' => false]);
        $source = IntegrationSource::query()->create([
            'code' => 'legacy-staging-widget-source',
            'name' => 'Историческая 1С',
            'is_active' => true,
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'legacy-widget-product',
            'name' => 'Ранее полученный товар',
            'last_seen_at' => now()->subHour(),
        ]);

        $this->actingAs($admin);

        Livewire::test(IntegrationHealthOverview::class)
            ->assertSeeText('Ожидает контрольный цикл')
            ->assertSeeText('Позиций в буфере: 1 · запустите новый обмен для журнала')
            ->assertDontSeeText('Обмен интеграций Нет данных');
    }

    public function test_empty_exchange_journal_explains_existing_staged_catalog(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $source = IntegrationSource::query()->create([
            'code' => 'legacy-journal-source',
            'name' => 'Историческая загрузка',
            'is_active' => true,
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'legacy-journal-product',
            'name' => 'Товар из прежней загрузки',
        ]);

        $this->actingAs($admin);

        Livewire::test(RecentIntegrationRuns::class)
            ->assertSeeText('Контролируемых сеансов ещё нет')
            ->assertSeeText('Позиций в буфере прежней загрузки: 1');

        $this->get(IntegrationExchangeRunResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Контролируемых сеансов ещё нет')
            ->assertSeeText('Позиций в буфере прежней загрузки: 1');
    }

    public function test_recent_exchange_widget_separates_created_and_updated_catalog_rows(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $source = IntegrationSource::query()->create([
            'code' => 'journal-result-source',
            'name' => 'Источник результата',
        ]);
        IntegrationExchangeRun::query()->create([
            'integration_source_id' => $source->id,
            'direction' => 'inbound',
            'operation' => 'catalog',
            'status' => 'success',
            'started_at' => now(),
            'finished_at' => now(),
            'items_received' => 705,
            'items_created' => 5,
            'items_updated' => 698,
            'items_skipped' => 2,
        ]);

        Livewire::actingAs($admin)
            ->test(RecentIntegrationRuns::class)
            ->assertSeeText('Товары: получено')
            ->assertSeeText('705')
            ->assertSeeText('новых 5 · обновлено 698 · пропущено 2');
    }
}
