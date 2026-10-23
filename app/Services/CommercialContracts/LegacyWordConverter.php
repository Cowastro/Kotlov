<?php

namespace App\Services\CommercialContracts;

use RuntimeException;
use Symfony\Component\Process\Process;

class LegacyWordConverter
{
    public function convertToDocx(string $sourcePath): string
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
                    return $outputPath;
                }
            } catch (\Throwable) {
                // Try the next known executable.
            }
        }

        $this->removeDirectory($temporaryRoot);

        throw new RuntimeException('Старый файл DOC принят, но сервер не смог преобразовать его в DOCX. Обратитесь к администратору: требуется LibreOffice либо переменная LIBREOFFICE_BIN.');
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

        return false;
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
}
