<?php

namespace Tests\Feature;

use App\Models\CommercialDocument;
use App\Services\CommercialContracts\CommercialContractGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class CommercialContractGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_versioned_docx_and_pdf_from_a_quote(): void
    {
        Storage::fake('local');

        $quote = CommercialDocument::query()->create([
            'type' => 'quote',
            'status' => 'accepted',
            'client_name' => 'Иванов Иван Иванович',
            'client_legal_name' => 'ООО «Покупатель»',
            'client_unp' => '190000000',
            'client_address' => 'г. Минск, ул. Тестовая, 1',
            'client_email' => 'buyer@example.test',
            'currency' => 'BYN',
        ]);
        $quote->items()->create([
            'name' => 'Котёл тестовый',
            'sku' => 'TEST-1',
            'quantity' => 2,
            'unit_price' => 100,
            'line_total' => 200,
        ]);

        $contract = app(CommercialContractGenerator::class)->create($quote, [
            'contract_date' => '2026-10-23',
            'valid_until' => '2026-12-31',
            'buyer_name' => 'ООО «Покупатель»',
            'buyer_unp' => '190000000',
            'buyer_address' => 'г. Минск, ул. Тестовая, 1',
            'buyer_representative' => 'Иванов Иван Иванович',
            'buyer_position' => 'Директор',
            'buyer_authority_basis' => 'Устава',
            'buyer_iban' => 'BY00TEST00000000000000000000',
            'buyer_bank' => 'Тестовый банк',
            'buyer_bic' => 'TESTBY2X',
            'purchase_purpose' => 'own_use',
            'regulated_goods' => false,
            'payment_type' => 'prepayment',
            'prepayment_percent' => 100,
            'payment_days' => 3,
            'delivery_method' => 'pickup',
            'delivery_days' => 10,
            'delivery_day_type' => 'working',
            'delivery_start_event' => 'prepayment',
            'auto_renew' => true,
        ]);

        $this->assertSame($quote->id, $contract->commercial_document_id);
        $this->assertSame('standard', $contract->template_mode);
        $this->assertNotEmpty($contract->content_checksum);
        Storage::disk('local')->assertExists($contract->generated_docx_path);
        Storage::disk('local')->assertExists($contract->generated_pdf_path);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($contract->generated_docx_path)) === true);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertIsString($xml);
        $this->assertStringContainsString('ООО «Покупатель»', $xml);
        $this->assertStringContainsString('Котёл тестовый', $xml);
        $this->assertStringContainsString('100% предварительную оплату', $xml);
    }
}
