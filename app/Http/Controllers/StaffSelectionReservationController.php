<?php

namespace App\Http\Controllers;

use App\Models\CustomerSelection;
use App\Services\B2bOrders\StaffSelectionReservation;
use App\Services\StaffCatalogWorkspace;
use Illuminate\Http\Request;

class StaffSelectionReservationController extends Controller
{
    public function store(Request $request, CustomerSelection $selection, StaffSelectionReservation $service)
    {
        abort_unless(StaffCatalogWorkspace::allowed($request->user()), 403);
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1'], 'b2b_client_id' => ['required', 'integer'], 'hold_days' => ['required', 'integer', 'min:1', 'max:14'], 'client_message' => ['nullable', 'string', 'max:1000']]);
        $order = $service->create($selection, $request->user(), $data);

        return to_route('b2b.selections.show', $selection)->with('success', 'Заявка '.$order->number.' опубликована в кабинете выбранной компании. Внутренний резерв создан.');
    }

    public function release(Request $request, CustomerSelection $selection, StaffSelectionReservation $service)
    {
        $service->release($selection, $request->user());

        return to_route('b2b.selections.show', $selection)->with('success', 'Заявка отменена, внутренний резерв снят.');
    }
}
