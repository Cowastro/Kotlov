<?php

namespace App\Http\Controllers;

use App\Models\CrmMailMessage;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class CrmMailAttachmentPreviewController extends Controller
{
    private const MAX_DOCX_XML_BYTES = 8_000_000;

    public function __invoke(CrmMailMessage $message, int $attachment): View|BinaryFileResponse
    {
        abort_unless(auth()->user()?->is_active && auth()->user()?->isManager(), 403);

        $file = collect($message->metadata['attachments'] ?? [])->values()->get($attachment);
        abort_unless(is_array($file), 404);

        $path = (string) ($file['path'] ?? '');
        abort_unless(str_starts_with($path, 'crm-mail-attachments/'), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        $name = (string) ($file['name'] ?? basename($path));
        $extension = mb_strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (isset($this->inlineMimeTypes()[$extension])) {
            $response = response()->file(Storage::disk('local')->path($path), [
                'Content-Type' => $this->inlineMimeTypes()[$extension],
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]);
            $fallbackName = preg_replace('/[^A-Za-z0-9._-]/', '_', Str::ascii($name)) ?: 'attachment.'.$extension;
            $response->setContentDisposition('inline', $name, $fallbackName);

            return $response;
        }

        $content = match ($extension) {
            'docx' => $this->docxHtml(Storage::disk('local')->path($path)),
            'txt', 'csv' => '<pre>'.e($this->plainText(Storage::disk('local')->get($path))).'</pre>',
            default => abort(415, 'Для этого формата доступно только скачивание.'),
        };

        return view('filament.crm-mail.attachment-preview', [
            'name' => $name,
            'content' => $content,
            'downloadUrl' => route('admin.crm-mail-messages.attachments.download', [
                'message' => $message,
                'attachment' => $attachment,
            ]),
        ]);
    }

    /** @return array<string, string> */
    private function inlineMimeTypes(): array
    {
        return [
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        ];
    }

    private function plainText(string $content): string
    {
        return mb_convert_encoding($content, 'UTF-8', mb_detect_encoding($content, ['UTF-8', 'Windows-1251', 'ISO-8859-1'], true) ?: 'UTF-8');
    }

    private function docxHtml(string $path): string
    {
        $zip = new ZipArchive;
        abort_unless($zip->open($path) === true, 422, 'Файл DOCX повреждён или имеет неверный формат.');

        try {
            $documentIndex = $zip->locateName('word/document.xml');
            abort_unless($documentIndex !== false, 422, 'В DOCX не найден текст документа.');

            $document = $zip->statIndex($documentIndex);
            abort_unless(
                is_array($document) && ($document['size'] ?? 0) <= self::MAX_DOCX_XML_BYTES,
                413,
                'Документ слишком большой для безопасного просмотра. Скачайте оригинал.',
            );

            $xml = $zip->getFromIndex($documentIndex);
        } finally {
            $zip->close();
        }

        abort_unless(is_string($xml), 422, 'В DOCX не найден текст документа.');

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        abort_unless($loaded, 422, 'Не удалось прочитать содержимое DOCX.');

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $body = $xpath->query('//w:body')->item(0);
        abort_unless($body instanceof DOMNode, 422, 'В DOCX отсутствует тело документа.');

        $html = '';
        foreach ($body->childNodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $html .= match ($node->localName) {
                'p' => $this->paragraphHtml($xpath, $node),
                'tbl' => $this->tableHtml($xpath, $node),
                default => '',
            };
        }

        return $html !== '' ? $html : '<p>В документе не найден отображаемый текст.</p>';
    }

    private function paragraphHtml(DOMXPath $xpath, DOMNode $paragraph): string
    {
        $text = $this->nodeText($xpath, $paragraph);

        return $text === '' ? '<div class="spacer"></div>' : '<p>'.e($text).'</p>';
    }

    private function tableHtml(DOMXPath $xpath, DOMNode $table): string
    {
        $html = '<table>';
        foreach ($xpath->query('./w:tr', $table) as $row) {
            $html .= '<tr>';
            foreach ($xpath->query('./w:tc', $row) as $cell) {
                $html .= '<td>'.e($this->nodeText($xpath, $cell)).'</td>';
            }
            $html .= '</tr>';
        }

        return $html.'</table>';
    }

    private function nodeText(DOMXPath $xpath, DOMNode $node): string
    {
        $parts = [];
        foreach ($xpath->query('.//w:t | .//w:tab | .//w:br | .//w:cr', $node) as $textNode) {
            $parts[] = match ($textNode->localName) {
                'tab' => "\t",
                'br', 'cr' => "\n",
                default => $textNode->textContent,
            };
        }

        return trim(implode('', $parts));
    }
}
