<?php

namespace App\Http\Controllers;

use App\Models\CustomerSelection;
use App\Models\User;
use App\Services\B2bCompanyAccess;
use App\Services\StaffCatalogWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class B2bSelectionDeskController extends Controller
{
    public static function canUse(?User $user): bool
    {
        return (bool) ($user?->is_active && (StaffCatalogWorkspace::allowed($user)
            || ($user->isB2B() && app(B2bCompanyAccess::class)->allows($user, 'orders.create'))));
    }

    private function authorizeSelection(Request $request, CustomerSelection $selection): void
    {
        $user = $request->user();
        abort_unless(self::canUse($user), 403);
        abort_unless((int) $selection->user_id === (int) $user->id, 404);
    }

    private function state(CustomerSelection $selection, Request $request): array
    {
        $state = app(StaffCatalogWorkspace::class)->state($selection, $request->user());
        $excluded = $selection->meta['desk_excluded_ids'] ?? [];
        $state['excluded'] = array_map('intval', $excluded);
        $state['note'] = $selection->note;
        $state['undo_available'] = ! empty($selection->meta['desk_undo']);
        $state['desk_url'] = route('b2b.selections.show', $selection);

        return $state;
    }

    public function show(Request $request, CustomerSelection $selection)
    {
        $this->authorizeSelection($request, $selection);
        $state = $this->state($selection, $request);
        $targets = CustomerSelection::where('user_id', $request->user()->id)->whereKeyNot($selection->id)->latest('updated_at')->get(['id', 'name', 'revision']);
        $showProcurement = $request->user()->isAdmin();
        $minimalLayout = true;

        return response()->view('pages.b2b.selection-desk', compact('selection', 'state', 'targets', 'showProcurement', 'minimalLayout'))->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, CustomerSelection $selection)
    {
        $this->authorizeSelection($request, $selection);
        $data = $request->validate([
            'action' => ['required', Rule::in(['quantity', 'select', 'details', 'copy', 'move', 'remove', 'undo'])],
            'revision' => ['required', 'integer', 'min:1'],
            'ids' => ['sometimes', 'array', 'max:2000'], 'ids.*' => ['integer', 'distinct'],
            'product_id' => ['required_if:action,quantity', 'integer'],
            'quantity' => ['required_if:action,quantity', 'integer', 'min:1', 'max:999'],
            'name' => ['required_if:action,details', 'nullable', 'string', 'max:160'],
            'note' => ['nullable', 'string', 'max:1000'],
            'target_id' => ['nullable', 'integer'],
            'target_revision' => ['required_with:target_id', 'nullable', 'integer', 'min:1'],
        ]);
        $target = null;
        DB::transaction(function () use ($request, $selection, $data, &$target) {
            // Always lock rows in the same order, including transfers between selections.
            $locked = CustomerSelection::where('user_id', $request->user()->id)
                ->whereIn('id', array_filter([$selection->id, $data['target_id'] ?? null]))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $source = $locked->get($selection->id);
            abort_unless($source && $source->revision === (int) $data['revision'], 409, 'Подбор изменён в другой вкладке. Обновите страницу.');
            $items = $source->items;
            $meta = $source->meta ?? [];
            $ids = array_map('intval', $data['ids'] ?? []);
            abort_if(array_diff($ids, array_map('intval', array_keys($items))) !== [], 422, 'В подборе уже нет одной из отмеченных позиций.');
            switch ($data['action']) {
                case 'quantity':
                    abort_unless(isset($items[$data['product_id']]), 404);
                    $items[$data['product_id']]['quantity'] = (int) $data['quantity'];
                    break;
                case 'select':
                    $meta['desk_excluded_ids'] = array_values(array_diff(array_map('intval', array_keys($items)), $ids));
                    break;
                case 'details':
                    $source->name = $data['name'];
                    $source->note = $data['note'] ?? null;
                    break;
                case 'remove':
                    abort_if($ids === [], 422, 'Отметьте позиции для удаления.');
                    $meta['desk_undo'] = array_intersect_key($items, array_flip($ids));
                    $items = array_diff_key($items, array_flip($ids));
                    break;
                case 'undo':
                    $restore = $meta['desk_undo'] ?? [];
                    abort_if(array_intersect_key($items, $restore) !== [], 409, 'Некоторые удалённые товары уже добавлены заново.');
                    abort_if(count($items) + count($restore) > 2000, 422, 'В подборе не может быть больше 2000 позиций.');
                    $items += $restore;
                    unset($meta['desk_undo']);
                    break;
                case 'copy':
                case 'move':
                    abort_if($ids === [], 422, 'Отметьте позиции для переноса или копирования.');
                    $chosen = array_intersect_key($items, array_flip($ids));
                    $calculationKeys = array_filter(array_column($chosen, 'pump_calculation_key'));
                    $calculations = array_intersect_key($meta['pump_calculations'] ?? [], array_flip($calculationKeys));
                    if (! empty($data['target_id'])) {
                        $target = $locked->get($data['target_id']);
                        abort_unless($target && $target->id !== $source->id, 404);
                        abort_unless($target->revision === (int) $data['target_revision'], 409, 'Подбор назначения изменён. Обновите страницу.');
                        abort_if(array_intersect_key($target->items, $chosen) !== [], 409, 'В выбранном подборе уже есть такой товар. Дубли с разными ценами не объединяются автоматически.');
                        abort_if(count($target->items) + count($chosen) > 2000, 422, 'В подборе назначения не может быть больше 2000 позиций.');
                        $targetMeta = $target->meta ?? [];
                        $targetMeta['pump_calculations'] = ($targetMeta['pump_calculations'] ?? []) + $calculations;
                        abort_if(count($targetMeta['pump_calculations']) > 20,422,'В подборе назначения больше 20 расчётов.');
                        $target->update(['items' => $target->items + $chosen, 'meta'=>$targetMeta, 'revision' => $target->revision + 1]);
                    } else {
                        abort_if(blank($data['name'] ?? null), 422, 'Укажите название нового подбора.');
                        $target = CustomerSelection::create(['user_id' => $request->user()->id, 'name' => $data['name'], 'items' => $chosen, 'revision' => 1,
                            'meta' => array_merge(($meta['staff_workspace'] ?? false) ? ['staff_workspace' => true] : [], $calculations ? ['pump_calculations'=>$calculations] : [])]);
                    }
                    if ($data['action'] === 'move') {
                        $items = array_diff_key($items, $chosen);
                    }
                    break;
            }
            $meta['desk_excluded_ids'] = array_values(array_intersect($meta['desk_excluded_ids'] ?? [], array_keys($items)));
            $source->fill(['items' => $items, 'meta' => $meta, 'revision' => $source->revision + 1])->save();
        });

        return response()->json(['state' => $this->state($selection->fresh(), $request), 'target' => $target ? ['id' => $target->id, 'name' => $target->name, 'revision' => $target->revision, 'url' => route('b2b.selections.show', $target)] : null])->header('Cache-Control', 'private, no-store');
    }

    public function export(Request $request, CustomerSelection $selection)
    {
        $this->authorizeSelection($request, $selection);
        $items = array_diff_key($selection->items, array_flip($selection->meta['desk_excluded_ids'] ?? []));
        $internal = $request->boolean('internal');
        abort_if($internal && ! $request->user()->isAdmin(), 403);
        if ($request->input('format') === 'print') {
            return response()->view('pages.selections.print', ['name' => $selection->name, 'note' => $selection->note, 'items' => $items, 'date' => $selection->updated_at, 'pumpCalculations' => $selection->meta['pump_calculations'] ?? []])->header('Cache-Control', 'private, no-store');
        }

        return response()->streamDownload(function () use ($selection, $items, $internal) {
            $book = new Spreadsheet;
            $sheet = $book->getActiveSheet();
            $sheet->setTitle('Подбор');
            $sheet->setCellValueExplicit('A1', $selection->name, DataType::TYPE_STRING);
            if (!empty($selection->meta['pump_calculations'])) $sheet->setCellValueExplicit('A2', collect($selection->meta['pump_calculations'])->pluck('result.summary')->implode(' | '), DataType::TYPE_STRING);
            $headers = ['Артикул', 'Название', 'Цена, BYN', 'Количество', 'Сумма, BYN', 'Комментарий'];
            if ($internal) {
                $headers = array_merge($headers, ['Поставщик', 'Входная, BYN', 'Маржа, BYN']);
            }
            $sheet->fromArray($headers, null, 'A3');
            $row = 4;
            foreach ($items as $item) {
                $sheet->setCellValueExplicit('A'.$row, (string) ($item['sku'] ?? ''), DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('B'.$row, $item['name'], DataType::TYPE_STRING);
                if ($item['price'] > 0) {
                    $sheet->setCellValue('C'.$row, (float) $item['price']);
                    $sheet->setCellValue('E'.$row, round($item['price'] * $item['quantity'], 2));
                } else {
                    $sheet->setCellValueExplicit('C'.$row, 'Уточняется', DataType::TYPE_STRING);
                }
                $sheet->setCellValue('D'.$row, (int) $item['quantity']);
                $sheet->setCellValueExplicit('F'.$row, $item['selection_note'] ?? '', DataType::TYPE_STRING);
                if ($internal) {
                    $purchase = $item['workspace_purchase'] ?? [];
                    $sheet->setCellValueExplicit('G'.$row, $purchase['supplier'] ?? '', DataType::TYPE_STRING);
                    if (isset($purchase['purchase_price'])) {
                        $sheet->setCellValue('H'.$row, $purchase['purchase_price']);
                        if ($item['price'] > 0) {
                            $sheet->setCellValue('I'.$row, round(($item['price'] - $purchase['purchase_price']) * $item['quantity'], 2));
                        }
                    }
                }
                $row++;
            }
            $sheet->freezePane('C4');
            $sheet->setAutoFilter('A3:'.($internal ? 'I' : 'F').max(3, $row - 1));
            $sheet->getColumnDimension('A')->setWidth(24);
            $sheet->getColumnDimension('B')->setWidth(65);
            $sheet->getColumnDimension('F')->setWidth(45);
            $sheet->getStyle('A3:'.($internal ? 'I' : 'F').'3')->getFont()->setBold(true);
            (new Xlsx($book))->save('php://output');
            $book->disconnectWorksheets();
        }, 'kotlov-selection-'.$selection->id.($internal ? '-internal' : '').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'private, no-store']);
    }
}
