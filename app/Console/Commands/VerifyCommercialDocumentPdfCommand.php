<?php

namespace App\Console\Commands;

use App\Models\CommercialDocument;
use App\Services\CommercialDocuments\CommercialDocumentPdf;
use Illuminate\Console\Command;
use Throwable;

class VerifyCommercialDocumentPdfCommand extends Command
{
    protected $signature = 'commercial-documents:verify-pdf {--id= : ID коммерческого документа}';

    protected $description = 'Безопасно проверить генерацию PDF коммерческого документа без вывода его содержимого';

    public function handle(CommercialDocumentPdf $renderer): int
    {
        $id = filter_var($this->option('id'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($id === false) {
            $this->error('pdf_status=invalid_id');

            return self::INVALID;
        }

        $document = CommercialDocument::query()->find($id);
        if (! $document) {
            $this->error('pdf_status=document_not_found');

            return self::FAILURE;
        }

        try {
            $pdf = $renderer->render($document);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('pdf_status=render_failed');
            $this->line('exception_class='.$exception::class);
            $this->line('failure_code='.self::safeFailureCode($exception));

            return self::FAILURE;
        }

        $valid = str_starts_with($pdf, '%PDF-') && strlen($pdf) > 10_000;
        $this->line('pdf_status='.($valid ? 'ok' : 'invalid_output'));
        $this->line('pdf_bytes='.strlen($pdf));

        return $valid ? self::SUCCESS : self::FAILURE;
    }

    public static function safeFailureCode(Throwable $exception): string
    {
        $message = $exception->getMessage();

        if (preg_match('/Class "([A-Za-z0-9_\\\\]+)" not found/', $message, $match) === 1) {
            return 'missing_class:'.str_replace('\\', '.', $match[1]);
        }

        if (preg_match('/Call to undefined function ([A-Za-z0-9_\\\\]+)\(\)/', $message, $match) === 1) {
            return 'missing_function:'.str_replace('\\', '.', $match[1]);
        }

        if (str_contains($message, 'Call to a member function')) {
            return 'invalid_object_value';
        }

        if (str_contains($message, 'Cannot access offset') || str_contains($message, 'must be of type array')) {
            return 'invalid_data_shape';
        }

        return 'unclassified_'.strtolower((new \ReflectionClass($exception))->getShortName());
    }
}
