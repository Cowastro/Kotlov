<?php

namespace Tests\Feature;

use App\Filament\Resources\CommercialDocuments\Pages\ViewCommercialDocument;
use App\Models\CommercialDocument;
use App\Models\User;
use App\Services\CommercialDocuments\CommercialDocumentRevisionComparison;
use App\Services\CommercialDocuments\CommercialDocumentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CommercialDocumentRevisionComparisonTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_compares_changed_added_and_removed_items_with_previous_revision(): void
    {
        [$original, $revision] = $this->changedRevision();

        $comparison = app(CommercialDocumentRevisionComparison::class)->compare($revision);

        $this->assertSame($original->id, $comparison['previous']->id);
        $this->assertSame(3, count($comparison['changes']));
        $this->assertSame(['added', 'changed', 'removed'], collect($comparison['changes'])->pluck('type')->sort()->values()->all());

        $changed = collect($comparison['changes'])->firstWhere('type', 'changed');
        $this->assertSame('Насос', $changed['name']);
        $this->assertContains('quantity', $changed['changed_fields']);
        $this->assertContains('unit_price', $changed['changed_fields']);
        $this->assertContains('discount_percent', $changed['changed_fields']);
        $this->assertSame(850.0, $comparison['total_delta']);
    }

    public function test_revision_changes_are_visible_on_document_page(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        [, $revision] = $this->changedRevision($manager);

        Livewire::actingAs($manager)
            ->test(ViewCommercialDocument::class, ['record' => $revision->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('История и изменения редакций')
            ->assertSee('Редакция №1')
            ->assertSee('Редакция №2')
            ->assertSee('Добавлено')
            ->assertSee('Удалено')
            ->assertSee('Изменено')
            ->assertSee('Причина корректировки:')
            ->assertSee('Заменить радиатор и увеличить количество насоса');
    }

    public function test_first_revision_links_to_later_versions_without_comparing_itself(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        [$original, $revision] = $this->changedRevision($manager);

        Livewire::actingAs($manager)
            ->test(ViewCommercialDocument::class, ['record' => $original->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('История и изменения редакций')
            ->assertSee($revision->number)
            ->assertSee('Это первая редакция')
            ->assertDontSee('Было / стало');
    }

    /** @return array{CommercialDocument, CommercialDocument} */
    private function changedRevision(?User $manager = null): array
    {
        $manager ??= User::factory()->create();
        $original = CommercialDocument::query()->create([
            'type' => 'quote',
            'status' => 'needs_revision',
            'revision_number' => 1,
            'outcome_note' => 'Заменить радиатор и увеличить количество насоса',
        ]);
        $original->items()->create([
            'name' => 'Насос',
            'sku' => 'PUMP-1',
            'unit' => 'шт.',
            'quantity' => 1,
            'unit_price' => 500,
            'discount_percent' => 0,
        ]);
        $original->items()->create([
            'name' => 'Радиатор',
            'sku' => 'RAD-1',
            'unit' => 'шт.',
            'quantity' => 1,
            'unit_price' => 300,
            'discount_percent' => 0,
        ]);

        $revision = app(CommercialDocumentWorkflow::class)->createRevision($original->fresh(), $manager);
        $pump = $revision->items()->where('sku', 'PUMP-1')->firstOrFail();
        $pump->update(['quantity' => 2, 'unit_price' => 600, 'discount_percent' => 10]);
        $revision->items()->where('sku', 'RAD-1')->firstOrFail()->delete();
        $revision->items()->create([
            'name' => 'Бойлер',
            'sku' => 'BOILER-1',
            'unit' => 'шт.',
            'quantity' => 1,
            'unit_price' => 570,
            'discount_percent' => 0,
        ]);

        return [$original->fresh('items'), $revision->fresh('items')];
    }
}
