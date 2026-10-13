<?php

namespace Tests\Feature;

use App\Models\CommercialDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifyCommercialDocumentPdfCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_renders_a_real_document_without_exposing_its_contents(): void
    {
        $document = CommercialDocument::query()->create([
            'type' => 'quote',
            'client_company' => 'Секретный покупатель',
            'client_email' => 'private@example.com',
        ]);
        $document->items()->create([
            'name' => 'Тестовый котёл',
            'quantity' => 1,
            'unit_price' => 3154,
            'vat_rate' => 20,
        ]);

        $this->artisan('commercial-documents:verify-pdf', ['--id' => $document->id])
            ->expectsOutputToContain('pdf_status=ok')
            ->doesntExpectOutputToContain('Секретный покупатель')
            ->doesntExpectOutputToContain('private@example.com')
            ->assertSuccessful();
    }
}
