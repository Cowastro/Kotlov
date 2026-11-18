<?php

namespace App\Http\Controllers;

use App\Models\CustomerSelection;
use App\Models\SelectionStockAlert;
use App\Services\SelectionStockMonitor;
use Illuminate\Http\Request;

class RequestArrivalController extends Controller
{
    public function toggle(Request $request, CustomerSelection $selection, SelectionStockMonitor $monitor)
    {
        abort_unless(B2bSelectionDeskController::canUse($request->user()), 403);
        abort_unless((int) $selection->user_id === (int) $request->user()->id, 404);
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $monitor->toggle($selection, (bool) $data['enabled']);

        return back()->with('success', $data['enabled'] ? 'Слежение включено. Уведомления появятся в «Моих заявках».' : 'Уведомления для этой заявки выключены. Сама заявка сохранена.');
    }

    public function read(Request $request, SelectionStockAlert $alert)
    {
        abort_unless(B2bSelectionDeskController::canUse($request->user()), 403);
        abort_unless((int) $alert->watch->selection->user_id === (int) $request->user()->id, 404);
        if (! $alert->read_at) {
            $alert->update(['read_at' => now()]);
        }

        return back()->with('success', 'Уведомление прочитано. Товары и заявка сохранены.');
    }
}
