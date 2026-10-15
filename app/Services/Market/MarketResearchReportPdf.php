<?php

namespace App\Services\Market;

use App\Models\MarketResearchReport;
use Dompdf\Dompdf;
use Dompdf\Options;

class MarketResearchReportPdf
{
    public function __construct(private readonly MarketResearchReportPresenter $presenter) {}

    public function render(MarketResearchReport $report): string
    {
        $data = $this->presenter->present($report);
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('pdf.market-research-report', [
            'report' => $report,
            ...$data,
        ])->render(), 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }
}
