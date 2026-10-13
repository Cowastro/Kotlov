<?php

namespace App\Http\Controllers;

use App\Models\CrmMailMessage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CrmMailAttachmentController extends Controller
{
    public function __invoke(CrmMailMessage $message, int $attachment): StreamedResponse
    {
        abort_unless(auth()->user()?->is_active && auth()->user()?->isManager(), 403);

        $file = collect($message->metadata['attachments'] ?? [])->values()->get($attachment);
        abort_unless(is_array($file), 404);

        $path = (string) ($file['path'] ?? '');
        abort_unless(str_starts_with($path, 'crm-mail-attachments/'), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download(
            $path,
            (string) ($file['name'] ?? basename($path)),
            [
                'Content-Type' => (string) ($file['mime_type'] ?? 'application/octet-stream'),
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
