<?php

namespace App\Http\Controllers;

use App\Models\ProductPurchasePriceSource;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductPurchasePriceSourceAttachmentController extends Controller
{
    public function __invoke(ProductPurchasePriceSource $source): StreamedResponse
    {
        abort_unless(auth()->user()?->is_active && auth()->user()?->isManager(), 403);

        $path = (string) $source->attachment_path;
        abort_unless(str_starts_with($path, 'supplier-price-evidence/'), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, basename($path), [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
