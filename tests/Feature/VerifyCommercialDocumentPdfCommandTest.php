<?php

namespace Tests\Feature;

use App\Console\Commands\VerifyCommercialDocumentPdfCommand;
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

    public function test_failure_codes_expose_only_safe_technical_categories(): void
    {
        $this->assertSame(
            'missing_class:Dompdf.Dompdf',
            VerifyCommercialDocumentPdfCommand::safeFailureCode(new \Error('Class "Dompdf\\Dompdf" not found')),
        );
        $this->assertSame(
            'missing_function:Dompdf.mb_internal_encoding',
            VerifyCommercialDocumentPdfCommand::safeFailureCode(new \Error('Call to undefined function Dompdf\\mb_internal_encoding()')),
        );
    }
}
