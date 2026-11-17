<?php

namespace Tests\Feature;

use App\Models\CommercialDocument;
use App\Services\CommercialDocuments\CommercialDocumentReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialDocumentFreeLineTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_line_typed_by_hand_is_allowed_with_a_warning(): void
    {
        $quote = CommercialDocument::query()->create([
            'type' => 'quote', 'status' => 'draft', 'client_name' => 'Клиент',
            'payment_terms' => '100% предоплата', 'delivery_terms' => 'самовывоз',
        ]);
        $quote->items()->create(['name' => 'Дюбель 6×40, упаковка 100 шт.', 'unit' => 'уп.', 'quantity' => 3, 'unit_price' => 4.5, 'vat_rate' => 20]);

        $result = app(CommercialDocumentReadiness::class)->assessment($quote->fresh());

        // Only client-data issues may remain; the hand-typed line itself does not block the quote.
        $this->assertEmpty(array_filter($result['issues'], fn ($issue) => str_contains($issue, 'Дюбель')));
        $this->assertContains('позиция вне каталога, закупка не проверена: Дюбель 6×40, упаковка 100 шт.', $result['warnings']);
        $this->assertEquals(13.5, (float) $quote->fresh()->items()->first()->line_total);
    }
}
