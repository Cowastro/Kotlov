<?php

namespace Tests\Feature;

use App\Models\CommercialDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialDocumentPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_history_hides_a_repeated_terminal_result(): void
    {
        $quote = CommercialDocument::query()->create([
            'type' => 'quote',
            'status' => 'rejected',
            'contact_history' => [
                [
                    'contacted_at' => '2026-10-19T11:48:00+03:00',
                    'user_name' => 'Первый менеджер',
                    'stage' => 'lost',
                    'result' => 'rejected',
                    'note' => 'Выбран другой поставщик.',
                ],
                [
                    'contacted_at' => '2026-10-19T11:49:00+03:00',
                    'user_name' => 'Второй менеджер',
                    'stage' => 'lost',
                    'result' => 'rejected',
                    'note' => 'Выбран другой поставщик',
                ],
            ],
        ]);

        $html = view('filament.commercial-documents.contact-history', ['record' => $quote])->render();

        $this->assertSame(1, substr_count($html, 'Клиент отказался'));
        $this->assertStringContainsString('Первый менеджер', $html);
        $this->assertStringNotContainsString('Второй менеджер', $html);
    }

    public function test_items_view_uses_stable_inline_table_layout(): void
    {
        $item = (object) [
            'name' => 'Твердотопливный котёл',
            'sku' => 'PS-011.3671',
            'quantity' => 3,
            'unit' => 'шт.',
            'unit_price' => 1540,
            'line_total' => 4620,
        ];

        $html = view('filament.commercial-documents.items-table', ['items' => collect([$item])])->render();

        $this->assertStringContainsString('border-collapse:collapse', $html);
        $this->assertStringContainsString('text-align:right', $html);
        $this->assertStringContainsString('PS-011.3671', $html);
    }
}
