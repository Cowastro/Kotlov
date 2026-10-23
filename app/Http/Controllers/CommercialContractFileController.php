<?php

namespace App\Http\Controllers;

use App\Models\CommercialContract;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class CommercialContractFileController extends Controller
{
    public function pdf(CommercialContract $commercialContract): BinaryFileResponse
    {
        abort_unless(auth()->user()?->isManager(), 403);
        abort_unless($commercialContract->generated_pdf_path
            && Storage::disk('local')->exists($commercialContract->generated_pdf_path), 404);

        return response()->file(Storage::disk('local')->path($commercialContract->generated_pdf_path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => (new ResponseHeaderBag)->makeDisposition(
                'inline',
                'Договор-'.$commercialContract->number.'.pdf',
                'contract-'.$commercialContract->id.'.pdf',
            ),
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function docx(CommercialContract $commercialContract): BinaryFileResponse
    {
        abort_unless(auth()->user()?->isManager(), 403);
        abort_unless($commercialContract->generated_docx_path
            && Storage::disk('local')->exists($commercialContract->generated_docx_path), 404);

        return Storage::disk('local')->download(
            $commercialContract->generated_docx_path,
            'Договор-'.$commercialContract->number.'.docx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        );
    }

    public function source(CommercialContract $commercialContract): BinaryFileResponse
    {
        abort_unless(auth()->user()?->isManager(), 403);
        abort_unless($commercialContract->source_template_path
            && Storage::disk('local')->exists($commercialContract->source_template_path), 404);

        return Storage::disk('local')->download(
            $commercialContract->source_template_path,
            'Исходная-форма-'.$commercialContract->number.'.docx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        );
    }
}
