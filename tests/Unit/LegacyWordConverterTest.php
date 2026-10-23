<?php

namespace Tests\Unit;

use App\Services\CommercialContracts\LegacyWordConverter;
use ReflectionMethod;
use Tests\TestCase;
use ZipArchive;

class LegacyWordConverterTest extends TestCase
{
    public function test_text_recovery_restores_missing_contract_fields_and_supplier_block_order(): void
    {
        $source = storage_path('app/tmp/legacy-contract-source.doc');
        $output = storage_path('app/tmp/legacy-contract-output.docx');
        @mkdir(dirname($source), 0775, true);

        $text = implode("\r\n", [
            'ДОГОВОР ПОСТАВКИ №',
            '. 2026г г. Минск',
            ', именуемое в дальнейшем «Поставщик», в лице директора',
            ', действующего на основании Устава, с одной стороны и Покупатель заключили договор.',
            '1.1. Товары согласно счёт-фактуре №',
            ', а Покупатель оплачивает товары согласно счёт-фактуре',
            'г., являющейся неотъемлемой частью Договора.',
            '3.3. Сумма по договору составляет:',
            '9. ЮРИДИЧЕСКИЕ РЕКВИЗИТЫ СТОРОН',
            'Покупатель:',
            'ГУ «Покупатель»',
            'Font Style13',
        ]);
        file_put_contents($source, mb_convert_encoding($text, 'UTF-16LE', 'UTF-8'));

        try {
            $converter = app(LegacyWordConverter::class);
            $method = new ReflectionMethod($converter, 'recoverTextAsDocx');
            $method->setAccessible(true);

            $this->assertTrue($method->invoke($converter, $source, $output));

            $zip = new ZipArchive;
            $this->assertTrue($zip->open($output) === true);
            $xml = (string) $zip->getFromName('word/document.xml');
            $zip->close();

            $this->assertStringContainsString('{{CONTRACT_NUMBER}}', $xml);
            $this->assertStringContainsString('{{CONTRACT_DATE}}', $xml);
            $this->assertStringContainsString('{{SUPPLIER_SHORT_NAME}}', $xml);
            $this->assertStringContainsString('{{QUOTE_NUMBER}}', $xml);
            $this->assertStringContainsString('{{QUOTE_TOTAL}}', $xml);
            $this->assertLessThan(strpos($xml, 'Покупатель:'), strpos($xml, 'Поставщик:'));
            $this->assertStringNotContainsString('Font Style13', $xml);
        } finally {
            @unlink($source);
            @unlink($output);
        }
    }
}
