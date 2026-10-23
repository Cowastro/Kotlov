<?php

namespace Tests\Feature;

use App\Models\CommercialDocument;
use App\Models\CommercialContract;
use App\Models\User;
use App\Services\CommercialContracts\ClientContractTemplateGenerator;
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

    public function test_it_preserves_and_fills_a_client_docx_template(): void
    {
        Storage::fake('local');

        $quote = CommercialDocument::query()->create([
            'type' => 'quote',
            'status' => 'accepted',
            'client_name' => 'Иванов Иван Иванович',
            'client_legal_name' => 'ООО «Покупатель»',
            'client_unp' => '190000000',
            'currency' => 'BYN',
        ]);
        $templatePath = 'commercial-contracts/client-uploads/form.docx';
        Storage::disk('local')->makeDirectory(dirname($templatePath));
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($templatePath), ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
            .'<w:p><w:r><w:t>УНП: {{SUPPLIER_</w:t></w:r><w:r><w:t>UNP}}</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>Договор № {{CONTRACT_NUMBER}}</w:t></w:r></w:p>'
            .'</w:body></w:document>');
        $zip->close();

        $contract = app(ClientContractTemplateGenerator::class)->create($quote, [
            'client_template_path' => $templatePath,
            'client_template_name' => 'Договор клиента.docx',
            'contract_date' => '2026-10-23',
            'auto_replace_supplier_column' => true,
        ]);

        $this->assertSame('client', $contract->template_mode);
        $this->assertNull($contract->generated_pdf_path);
        Storage::disk('local')->assertExists($contract->source_template_path);
        Storage::disk('local')->assertExists($contract->generated_docx_path);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($contract->generated_docx_path)) === true);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertIsString($xml);
        $this->assertStringContainsString('191355768', $xml);
        $this->assertStringContainsString($contract->number, $xml);
        $this->assertStringNotContainsString('{{SUPPLIER_UNP}}', $xml);
    }

    public function test_manager_can_download_generated_docx(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('commercial-contracts/1/contract.docx', 'docx-content');
        Storage::disk('local')->put('commercial-contracts/1/source.doc', 'doc-content');

        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $quote = CommercialDocument::query()->create([
            'type' => 'quote',
            'status' => 'accepted',
            'client_name' => 'Тестовый клиент',
            'currency' => 'BYN',
        ]);
        $contract = CommercialContract::query()->create([
            'commercial_document_id' => $quote->id,
            'created_by' => $manager->id,
            'status' => 'generated',
            'template_mode' => 'standard',
            'template_version' => 1,
            'source_template_path' => 'commercial-contracts/1/source.doc',
            'generated_docx_path' => 'commercial-contracts/1/contract.docx',
        ]);

        $response = $this->actingAs($manager)->get(route('admin.commercial-contracts.docx', $contract));

        $response->assertOk();
        $response->assertDownload('Договор-'.$contract->number.'.docx');
        $this->assertSame('docx-content', $response->streamedContent());

        $sourceResponse = $this->actingAs($manager)->get(route('admin.commercial-contracts.source', $contract));

        $sourceResponse->assertOk();
        $sourceResponse->assertDownload('Исходная-форма-'.$contract->number.'.doc');
        $this->assertSame('doc-content', $sourceResponse->streamedContent());
    }
}
