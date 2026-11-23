<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\VamExpress\VamExpressShipmentResource;
use App\Services\Shipping\VamExpressException;
use App\Services\Shipping\VamExpressLabels;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VamExpressLabelController extends Controller
{
    public function __invoke(Request $request, int $shipment, string $format, VamExpressLabels $labels): Response
    {
        abort_unless($request->user()?->canAccessPanel(Filament::getPanel('admin')) && VamExpressShipmentResource::canViewAny(), 403);
        abort_unless(isset(VamExpressLabels::FORMATS[$format]), 404);
        $record = VamExpressShipmentResource::getEloquentQuery()->findOrFail($shipment);
        if ($record->order) {
            abort_unless(OrderResource::canView($record->order), 403);
        }
        $nonce = base64_encode(random_bytes(16));
        $data = ['shipment' => $record, 'format' => $format, 'nonce' => $nonce, 'label' => null, 'message' => null];
        $status = 200;
        try {
            $data['label'] = $labels->get($record, $format);
        } catch (VamExpressException $e) {
            $data['message'] = $e->getMessage();
            $status = 503;
        }

        return response()->view('filament.resources.vam-express.label', $data, $status, [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'no-referrer',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; script-src 'nonce-{$nonce}'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'",
        ]);
    }
}
