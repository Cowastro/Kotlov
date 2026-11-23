<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\VamExpressShipment;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VamExpressOrderLabelsController extends Controller
{
    public function __invoke(Request $request, int $order): Response
    {
        abort_unless($request->user()?->canAccessPanel(Filament::getPanel('admin')), 403);
        $record = OrderResource::getEloquentQuery()->findOrFail($order);
        abort_unless(OrderResource::canView($record), 403);

        return response()->view('filament.resources.vam-express.order-labels', [
            'order' => $record, 'shipments' => VamExpressShipment::where('order_id', $record->id)->get(),
        ], 200, ['Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex, nofollow', 'X-Frame-Options' => 'DENY']);
    }
}
