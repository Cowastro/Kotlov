<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Orders\OrderResource;
use App\Services\Shipping\Evropochta;
use App\Services\Shipping\EvropochtaCheckout;
use App\Services\Shipping\EvropochtaShipmentException;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EvropochtaOrderLabelController extends Controller
{
    public function __invoke(Request $request, int $order, string $format, Evropochta $carrier): Response
    {
        abort_unless($request->user()?->canAccessPanel(Filament::getPanel('admin')), 403);

        $record = OrderResource::getEloquentQuery()->findOrFail($order);
        abort_unless(OrderResource::canView($record), 403);
        abort_unless($record->delivery_type === EvropochtaCheckout::METHOD && filled($record->evropochta_tracking_number), 404);

        $number = $record->evropochta_tracking_number;
        $headers = ['Cache-Control' => 'private, no-store'];

        try {
            $pdf = $carrier->shipmentLabel($number, $format);
        } catch (EvropochtaShipmentException $error) {
            return response()->view('filament.resources.orders.evropochta-label-pending', [
                'message' => $error->getMessage(),
                'number' => $number,
                'format' => Evropochta::LABEL_FORMATS[$format],
                'retryUrl' => $request->url(),
                'orderUrl' => OrderResource::getUrl('view', ['record' => $record], panel: 'admin'),
            ], 503, $headers);
        }

        return response($pdf, 200, $headers + [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=evropochta-{$number}-{$format}.pdf",
        ]);
    }
}
