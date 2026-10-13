<?php

namespace App\Http\Controllers;

use App\Models\CommercialDocument;
use App\Services\CommercialDocuments\CommercialDocumentPdf;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class CommercialDocumentPdfController extends Controller
{
    public function __invoke(CommercialDocument $commercialDocument, CommercialDocumentPdf $pdf): Response
    {
        abort_unless(auth()->user()?->isManager(), 403);
        $filename = ($commercialDocument->type === 'invoice' ? 'Счет-' : 'Коммерческое-предложение-').$commercialDocument->number.'.pdf';
        $fallback = ($commercialDocument->type === 'invoice' ? 'invoice-' : 'quote-').$commercialDocument->id.'.pdf';

        return response($pdf->render($commercialDocument), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => (new ResponseHeaderBag)->makeDisposition('inline', $filename, $fallback),
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
