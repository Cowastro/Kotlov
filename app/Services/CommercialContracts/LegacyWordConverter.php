<?php

namespace App\Services\CommercialContracts;

use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class LegacyWordConverter
{
    /** @return array{path: string, mode: string} */
    public function convertToDocx(string $sourcePath): array
    {
        $temporaryRoot = storage_path('app/tmp/legacy-word-'.bin2hex(random_bytes(8)));
        if (! mkdir($temporaryRoot, 0775, true) && ! is_dir($temporaryRoot)) {
            throw new RuntimeException('Не удалось подготовить временную папку для старого Word-файла.');
        }

        $inputPath = $temporaryRoot.'/source.doc';
        if (! copy($sourcePath, $inputPath)) {
            $this->removeDirectory($temporaryRoot);
            throw new RuntimeException('Не удалось подготовить старый Word-файл к конвертации.');
        }

        $outputPath = $temporaryRoot.'/source.docx';
        foreach ($this->executables() as $executable) {
            if ($this->isAbsolutePath($executable) && ! is_executable($executable)) {
                continue;
            }

            try {
                $process = new Process([
                    $executable,
                    '--headless',
                    '--nologo',
                    '--nodefault',
                    '--nolockcheck',
                    '--convert-to',
                    'docx',
                    '--outdir',
                    $temporaryRoot,
                    $inputPath,
                ]);
                $process->setTimeout(60)->run();
                if ($process->isSuccessful() && is_file($outputPath) && filesize($outputPath) > 0) {
                    return ['path' => $outputPath, 'mode' => 'libreoffice'];
                }
            } catch (\Throwable) {
                // Try the next known executable.
            }
        }

        if ($this->recoverTextAsDocx($inputPath, $outputPath)) {
            return ['path' => $outputPath, 'mode' => 'text_recovery'];
        }

        $this->removeDirectory($temporaryRoot);

        throw new RuntimeException('Старый файл DOC принят, но его содержимое не удалось восстановить. Попробуйте открыть файл в Word и сохранить как DOCX.');
    }

    public function available(): bool
    {
        foreach ($this->executables() as $executable) {
            if ($this->isAbsolutePath($executable) && ! is_executable($executable)) {
                continue;
            }
            try {
                $process = new Process([$executable, '--version']);
                $process->setTimeout(10)->run();
                if ($process->isSuccessful()) {
                    return true;
                }
            } catch (\Throwable) {
                // Try the next known executable.
            }
        }

        return class_exists(ZipArchive::class) && (function_exists('iconv') || function_exists('mb_convert_encoding'));
    }

    public function cleanup(string $convertedPath): void
    {
        $directory = dirname($convertedPath);
        if (str_contains(str_replace('\\', '/', $directory), '/storage/app/tmp/legacy-word-')) {
            $this->removeDirectory($directory);
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory.DIRECTORY_SEPARATOR.$entry;
            if (is_file($path) || is_link($path)) {
                @unlink($path);
            }
        }
        @rmdir($directory);
    }

    /** @return array<int, string> */
    private function executables(): array
    {
        return array_values(array_unique(array_filter([
            config('services.libreoffice.binary'),
            '/usr/bin/libreoffice',
            '/usr/bin/soffice',
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'libreoffice',
            'soffice',
        ])));
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Z]:\\\\/i', $path) === 1;
    }

    private function recoverTextAsDocx(string $sourcePath, string $outputPath): bool
    {
        if (! class_exists(ZipArchive::class)) {
            return false;
        }

        $contents = file_get_contents($sourcePath);
        if (! is_string($contents) || $contents === '') {
            return false;
        }

        $utf16 = function_exists('iconv')
            ? iconv('UTF-16LE', 'UTF-8//IGNORE', $contents)
            : mb_convert_encoding($contents, 'UTF-8', 'UTF-16LE');
        $utf16Paragraphs = is_string($utf16) ? $this->extractParagraphs($utf16) : [];

        $ansiParagraphs = [];
        if (preg_match_all('/[\x20-\x7E\xC0-\xFF\xA8\xB8]{12,}/', $contents, $matches)) {
            foreach ($matches[0] as $fragment) {
                $decoded = function_exists('iconv')
                    ? iconv('Windows-1251', 'UTF-8//IGNORE', $fragment)
                    : mb_convert_encoding($fragment, 'UTF-8', 'Windows-1251');
                if (is_string($decoded)) {
                    $ansiParagraphs = array_merge($ansiParagraphs, $this->extractParagraphs($decoded));
                }
            }
        }

        $utf16Length = array_sum(array_map('mb_strlen', $utf16Paragraphs));
        $ansiLength = array_sum(array_map('mb_strlen', $ansiParagraphs));
        $paragraphs = $utf16Length >= $ansiLength ? $utf16Paragraphs : $ansiParagraphs;
        if (count($paragraphs) < 3 || array_sum(array_map('mb_strlen', $paragraphs)) < 150) {
            return false;
        }

        $zip = new ZipArchive;
        if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>');
        $zip->addFromString('word/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:eastAsia="Times New Roman" w:cs="Times New Roman"/><w:sz w:val="24"/><w:szCs w:val="24"/></w:rPr></w:rPrDefault></w:docDefaults></w:styles>');
        $zip->addFromString('word/document.xml', $this->documentXml($paragraphs));
        $zip->close();

        return is_file($outputPath) && filesize($outputPath) > 0;
    }

    /** @return array<int, string> */
    private function extractParagraphs(string $text): array
    {
        preg_match_all('/[\x{0400}-\x{04FF}A-Za-z0-9№«»„“”"\'.,:;()\/\\\\%+\-–—\r\n\t ]{12,}/u', $text, $matches);
        $paragraphs = [];
        foreach ($matches[0] ?? [] as $fragment) {
            $fragment = preg_replace('/[\t ]+/u', ' ', $fragment) ?? $fragment;
            foreach (preg_split('/\R+/u', trim($fragment)) ?: [] as $paragraph) {
                $paragraph = trim($paragraph);
                if (mb_strlen($paragraph) < 8 || preg_match_all('/[\p{L}\p{N}]/u', $paragraph) < 5) {
                    continue;
                }
                if ($this->isWordMetadataParagraph($paragraph)) {
                    continue;
                }
                if (($paragraphs[array_key_last($paragraphs)] ?? null) !== $paragraph) {
                    $paragraphs[] = $paragraph;
                }
            }
        }

        return $paragraphs;
    }

    private function isWordMetadataParagraph(string $paragraph): bool
    {
        $normalized = mb_strtolower(trim($paragraph));

        return in_array($normalized, [
            'обычная таблица',
            'сетка таблицы',
            'текст выноски',
            'верхний колонтитул',
            'верхний колонтитул знак',
            'основной текст',
            'основной текст знак',
            'основной шрифт абзаца',
            'нижний колонтитул',
            'нижний колонтитул знак',
            'apple-style-span',
            'consplusnonformat',
            'js-extracted-address',
            'mail-message-map-nobreak',
            'worddocument',
            'summaryinformation',
            'documentsummaryinformation',
        ], true);
    }

    /** @param array<int, string> $paragraphs */
    private function documentXml(array $paragraphs): string
    {
        $body = '';
        foreach ($paragraphs as $paragraph) {
            $isHeading = mb_strlen($paragraph) < 100 && mb_strtoupper($paragraph) === $paragraph;
            $body .= '<w:p><w:pPr><w:spacing w:after="100" w:line="276" w:lineRule="auto"/>'
                .($isHeading ? '<w:jc w:val="center"/>' : '<w:jc w:val="both"/>').'</w:pPr><w:r><w:rPr>'
                .($isHeading ? '<w:b/>' : '').'<w:sz w:val="24"/><w:szCs w:val="24"/></w:rPr><w:t xml:space="preserve">'
                .htmlspecialchars($paragraph, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</w:t></w:r></w:p>';
        }
        $body .= '<w:p><w:r><w:t>{{SUPPLIER_NAME}}</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>УНП {{SUPPLIER_UNP}}</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>{{SUPPLIER_ADDRESS}}</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>IBAN {{SUPPLIER_IBAN}}</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>{{SUPPLIER_BANK}}, BIC {{SUPPLIER_BIC}}</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>Тел.: {{SUPPLIER_PHONE}}, Email: {{SUPPLIER_EMAIL}}</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>________________ {{SUPPLIER_DIRECTOR}}</w:t></w:r></w:p>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body
            .'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134"/></w:sectPr>'
            .'</w:body></w:document>';
    }
}
