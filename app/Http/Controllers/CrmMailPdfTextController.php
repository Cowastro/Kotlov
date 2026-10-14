<?php

namespace App\Http\Controllers;

use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Models\CrmMailMessage;
use App\Services\CrmMail\CrmMailPdfTextExtractor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            $browserFallback = false;
        } catch (RuntimeException $exception) {
            $result = [
                'name' => 'PDF-вложение',
                'text' => '',
                'method' => 'failed',
            ];
            $error = $exception->getMessage();
            $browserFallback = str_contains($error, 'Poppler');
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
            $browserFallback = false;
        }

        return view('filament.crm-mail.pdf-text', [
            ...$result,
            'error' => $error,
            'browserFallback' => $browserFallback,
            'recognizeUrl' => route('admin.crm-mail-messages.attachments.recognize-pdf', [
                'message' => $message,
                'attachment' => $attachment,
            ]),
            'threadUrl' => CrmMailThreadResource::getUrl('view', ['record' => $message->thread_id]),
            'downloadUrl' => route('admin.crm-mail-messages.attachments.download', [
                'message' => $message,
                'attachment' => $attachment,
            ]),
        ]);
    }

    public function recognizeImages(
        Request $request,
        CrmMailMessage $message,
        int $attachment,
        CrmMailPdfTextExtractor $extractor,
    ): JsonResponse {
        abort_unless(auth()->user()?->is_active && auth()->user()?->isManager(), 403);

        $validated = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:8'],
            'images.*' => ['required', 'string', 'max:8000000'],
        ]);

        try {
            return response()->json($extractor->extractRenderedImages($message, $attachment, $validated['images']));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            Log::warning('CRM browser PDF recognition failed', [
                'message_id' => $message->id,
                'attachment' => $attachment,
                'exception' => $exception::class,
            ]);

            return response()->json(['message' => 'Не удалось прочитать PDF. Повторите попытку позже.'], 500);
        }
    }
}
