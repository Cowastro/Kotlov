<?php

namespace App\Http\Controllers;

use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Models\CrmMailMessage;
use App\Services\CrmMail\CrmMailPdfTextExtractor;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class CrmMailPdfTextController extends Controller
{
    public function __invoke(
        CrmMailMessage $message,
        int $attachment,
        CrmMailPdfTextExtractor $extractor,
    ): View {
        abort_unless(auth()->user()?->is_active && auth()->user()?->isManager(), 403);

        try {
            $result = $extractor->extract($message, $attachment);
            $error = null;
        } catch (RuntimeException $exception) {
            $result = [
                'name' => 'PDF-вложение',
                'text' => '',
                'method' => 'failed',
            ];
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            Log::warning('CRM PDF recognition failed', [
                'message_id' => $message->id,
                'attachment' => $attachment,
                'exception' => $exception::class,
            ]);
            $result = [
                'name' => 'PDF-вложение',
                'text' => '',
                'method' => 'failed',
            ];
            $error = 'Не удалось прочитать PDF. Повторите попытку позже.';
        }

        return view('filament.crm-mail.pdf-text', [
            ...$result,
            'error' => $error,
            'threadUrl' => CrmMailThreadResource::getUrl('view', ['record' => $message->thread_id]),
            'downloadUrl' => route('admin.crm-mail-messages.attachments.download', [
                'message' => $message,
                'attachment' => $attachment,
            ]),
        ]);
    }
}
