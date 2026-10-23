<?php

namespace App\Services\CommercialContracts;

use App\Models\CommercialContract;
use App\Models\CommercialDocument;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class CommercialContractGenerator
{
    /** @param array<string, mixed> $input */
    public function create(CommercialDocument $document, array $input): CommercialContract
    {
        $document->loadMissing(['items', 'clientCounterparty', 'clientContact']);

        return DB::transaction(function () use ($document, $input): CommercialContract {
            $contract = CommercialContract::query()->create([
                'commercial_document_id' => $document->id,
                'created_by' => auth()->id(),
                'status' => 'generated',
                'template_mode' => 'standard',
                'template_version' => 1,
                'contract_date' => $input['contract_date'] ?? today(),
                'valid_until' => $input['valid_until'] ?? now()->endOfYear()->toDateString(),
                'seller_snapshot' => (array) config('legal.commercial_documents', []),
                'buyer_snapshot' => $this->buyerSnapshot($document, $input),
                'terms_snapshot' => $this->termsSnapshot($input),
            ]);

            $data = $this->data($contract);
            $directory = 'commercial-contracts/'.$contract->id;
            $docxPath = $directory.'/Договор-'.$contract->number.'.docx';
            $pdfPath = $directory.'/Договор-'.$contract->number.'.pdf';
            $docx = $this->renderDocx($data);
            $pdf = $this->renderPdf($data);

            Storage::disk('local')->put($docxPath, $docx);
            Storage::disk('local')->put($pdfPath, $pdf);

            $contract->forceFill([
                'generated_docx_path' => $docxPath,
                'generated_pdf_path' => $pdfPath,
                'content_checksum' => hash('sha256', $docx),
                'generated_at' => now(),
            ])->save();

            if (($input['save_buyer_requisites'] ?? false) && $document->clientCounterparty) {
                $document->clientCounterparty->forceFill([
                    'legal_name' => $input['buyer_name'] ?? $document->clientCounterparty->legal_name,
                    'unp' => $input['buyer_unp'] ?? $document->clientCounterparty->unp,
                    'legal_address' => $input['buyer_address'] ?? $document->clientCounterparty->legal_address,
                    'postal_address' => $input['buyer_postal_address'] ?? $document->clientCounterparty->postal_address,
                    'bank_name' => $input['buyer_bank'] ?? null,
                    'bank_bic' => $input['buyer_bic'] ?? null,
                    'bank_iban' => $input['buyer_iban'] ?? null,
                    'authorized_representative' => $input['buyer_representative'] ?? null,
                    'representative_position' => $input['buyer_position'] ?? null,
                    'authority_basis' => $input['buyer_authority_basis'] ?? null,
                ])->save();
            }

            return $contract->fresh(['commercialDocument.items']);
        });
    }

    /** @return array<string, mixed> */
    public function data(CommercialContract $contract): array
    {
        $contract->loadMissing('commercialDocument.items');
        $terms = (array) $contract->terms_snapshot;

        return [
            'contract' => $contract,
            'document' => $contract->commercialDocument,
            'seller' => (array) $contract->seller_snapshot,
            'buyer' => (array) $contract->buyer_snapshot,
            'terms' => $terms,
            'purposeText' => $this->purposeText((string) ($terms['purchase_purpose'] ?? 'own_use'), (string) ($terms['purpose_custom'] ?? '')),
            'paymentText' => $this->paymentText($terms),
            'deliveryText' => $this->deliveryText($terms),
        ];
    }

    /** @param array<string, mixed> $data */
    public function renderPdf(array $data): string
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('pdf.commercial-contract', $data)->render(), 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }

    /** @param array<string, mixed> $data */
    public function renderDocx(array $data): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('На сервере недоступен модуль ZIP, необходимый для создания DOCX.');
        }

        $temporaryDirectory = function_exists('storage_path')
            ? storage_path('app/tmp')
            : dirname(__DIR__, 3).'/storage/app/tmp';
        if (! is_dir($temporaryDirectory) && ! mkdir($temporaryDirectory, 0775, true) && ! is_dir($temporaryDirectory)) {
            throw new RuntimeException('Не удалось подготовить временную папку для DOCX.');
        }

        $temporary = tempnam($temporaryDirectory, 'contract-docx-');
        if ($temporary === false) {
            throw new RuntimeException('Не удалось создать временный файл договора.');
        }

        @unlink($temporary);

        $zip = new ZipArchive;
        if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($temporary);
            throw new RuntimeException('Не удалось создать DOCX договора.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->packageRelationshipsXml());
        $zip->addFromString('word/_rels/document.xml.rels', $this->documentRelationshipsXml());
        $zip->addFromString('word/styles.xml', $this->stylesXml());
        $zip->addFromString('word/document.xml', $this->documentXml($data));
        $zip->close();

        $contents = file_get_contents($temporary);
        @unlink($temporary);

        if ($contents === false) {
            throw new RuntimeException('Не удалось прочитать созданный DOCX договора.');
        }

        return $contents;
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    private function buyerSnapshot(CommercialDocument $document, array $input): array
    {
        return [
            'name' => trim((string) ($input['buyer_name'] ?? $document->client_legal_name ?? $document->client_company ?? $document->client_name)),
            'unp' => trim((string) ($input['buyer_unp'] ?? $document->client_unp)),
            'legal_address' => trim((string) ($input['buyer_address'] ?? $document->client_address)),
            'postal_address' => trim((string) ($input['buyer_postal_address'] ?? $input['buyer_address'] ?? $document->client_address)),
            'representative' => trim((string) ($input['buyer_representative'] ?? $document->client_name)),
            'position' => trim((string) ($input['buyer_position'] ?? 'Директор')),
            'authority_basis' => trim((string) ($input['buyer_authority_basis'] ?? 'Устава')),
            'iban' => trim((string) ($input['buyer_iban'] ?? '')),
            'bank' => trim((string) ($input['buyer_bank'] ?? '')),
            'bic' => trim((string) ($input['buyer_bic'] ?? '')),
            'phone' => trim((string) ($input['buyer_phone'] ?? $document->client_phone)),
            'email' => trim((string) ($input['buyer_email'] ?? $document->client_email)),
        ];
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    private function termsSnapshot(array $input): array
    {
        return Arr::only($input, [
            'purchase_purpose', 'purpose_custom', 'regulated_goods', 'payment_type',
            'prepayment_percent', 'payment_days', 'delivery_method', 'delivery_days',
            'delivery_day_type', 'delivery_start_event', 'auto_renew',
        ]);
    }

    private function purposeText(string $purpose, string $custom): string
    {
        return match ($purpose) {
            'resale' => 'для последующей розничной торговли и реализации конечным потребителям',
            'repair' => 'для целей текущего ремонта и (или) благоустройства собственных территорий',
            'mixed' => 'для последующей розничной торговли, текущего ремонта и (или) благоустройства собственных территорий',
            'other' => $custom !== '' ? $custom : 'в целях, согласованных сторонами',
            default => 'для собственного потребления Покупателя',
        };
    }

    /** @param array<string, mixed> $terms */
    private function paymentText(array $terms): string
    {
        $days = max(1, (int) ($terms['payment_days'] ?? 3));
        $percent = min(100, max(1, (int) ($terms['prepayment_percent'] ?? 100)));

        return match ($terms['payment_type'] ?? 'prepayment') {
            'partial' => "Покупатель перечисляет предоплату {$percent}% в течение {$days} рабочих дней с момента выставления счёта. Оставшаяся сумма оплачивается до отгрузки товара.",
            'on_delivery' => 'Оплата производится в день передачи товара Покупателю на основании выставленного счёта.',
            'deferred' => "Покупатель оплачивает товар в течение {$days} банковских дней с даты его передачи.",
            default => "Покупатель производит 100% предварительную оплату товара в течение {$days} рабочих дней с момента выставления счёта.",
        };
    }

    /** @param array<string, mixed> $terms */
    private function deliveryText(array $terms): string
    {
        $days = max(1, (int) ($terms['delivery_days'] ?? 10));
        $dayType = match ($terms['delivery_day_type'] ?? 'working') {
            'banking' => 'банковских',
            'calendar' => 'календарных',
            default => 'рабочих',
        };
        $event = match ($terms['delivery_start_event'] ?? 'prepayment') {
            'contract' => 'с даты подписания договора',
            'readiness' => 'с даты уведомления Покупателя о готовности товара',
            default => 'с даты поступления предусмотренной договором предоплаты',
        };
        $method = ($terms['delivery_method'] ?? 'pickup') === 'supplier'
            ? 'доставка транспортом Поставщика по согласованному сторонами адресу'
            : 'самовывоз со склада Поставщика транспортом Покупателя';

        return "Срок поставки — не более {$days} {$dayType} дней {$event}. Способ поставки: {$method}.";
    }

    /** @param array<string, mixed> $data */
    private function documentXml(array $data): string
    {
        $contract = $data['contract'];
        $document = $data['document'];
        $seller = $data['seller'];
        $buyer = $data['buyer'];
        $terms = $data['terms'];
        $body = '';
        $body .= $this->paragraph('ДОГОВОР ПОСТАВКИ № '.$contract->number, true, true, 28);
        $body .= $this->twoColumnLine('г. Минск', $contract->contract_date->translatedFormat('d F Y г.'));
        $body .= $this->paragraph($this->intro($seller, $buyer), false, false, 24, true);
        $body .= $this->heading('1. ПРЕДМЕТ И ЦЕЛЬ ДОГОВОРА');
        $body .= $this->paragraph('1.1. Поставщик обязуется на основании заявок Покупателя поставить, а Покупатель принять и оплатить товары согласно выставленным счетам и (или) спецификациям, являющимся неотъемлемой частью настоящего договора.', false, false, 24, true);
        $body .= $this->paragraph('1.2. Цель приобретения товара — '.$data['purposeText'].'.', false, false, 24, true);
        $body .= $this->paragraph('1.3. Общая сумма договора определяется стоимостью всех партий товара, поставленных в течение срока его действия.', false, false, 24, true);
        if ((bool) ($terms['regulated_goods'] ?? false)) {
            $body .= $this->paragraph('1.4. В отношении товаров, для которых законодательством Республики Беларусь установлено регулирование цен или ограничения по цели приобретения, Покупатель обязуется использовать и реализовывать их только в заявленных целях и соблюдать применимые требования законодательства.', false, false, 24, true);
        }
        $body .= $this->heading('2. УСЛОВИЯ ПОСТАВКИ И ОБЯЗАТЕЛЬСТВА СТОРОН');
        $body .= $this->paragraph('2.1. Поставщик обеспечивает соответствие качества товара требованиям изготовителя и передаёт относящиеся к товару документы, если их наличие обязательно.', false, false, 24, true);
        $body .= $this->paragraph('2.2. '.$data['deliveryText'], false, false, 24, true);
        $body .= $this->paragraph('2.3. Покупатель обязан принять товар по количеству, ассортименту, комплектности и внешнему состоянию, а также обеспечить надлежащие условия его транспортировки и хранения.', false, false, 24, true);
        $body .= $this->heading('3. ЦЕНА И ПОРЯДОК РАСЧЁТОВ');
        $body .= $this->paragraph('3.1. Цена товара указывается в счетах и (или) спецификациях. Стоимость упаковки включается в цену товара, если в товаросопроводительных документах не указано иное.', false, false, 24, true);
        $body .= $this->paragraph('3.2. '.$data['paymentText'], false, false, 24, true);
        $body .= $this->heading('4. КАЧЕСТВО, УПАКОВКА И ГАРАНТИЯ');
        $body .= $this->paragraph('4.1. Качество, упаковка и маркировка товара должны соответствовать обязательным требованиям законодательства Республики Беларусь и документации изготовителя.', false, false, 24, true);
        $body .= $this->paragraph('4.2. Гарантийный срок определяется изготовителем и указывается в паспорте, гарантийном талоне либо ином документе на товар.', false, false, 24, true);
        $body .= $this->heading('5. ПРИЁМКА ТОВАРА');
        $body .= $this->paragraph('5.1. Приёмка товара по количеству и качеству производится в порядке, установленном законодательством Республики Беларусь. Видимые недостатки и расхождения фиксируются при передаче товара.', false, false, 24, true);
        $body .= $this->paragraph('5.2. Требования в связи с недостатками товара рассматриваются в порядке и сроки, установленные законодательством и условиями гарантии изготовителя.', false, false, 24, true);
        $body .= $this->heading('6. ОТВЕТСТВЕННОСТЬ СТОРОН');
        $body .= $this->paragraph('6.1. За неисполнение или ненадлежащее исполнение обязательств стороны несут ответственность в соответствии с настоящим договором и законодательством Республики Беларусь.', false, false, 24, true);
        $body .= $this->paragraph('6.2. При нарушении срока оплаты наличие товара и первоначально согласованный срок поставки не гарантируются.', false, false, 24, true);
        $body .= $this->heading('7. СРОК ДЕЙСТВИЯ И ПРОЧИЕ УСЛОВИЯ');
        $body .= $this->paragraph('7.1. Договор вступает в силу с момента подписания и действует до '.$contract->valid_until?->format('d.m.Y').' включительно, а в части неисполненных обязательств — до их полного исполнения.'.((bool) ($terms['auto_renew'] ?? true) ? ' Если ни одна из сторон письменно не заявит о прекращении договора не позднее чем за 14 календарных дней до окончания срока, он продлевается на следующий календарный год.' : ''), false, false, 24, true);
        $body .= $this->paragraph('7.2. Споры разрешаются путём переговоров, а при недостижении соглашения — в экономическом суде в соответствии с законодательством Республики Беларусь.', false, false, 24, true);
        $body .= $this->paragraph('7.3. Во всём остальном стороны руководствуются законодательством Республики Беларусь.', false, false, 24, true);
        $body .= $this->heading('8. РЕКВИЗИТЫ И ПОДПИСИ СТОРОН');
        $body .= $this->requisitesTable($seller, $buyer);
        $body .= '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
        $body .= $this->paragraph('ПРИЛОЖЕНИЕ № 1. СПЕЦИФИКАЦИЯ К ДОГОВОРУ', true, true, 24);
        $body .= $this->itemsTable($document->items->all(), (float) $document->total, (string) $document->currency);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
            .$body
            .'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" w:header="708" w:footer="708"/></w:sectPr>'
            .'</w:body></w:document>';
    }

    /** @param array<string, mixed> $seller @param array<string, mixed> $buyer */
    private function intro(array $seller, array $buyer): string
    {
        return ($seller['name'] ?? 'ООО «СанБизнесГруп»').', именуемое в дальнейшем «Поставщик», в лице '.($seller['representative'] ?? '').', действующего на основании '.($seller['authority_basis'] ?? '').', и '.($buyer['name'] ?? '').', именуемое в дальнейшем «Покупатель», в лице '.trim(($buyer['position'] ?? '').' '.($buyer['representative'] ?? '')).', действующего на основании '.($buyer['authority_basis'] ?? 'Устава').', заключили настоящий договор о нижеследующем:';
    }

    private function heading(string $text): string
    {
        return $this->paragraph($text, true, true, 24);
    }

    private function paragraph(string $text, bool $bold = false, bool $center = false, int $size = 24, bool $justify = false): string
    {
        $alignment = $center ? '<w:jc w:val="center"/>' : ($justify ? '<w:jc w:val="both"/>' : '');
        $boldXml = $bold ? '<w:b/>' : '';

        return '<w:p><w:pPr>'.$alignment.'<w:spacing w:after="100" w:line="276" w:lineRule="auto"/></w:pPr><w:r><w:rPr>'.$boldXml.'<w:sz w:val="'.$size.'"/><w:szCs w:val="'.$size.'"/></w:rPr><w:t xml:space="preserve">'.$this->xml($text).'</w:t></w:r></w:p>';
    }

    private function twoColumnLine(string $left, string $right): string
    {
        return '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders><w:top w:val="nil"/><w:left w:val="nil"/><w:bottom w:val="nil"/><w:right w:val="nil"/><w:insideH w:val="nil"/><w:insideV w:val="nil"/></w:tblBorders></w:tblPr><w:tr>'
            .$this->cell($left, 5000).$this->cell($right, 5000, true).'</w:tr></w:tbl>';
    }

    /** @param array<string, mixed> $seller @param array<string, mixed> $buyer */
    private function requisitesTable(array $seller, array $buyer): string
    {
        $sellerText = implode("\n", array_filter([
            (string) ($seller['name'] ?? ''),
            'УНП: '.($seller['unp'] ?? ''),
            'Юридический адрес: '.($seller['legal_address'] ?? ''),
            'IBAN: '.($seller['iban'] ?? ''),
            (string) ($seller['bank'] ?? ''),
            'BIC: '.($seller['bic'] ?? ''),
            'Тел.: '.implode(', ', (array) ($seller['phones'] ?? [])),
            'Email: '.($seller['email'] ?? ''),
            '',
            '________________ '.($seller['director'] ?? ''),
        ]));
        $buyerText = implode("\n", array_filter([
            (string) ($buyer['name'] ?? ''),
            'УНП: '.($buyer['unp'] ?? ''),
            'Юридический адрес: '.($buyer['legal_address'] ?? ''),
            'IBAN: '.($buyer['iban'] ?? ''),
            (string) ($buyer['bank'] ?? ''),
            'BIC: '.($buyer['bic'] ?? ''),
            'Тел.: '.($buyer['phone'] ?? ''),
            'Email: '.($buyer['email'] ?? ''),
            '',
            '________________ '.($buyer['representative'] ?? ''),
        ]));

        return '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders><w:top w:val="single" w:sz="4"/><w:left w:val="single" w:sz="4"/><w:bottom w:val="single" w:sz="4"/><w:right w:val="single" w:sz="4"/><w:insideH w:val="single" w:sz="4"/><w:insideV w:val="single" w:sz="4"/></w:tblBorders></w:tblPr>'
            .'<w:tr>'.$this->cell('ПОСТАВЩИК', 5000, false, true).$this->cell('ПОКУПАТЕЛЬ', 5000, false, true).'</w:tr>'
            .'<w:tr>'.$this->cell($sellerText, 5000).$this->cell($buyerText, 5000).'</w:tr></w:tbl>';
    }

    /** @param array<int, mixed> $items */
    private function itemsTable(array $items, float $total, string $currency): string
    {
        $xml = '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders><w:top w:val="single" w:sz="4"/><w:left w:val="single" w:sz="4"/><w:bottom w:val="single" w:sz="4"/><w:right w:val="single" w:sz="4"/><w:insideH w:val="single" w:sz="4"/><w:insideV w:val="single" w:sz="4"/></w:tblBorders></w:tblPr>';
        $xml .= '<w:tr>'.$this->cell('№', 500, false, true).$this->cell('Товар', 4500, false, true).$this->cell('Кол-во', 1200, false, true).$this->cell('Цена', 1800, false, true).$this->cell('Сумма', 2000, false, true).'</w:tr>';
        foreach ($items as $index => $item) {
            $xml .= '<w:tr>'.$this->cell((string) ($index + 1), 500).$this->cell((string) $item->name, 4500).$this->cell(rtrim(rtrim(number_format((float) $item->quantity, 3, ',', ' '), '0'), ',').' '.$item->unit, 1200).$this->cell(number_format((float) $item->unit_price, 2, ',', ' '), 1800).$this->cell(number_format((float) $item->line_total, 2, ',', ' '), 2000).'</w:tr>';
        }
        $xml .= '<w:tr>'.$this->cell('ИТОГО', 8000, true, true, 4).$this->cell(number_format($total, 2, ',', ' ').' '.$currency, 2000, false, true).'</w:tr></w:tbl>';

        return $xml;
    }

    private function cell(string $text, int $width, bool $right = false, bool $bold = false, int $span = 1): string
    {
        $paragraphs = '';
        foreach (explode("\n", $text) as $line) {
            $paragraphs .= '<w:p><w:pPr>'.($right ? '<w:jc w:val="right"/>' : '').'<w:spacing w:after="40"/></w:pPr><w:r><w:rPr>'.($bold ? '<w:b/>' : '').'<w:sz w:val="20"/><w:szCs w:val="20"/></w:rPr><w:t xml:space="preserve">'.$this->xml($line).'</w:t></w:r></w:p>';
        }

        return '<w:tc><w:tcPr><w:tcW w:w="'.$width.'" w:type="dxa"/>'.($span > 1 ? '<w:gridSpan w:val="'.$span.'"/>' : '').'<w:tcMar><w:top w:w="80" w:type="dxa"/><w:left w:w="80" w:type="dxa"/><w:bottom w:w="80" w:type="dxa"/><w:right w:w="80" w:type="dxa"/></w:tcMar></w:tcPr>'.$paragraphs.'</w:tc>';
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/></Types>';
    }

    private function packageRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>';
    }

    private function documentRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:eastAsia="Times New Roman" w:cs="Times New Roman"/><w:sz w:val="24"/><w:szCs w:val="24"/></w:rPr></w:rPrDefault></w:docDefaults></w:styles>';
    }
}
