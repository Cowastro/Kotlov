<?php

namespace App\Services\CommercialContracts;

use App\Models\CommercialContract;
use App\Models\CommercialDocument;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class ClientContractTemplateGenerator
{
    private const WORD_NAMESPACE = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** @param array<string, mixed> $input */
    public function create(CommercialDocument $document, array $input): CommercialContract
    {
        $sourcePath = (string) ($input['client_template_path'] ?? '');
        $disk = Storage::disk('local');

        if ($sourcePath === '' || ! $disk->exists($sourcePath)) {
            throw new RuntimeException('Загруженная форма клиента не найдена. Загрузите DOCX ещё раз.');
        }

        if (strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION)) !== 'docx') {
            throw new RuntimeException('Форма клиента должна быть в формате DOCX. Старый DOC сначала сохраните в Word как DOCX.');
        }

        $this->assertValidDocx($disk->path($sourcePath));
        $document->loadMissing(['items', 'clientCounterparty', 'clientContact']);

        $createdPaths = [];

        try {
            return DB::transaction(function () use ($document, $input, $sourcePath, $disk, &$createdPaths): CommercialContract {
                $seller = (array) config('legal.commercial_documents', []);
                $buyer = $this->buyerSnapshot($document);
                $contract = CommercialContract::query()->create([
                    'commercial_document_id' => $document->id,
                    'created_by' => auth()->id(),
                    'status' => 'generated',
                    'template_mode' => 'client',
                    'template_version' => 1,
                    'contract_date' => $input['contract_date'] ?? today(),
                    'valid_until' => $input['valid_until'] ?? null,
                    'seller_snapshot' => $seller,
                    'buyer_snapshot' => $buyer,
                    'terms_snapshot' => [],
                ]);

                $directory = 'commercial-contracts/'.$contract->id;
                $originalName = trim((string) ($input['client_template_name'] ?? ''));
                $originalName = $originalName !== '' ? basename($originalName) : 'Форма-клиента.docx';
                $sourceCopy = $directory.'/Исходник-'.$originalName;
                $generatedPath = $directory.'/Договор-'.$contract->number.'-форма-клиента.docx';

                if (! $disk->copy($sourcePath, $sourceCopy) || ! $disk->copy($sourcePath, $generatedPath)) {
                    throw new RuntimeException('Не удалось сохранить исходную форму клиента.');
                }
                $createdPaths = [$sourceCopy, $generatedPath];

                $report = $this->replaceInDocx(
                    $disk->path($generatedPath),
                    $this->replacements($contract, $document, $seller, $buyer),
                    $seller,
                    (bool) ($input['auto_replace_supplier_column'] ?? true),
                );

                if (($report['seller_replacements'] ?? 0) < 1) {
                    throw new RuntimeException('В форме не найдены метки реквизитов или таблица с колонками «Поставщик/Исполнитель» и «Покупатель/Заказчик». Исходник не изменён.');
                }

                $contract->forceFill([
                    'source_template_path' => $sourceCopy,
                    'generated_docx_path' => $generatedPath,
                    'content_checksum' => hash_file('sha256', $disk->path($generatedPath)) ?: null,
                    'terms_snapshot' => ['replacement_report' => $report],
                    'generated_at' => now(),
                ])->save();

                return $contract->fresh(['commercialDocument.items']);
            });
        } catch (\Throwable $exception) {
            foreach ($createdPaths as $path) {
                $disk->delete($path);
            }

            throw $exception;
        }
    }

    private function assertValidDocx(string $path): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('На сервере недоступен модуль ZIP, необходимый для обработки DOCX.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Файл повреждён или не является документом DOCX.');
        }
        $valid = $zip->locateName('word/document.xml') !== false;
        $zip->close();

        if (! $valid) {
            throw new RuntimeException('В файле отсутствует основной документ Word. Загрузите корректный DOCX.');
        }
    }

    /**
     * @param array<string, string> $replacements
     * @param array<string, mixed> $seller
     * @return array<string, mixed>
     */
    private function replaceInDocx(string $path, array $replacements, array $seller, bool $autoReplaceSupplierColumn): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Не удалось открыть рабочую копию DOCX.');
        }

        $partNames = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);
            if (preg_match('~^word/(?:document|header\d*|footer\d*|footnotes|endnotes)\.xml$~', $name)) {
                $partNames[] = $name;
            }
        }

        $counts = array_fill_keys(array_keys($replacements), 0);
        $supplierColumnReplaced = false;
        $unresolved = [];

        foreach ($partNames as $partName) {
            $xml = $zip->getFromName($partName);
            if (! is_string($xml)) {
                continue;
            }

            [$updated, $partCounts, $columnReplaced, $partUnresolved] = $this->replaceXmlPart(
                $xml,
                $replacements,
                $seller,
                $autoReplaceSupplierColumn && ! $supplierColumnReplaced,
            );
            foreach ($partCounts as $token => $count) {
                $counts[$token] += $count;
            }
            $supplierColumnReplaced = $supplierColumnReplaced || $columnReplaced;
            $unresolved = array_merge($unresolved, $partUnresolved);
            $zip->addFromString($partName, $updated);
        }

        $zip->close();

        $sellerTokens = array_filter(array_keys($replacements), fn (string $token): bool => str_contains($token, 'SUPPLIER_') || str_contains($token, 'ПОСТАВЩИК_'));
        $sellerReplacementCount = array_sum(array_intersect_key($counts, array_flip($sellerTokens))) + ($supplierColumnReplaced ? 1 : 0);

        return [
            'mode' => $supplierColumnReplaced ? 'supplier_column' : 'placeholders',
            'seller_replacements' => $sellerReplacementCount,
            'replaced_tokens' => array_filter($counts),
            'supplier_column_replaced' => $supplierColumnReplaced,
            'unresolved_tokens' => array_values(array_unique($unresolved)),
        ];
    }

    /**
     * @param array<string, string> $replacements
     * @param array<string, mixed> $seller
     * @return array{string, array<string, int>, bool, array<int, string>}
     */
    private function replaceXmlPart(string $xml, array $replacements, array $seller, bool $autoReplaceSupplierColumn): array
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $loaded) {
            throw new RuntimeException('Не удалось прочитать структуру формы клиента.');
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::WORD_NAMESPACE);
        $counts = array_fill_keys(array_keys($replacements), 0);

        foreach ($xpath->query('//w:p') ?: [] as $paragraph) {
            if (! $paragraph instanceof DOMElement) {
                continue;
            }
            $text = $this->nodeText($xpath, $paragraph);
            $updated = $text;
            foreach ($replacements as $token => $value) {
                $tokenCount = substr_count($updated, $token);
                if ($tokenCount > 0) {
                    $updated = str_replace($token, $value, $updated);
                    $counts[$token] += $tokenCount;
                }
            }
            if ($updated !== $text) {
                $this->setParagraphText($dom, $xpath, $paragraph, $updated);
            }
        }

        $sellerTokenCount = array_sum(array_filter($counts, fn (int $count, string $token): bool => $count > 0 && (str_contains($token, 'SUPPLIER_') || str_contains($token, 'ПОСТАВЩИК_')), ARRAY_FILTER_USE_BOTH));
        $columnReplaced = false;
        if ($autoReplaceSupplierColumn && $sellerTokenCount === 0) {
            $columnReplaced = $this->replaceSupplierColumn($dom, $xpath, $seller);
        }

        $remainingText = $dom->textContent ?: '';
        preg_match_all('/\{\{[A-ZА-ЯЁ0-9_]+\}\}/u', $remainingText, $matches);

        return [$dom->saveXML() ?: $xml, $counts, $columnReplaced, $matches[0] ?? []];
    }

    /** @param array<string, mixed> $seller */
    private function replaceSupplierColumn(DOMDocument $dom, DOMXPath $xpath, array $seller): bool
    {
        foreach ($xpath->query('//w:tbl') ?: [] as $table) {
            if (! $table instanceof DOMElement) {
                continue;
            }
            $rows = $xpath->query('./w:tr', $table);
            if (! $rows || $rows->length < 2) {
                continue;
            }

            foreach ($rows as $rowIndex => $row) {
                $cells = $xpath->query('./w:tc', $row);
                if (! $cells || $cells->length < 2) {
                    continue;
                }
                $texts = [];
                foreach ($cells as $cell) {
                    $texts[] = mb_strtolower($this->nodeText($xpath, $cell));
                }
                $supplierIndex = $this->matchingColumn($texts, ['поставщик', 'исполнитель', 'продавец']);
                $buyerIndex = $this->matchingColumn($texts, ['покупатель', 'заказчик']);
                if ($supplierIndex === null || $buyerIndex === null || $supplierIndex === $buyerIndex) {
                    continue;
                }

                $replaced = false;
                for ($next = $rowIndex + 1; $next < $rows->length; $next++) {
                    $nextCells = $xpath->query('./w:tc', $rows->item($next));
                    $cell = $nextCells?->item($supplierIndex);
                    if (! $cell instanceof DOMElement) {
                        continue;
                    }
                    if (! $replaced) {
                        $this->setCellText($dom, $xpath, $cell, $this->sellerBlock($seller));
                        $replaced = true;
                    } else {
                        $this->setCellText($dom, $xpath, $cell, '');
                    }
                }

                if ($replaced) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param array<int, string> $texts @param array<int, string> $needles */
    private function matchingColumn(array $texts, array $needles): ?int
    {
        foreach ($texts as $index => $text) {
            foreach ($needles as $needle) {
                if (str_contains($text, $needle)) {
                    return $index;
                }
            }
        }

        return null;
    }

    private function nodeText(DOMXPath $xpath, DOMNode $node): string
    {
        $text = '';
        foreach ($xpath->query('.//w:t', $node) ?: [] as $textNode) {
            $text .= $textNode->textContent;
        }

        return $text;
    }

    private function setCellText(DOMDocument $dom, DOMXPath $xpath, DOMElement $cell, string $text): void
    {
        $paragraphs = $xpath->query('./w:p', $cell);
        $first = $paragraphs?->item(0);
        if (! $first instanceof DOMElement) {
            $first = $dom->createElementNS(self::WORD_NAMESPACE, 'w:p');
            $cell->appendChild($first);
        }
        $this->setParagraphText($dom, $xpath, $first, $text);
        if ($paragraphs) {
            for ($index = 1; $index < $paragraphs->length; $index++) {
                $paragraph = $paragraphs->item($index);
                if ($paragraph instanceof DOMElement) {
                    $this->setParagraphText($dom, $xpath, $paragraph, '');
                }
            }
        }
    }

    private function setParagraphText(DOMDocument $dom, DOMXPath $xpath, DOMElement $paragraph, string $text): void
    {
        $runs = $xpath->query('./w:r', $paragraph);
        $run = $runs?->item(0);
        if (! $run instanceof DOMElement) {
            $run = $dom->createElementNS(self::WORD_NAMESPACE, 'w:r');
            $paragraph->appendChild($run);
        }

        foreach (iterator_to_array($xpath->query('./w:r', $paragraph) ?: []) as $existingRun) {
            if ($existingRun !== $run) {
                $paragraph->removeChild($existingRun);
            }
        }
        foreach (iterator_to_array($xpath->query('.//w:t|.//w:br', $paragraph) ?: []) as $node) {
            $node->parentNode?->removeChild($node);
        }

        foreach (preg_split('/\R/u', $text) ?: [''] as $index => $line) {
            if ($index > 0) {
                $run->appendChild($dom->createElementNS(self::WORD_NAMESPACE, 'w:br'));
            }
            $textNode = $dom->createElementNS(self::WORD_NAMESPACE, 'w:t');
            $textNode->setAttribute('xml:space', 'preserve');
            $textNode->appendChild($dom->createTextNode($line));
            $run->appendChild($textNode);
        }
    }

    /** @return array<string, string> */
    private function replacements(CommercialContract $contract, CommercialDocument $document, array $seller, array $buyer): array
    {
        $phones = implode(', ', (array) ($seller['phones'] ?? []));
        $values = [
            'SUPPLIER_NAME' => (string) ($seller['name'] ?? ''),
            'SUPPLIER_SHORT_NAME' => (string) ($seller['short_name'] ?? ''),
            'SUPPLIER_UNP' => (string) ($seller['unp'] ?? ''),
            'SUPPLIER_OKPO' => (string) ($seller['okpo'] ?? ''),
            'SUPPLIER_IBAN' => (string) ($seller['iban'] ?? ''),
            'SUPPLIER_BANK' => (string) ($seller['bank'] ?? ''),
            'SUPPLIER_BIC' => (string) ($seller['bic'] ?? ''),
            'SUPPLIER_ADDRESS' => (string) ($seller['legal_address'] ?? ''),
            'SUPPLIER_WAREHOUSE_ADDRESS' => (string) ($seller['warehouse_address'] ?? ''),
            'SUPPLIER_PHONE' => $phones,
            'SUPPLIER_EMAIL' => (string) ($seller['email'] ?? ''),
            'SUPPLIER_WEBSITE' => (string) ($seller['website'] ?? ''),
            'SUPPLIER_DIRECTOR' => (string) ($seller['director'] ?? ''),
            'SUPPLIER_REPRESENTATIVE' => (string) ($seller['representative'] ?? ''),
            'SUPPLIER_AUTHORITY' => (string) ($seller['authority_basis'] ?? ''),
            'CONTRACT_NUMBER' => $contract->number,
            'CONTRACT_DATE' => $contract->contract_date?->format('d.m.Y') ?? '',
            'QUOTE_NUMBER' => (string) $document->number,
            'QUOTE_DATE' => $document->issued_at?->format('d.m.Y') ?? '',
            'QUOTE_TOTAL' => number_format((float) $document->total, 2, ',', ' ').' '.$document->currency,
            'BUYER_NAME' => (string) ($buyer['name'] ?? ''),
            'BUYER_UNP' => (string) ($buyer['unp'] ?? ''),
        ];

        $aliases = [];
        foreach ($values as $key => $value) {
            $aliases['{{'.$key.'}}'] = $value;
        }
        foreach (array_filter($values, fn (string $key): bool => str_starts_with($key, 'SUPPLIER_'), ARRAY_FILTER_USE_KEY) as $key => $value) {
            $aliases['{{ПОСТАВЩИК_'.substr($key, strlen('SUPPLIER_')).'}}'] = $value;
        }

        return $aliases;
    }

    /** @return array<string, mixed> */
    private function buyerSnapshot(CommercialDocument $document): array
    {
        return [
            'name' => trim((string) ($document->client_legal_name ?: $document->client_company ?: $document->client_name)),
            'unp' => trim((string) $document->client_unp),
            'legal_address' => trim((string) $document->client_address),
            'phone' => trim((string) $document->client_phone),
            'email' => trim((string) $document->client_email),
        ];
    }

    /** @param array<string, mixed> $seller */
    private function sellerBlock(array $seller): string
    {
        return implode("\n", array_filter([
            (string) ($seller['name'] ?? ''),
            'УНП '.($seller['unp'] ?? ''),
            isset($seller['okpo']) ? 'ОКПО '.$seller['okpo'] : null,
            (string) ($seller['legal_address'] ?? ''),
            'IBAN '.($seller['iban'] ?? ''),
            (string) ($seller['bank'] ?? ''),
            'BIC '.($seller['bic'] ?? ''),
            'Тел.: '.implode(', ', (array) ($seller['phones'] ?? [])),
            'Email: '.($seller['email'] ?? ''),
            '',
            '________________ '.($seller['director'] ?? ''),
        ], fn (?string $line): bool => $line !== null));
    }
}
