<?php

namespace Tests\Feature;

use App\Filament\Resources\CommercialDocuments\Pages\ListCommercialDocuments;
use App\Filament\Widgets\CommercialDocumentsOverview;
use App\Filament\Widgets\CommercialManagerPerformance;
use App\Models\CommercialDocument;
use App\Models\User;
use App\Services\CommercialDocuments\CommercialDocumentAnalytics;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class CommercialDocumentAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_counts_pipeline_conversion_invoices_and_loss_reasons(): void
    {
        Carbon::setTestNow('2026-10-14 12:00:00');
        CommercialDocument::query()->create([
            'type' => 'quote', 'status' => 'sent', 'total' => 5000,
            'valid_until' => today()->addDay(),
        ]);
        CommercialDocument::query()->create([
            'type' => 'quote', 'status' => 'needs_revision', 'total' => 1200,
        ]);
        CommercialDocument::query()->create([
            'type' => 'quote', 'status' => 'accepted', 'total' => 3000,
            'accepted_at' => now()->subDays(2),
        ]);
        foreach ([1, 2] as $day) {
            CommercialDocument::query()->create([
                'type' => 'quote', 'status' => 'rejected', 'total' => 1000,
                'outcome_reason' => 'price', 'closed_at' => now()->subDays($day),
            ]);
        }
        CommercialDocument::query()->create([
            'type' => 'invoice', 'status' => 'sent', 'total' => 2500,
        ]);

        $data = app(CommercialDocumentAnalytics::class)->summary();

        $this->assertSame(2, $data['active_count']);
        $this->assertSame(6200.0, $data['active_total']);
        $this->assertSame(3, $data['needs_action_count']);
        $this->assertSame(33.3, round((float) $data['conversion_percent'], 1));
        $this->assertSame(1, $data['won_count']);
        $this->assertSame(2, $data['lost_count']);
        $this->assertSame(1, $data['unpaid_invoice_count']);
        $this->assertSame(2500.0, $data['unpaid_invoice_total']);
        $this->assertSame('price', $data['top_loss_reason']);
        $this->assertSame(2, $data['top_loss_reason_count']);
    }

    public function test_manager_sees_commercial_analytics_and_work_tabs(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);

        $statsMethod = new ReflectionMethod(CommercialDocumentsOverview::class, 'getStats');
        $this->assertCount(4, $statsMethod->invoke(app(CommercialDocumentsOverview::class)));

        Livewire::actingAs($manager)
            ->test(CommercialDocumentsOverview::class)
            ->assertSuccessful()
            ->assertSee('Коммерческая работа');

        Livewire::actingAs($manager)
            ->test(ListCommercialDocuments::class)
            ->assertSuccessful()
            ->assertSee('Требует действия')
            ->assertSee('Выиграно')
            ->assertSee('Потеряно');
    }

    public function test_manager_performance_widget_shows_conversion_and_won_total(): void
    {
        Carbon::setTestNow('2026-10-14 12:00:00');
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true, 'name' => 'Мария Продажи']);
        CommercialDocument::query()->create([
            'type' => 'quote', 'status' => 'accepted', 'manager_id' => $manager->id,
            'accepted_at' => now(), 'total' => 4000,
        ]);
        CommercialDocument::query()->create([
            'type' => 'quote', 'status' => 'rejected', 'manager_id' => $manager->id,
            'closed_at' => now(), 'outcome_reason' => 'price', 'total' => 2000,
        ]);

        Livewire::actingAs($manager)
            ->test(CommercialManagerPerformance::class)
            ->assertSuccessful()
            ->assertSee('Мария Продажи')
            ->assertSee('50,0%')
            ->assertSee('BYN');
    }
}
