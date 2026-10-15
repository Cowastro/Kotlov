<?php

namespace App\Http\Controllers;

use App\Models\MarketResearchReport;
use App\Services\Market\MarketResearchReportPdf;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class MarketResearchReportPdfController extends Controller
{
    public function __invoke(MarketResearchReport $marketResearchReport, MarketResearchReportPdf $pdf): Response
    {
        abort_unless(auth()->user()?->isManager(), 403);

        $sku = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) ($marketResearchReport->product?->sku ?: $marketResearchReport->id));

        return response($pdf->render($marketResearchReport), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => (new ResponseHeaderBag)->makeDisposition(
                'inline',
                'Анализ-рынка-'.$sku.'.pdf',
                'market-research-'.$sku.'.pdf',
            ),
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
