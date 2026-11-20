<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The 1C price file built by integration:propose-priceless-prices: managers only, never a public path. */
class PriceProposalDownloadController extends Controller
{
    public function __invoke(string $file): StreamedResponse
    {
        abort_unless(auth()->user()?->is_active && auth()->user()?->isManager(), 403);
        $path = 'price-proposals/'.$file;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $file, ['Cache-Control' => 'private, no-store']);
    }
}
