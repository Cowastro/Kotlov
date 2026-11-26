<?php

namespace Tests\Feature;

use App\Models\SupplierSync;
use App\Services\Integrations\OneCBridge\OneCReadWindow;
use App\Services\Shipping\VamExpressSettings;
use App\Services\Suppliers\EurotermPortalCredentials;
use App\Services\Suppliers\TdKomplektFeed;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SchedulerIntegrationFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_integration_filters_resolve_their_classes_before_the_scheduler_reaches_onec(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put(TdKomplektFeed::DIRECTORY.'/config.json', '{}');
        SupplierSync::updateOrCreate(['key' => 'td_komplekt_csv'], [
            'name' => 'Test', 'code' => 'test', 'title' => 'Test',
            'command' => 'supplier:sync-td-komplekt', 'is_active' => true,
        ]);
        $this->mock(VamExpressSettings::class)->shouldReceive('due')->once()->andReturn(true);
        $this->mock(EurotermPortalCredentials::class)->shouldReceive('due')->once()->andReturn(true);

        $events = collect(app(Schedule::class)->events());
        foreach (['shipping:sync-vamexpress', 'supplier:sync-euroterm', 'supplier:sync-td-komplekt'] as $command) {
            $event = $events->first(fn ($event) => str_contains($event->command ?? '', $command));
            $this->assertNotNull($event, $command);
            // schedule:list never evaluates these callbacks. schedule:run does,
            // before its per-command exception handler, so a bad alias stops all later jobs.
            $this->assertTrue($event->filtersPass($this->app), $command);
        }

        foreach (['onec:sync-stock', 'onec:sync-prices', 'onec:check-documents'] as $command) {
            $this->assertNotNull($events->first(fn ($event) => str_contains($event->command ?? '', $command)));
        }
    }

    public function test_disabled_integrations_can_be_skipped_without_breaking_later_jobs(): void
    {
        Storage::fake('local');
        $this->mock(VamExpressSettings::class)->shouldReceive('due')->once()->andReturn(false);
        $this->mock(EurotermPortalCredentials::class)->shouldReceive('due')->once()->andReturn(false);

        foreach (app(Schedule::class)->events() as $event) {
            if (preg_match('/(?:shipping:sync-vamexpress|supplier:sync-euroterm|supplier:sync-td-komplekt)/', $event->command ?? '')) {
                $this->assertFalse($event->filtersPass($this->app));
            }
        }
    }

    public function test_all_automatic_onec_reads_obey_daily_minsk_hours_including_weekends(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command ?? '', ' onec:'));
        $this->assertCount(7, $events);

        // Sunday: no weekday restriction. The UTC server clock must not shift the window.
        foreach (['2026-11-29 05:59:00', '2026-11-29 15:00:00', '2026-11-29 20:00:00'] as $utc) {
            $this->travelTo(Carbon::parse($utc, 'UTC'));
            $this->assertFalse(OneCReadWindow::isOpen());
            foreach ($events as $event) {
                $this->assertFalse($event->filtersPass($this->app), $event->command);
            }
        }

        foreach (['06:00', '06:05', '06:10', '06:15', '06:20', '14:30', '14:45'] as $time) {
            $this->travelTo(Carbon::parse('2026-11-29 '.$time.':00', 'UTC'));
            $this->assertTrue(OneCReadWindow::isOpen());
            $this->assertNotEmpty($events->filter(fn ($event) => $event->isDue($this->app)
                && $event->filtersPass($this->app)), $time);
        }
    }
}
