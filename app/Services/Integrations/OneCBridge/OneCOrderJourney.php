<?php

namespace App\Services\Integrations\OneCBridge;

use App\Models\Order;
use App\Services\Orders\OrderDeliveryPreparation;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

/**
 * What an order went through in 1C and what comes next, step by step, for the manager: items linked with 1C, the
 * customer order, the supplier orders, posting by the accountant, stages after 1C. Each step says what was done and
 * when, the first open one says which button to press.
 */
class OneCOrderJourney
{
    public const DONE = 'done';

    public const WAIT = 'wait';

    public const TODO = 'todo';

    public const LATER = 'later';

    public function __construct(
        private readonly OneCOrderExport $orders,
        private readonly OneCOrderMatcher $matcher,
        private readonly OneCSupplierOrderExport $suppliers,
        private readonly OneCAgentTasks $tasks,
    ) {}

    /** @return list<array{title: string, state: string, detail: string, hint: ?string}> */
    public function steps(Order $order): array
    {
        $steps = [];

        // 1. Every line has its 1C item.
        $lines = $this->orders->lines($order);
        $linked = collect($lines)->whereNotNull('key')->count();
        $creating = collect($lines)->filter(fn (array $l): bool => $l['key'] === null && $l['item']->product
            && ($this->tasks->activeFor($l['item']->product, ItemTaskHandler::TYPE) || $this->tasks->activeFor($l['item']->product, ODataReadTaskHandler::TYPE)))->count();
        $itemsOk = $lines !== [] && $linked === count($lines);
        $steps[] = ['title' => 'Товары связаны с 1С', 'state' => $itemsOk ? self::DONE : ($creating ? self::WAIT : self::TODO),
            'detail' => $linked.' из '.count($lines).($creating ? ', создаются в 1С: '.$creating : ''),
            'hint' => $itemsOk ? null : ($creating ? 'Подождите 5 минут: агент создаёт товары в 1С.' : 'Нажмите ⊕ у строк «нет в 1С» в «Товары заказа»: привяжите товар 1С или создайте его.')];

        // 2. The customer order in 1C.
        $exported = filled($order->onec_order_guid) && ! $order->onec_deleted;
        $waiting = $this->tasks->activeFor($order, OrderTaskHandler::TYPE) !== null;
        $found = $exported || $waiting ? null : $this->matcher->found($order);
        $preparationBlocker = app(OrderDeliveryPreparation::class)->blocker($order);
        $steps[] = match (true) {
            $exported => ['title' => 'Заказ клиента в 1С', 'state' => self::DONE,
                'detail' => $order->onec_order_number.($order->onec_profile_id ? ' · создан с сайта' : ' · связан с заведённым вручную').self::at($order->onec_exported_at), 'hint' => null],
            $waiting => ['title' => 'Заказ клиента в 1С', 'state' => self::WAIT, 'detail' => 'отправляется', 'hint' => 'Подождите 5 минут: агент создаёт заказ клиента в 1С.'],
            $found !== null => ['title' => 'Заказ клиента в 1С', 'state' => self::TODO, 'detail' => 'найден в 1С: '.$found['ref']->description,
                'hint' => 'Нажмите «Найден в 1С: '.$found['ref']->description.'» и свяжите, если это тот же заказ.'],
            default => ['title' => 'Заказ клиента в 1С', 'state' => $itemsOk ? self::TODO : self::LATER, 'detail' => 'не создан',
                'hint' => $preparationBlocker ?: ($itemsOk ? 'Нажмите «Создать заказ в 1С».' : null)],
        };

        // 3. Supplier orders, when lines are bought from suppliers.
        // A delivered order or a closed 1C order has nothing left to buy.
        $finished = $order->status === 'delivered' || ($exported && $order->onec_doc_status === 'Закрыт');
        $groups = $finished ? [] : $this->suppliers->groups($order);
        if ($groups !== []) {
            $parts = [];
            $sent = 0;
            $queued = 0;
            foreach ($groups as $group) {
                $record = $group['record'];
                if (OneCSupplierOrderExport::sent($record)) {
                    $sent++;
                    $queued += $record->onec_order_number ? 0 : 1;
                    $parts[] = $group['supplier']->name.': '.($record->onec_order_number ? $record->onec_order_number.self::at($record->updated_at) : 'отправляется');
                } else {
                    $parts[] = $group['supplier']->name.': не заказан';
                }
            }
            $all = $sent === count($groups);
            $steps[] = ['title' => 'Заказы поставщикам в 1С', 'state' => $all ? ($queued ? self::WAIT : self::DONE) : ($itemsOk ? self::TODO : self::LATER),
                'detail' => implode('; ', $parts),
                'hint' => $all ? ($queued ? 'Подождите 5 минут: агент создаёт заказ поставщику.' : null)
                    : ($itemsOk ? 'Нажмите «Поставщикам в 1С» — закупка у поставщика ('.($sent).' из '.count($groups).').' : null)];
        }

        // 4. The accountant posts the documents in 1C.
        $documents = collect([$exported ? ['name' => 'заказ клиента '.$order->onec_order_number, 'posted' => (bool) $order->onec_posted, 'checked' => $order->onec_checked_at] : null])
            ->concat(collect($groups)->map(fn (array $g) => $g['record']?->onec_order_number && ! $g['record']->onec_deleted
                ? ['name' => 'заказ поставщику '.$g['record']->onec_order_number, 'posted' => (bool) $g['record']->onec_posted, 'checked' => $g['record']->onec_checked_at] : null))
            ->filter()->values();
        $unposted = $documents->reject(fn (array $d): bool => $d['posted']);
        $steps[] = ['title' => 'Проведено в 1С', 'state' => $documents->isEmpty() ? self::LATER : ($unposted->isEmpty() ? self::DONE : self::TODO),
            'detail' => $documents->isEmpty() ? 'документов ещё нет'
                : $documents->map(fn (array $d): string => $d['name'].' — '.($d['posted'] ? 'проведён' : 'не проведён'))->implode('; '),
            'hint' => $unposted->isNotEmpty() ? 'Проведите в 1С: '.$unposted->pluck('name')->implode(', ').'. Сайт сверяет каждые 30 минут.' : null];

        // 5. The stages follow 1C.
        $after = in_array($order->status, ['processing', 'shipped', 'delivered'], true);
        $steps[] = ['title' => 'Этапы по 1С', 'state' => $order->status === 'delivered' ? self::DONE : ($after ? self::WAIT : self::LATER),
            'detail' => (Order::STATUSES[$order->status] ?? $order->status).($exported ? ' · «'.mb_strtolower(OneCOrderProfiles::STATUSES[$order->onec_doc_status] ?? (string) $order->onec_doc_status).'» в 1С' : ''),
            'hint' => null];

        return $steps;
    }

    /** The first thing to do, or null when nothing waits for the manager. */
    public function next(Order $order): ?string
    {
        if ($order->status === 'processing' && blank($order->onec_order_guid) && ! $this->tasks->activeFor($order, OrderTaskHandler::TYPE)
            && ($reason = app(OrderDeliveryPreparation::class)->destinationBlocker($order))) {
            return $reason;
        }
        if (OneCPostingReminder::needed($order)) {
            return 'Откройте заказ '.($order->onec_order_number ?: $order->number).' в 1С, проверьте товары, действия строк и доставку и проведите его. Затем нажмите «Проверить проведение» в кнопке «Провести в 1С». Обеспечение и отгрузку дальше ведут в 1С.';
        }
        foreach ($this->steps($order) as $step) {
            if ($step['hint'] && in_array($step['state'], [self::TODO, self::WAIT], true)) {
                return $step['hint'];
            }
        }

        return null;
    }

    public function render(Order $order): HtmlString
    {
        $steps = $this->steps($order);
        $next = $this->next($order);
        $icons = [self::DONE => ['✓', '#16a34a'], self::WAIT => ['…', '#2563eb'], self::TODO => ['!', '#d97706'], self::LATER => ['·', '#9ca3af']];
        $html = $next ? '<div style="margin-bottom:10px;padding:8px 12px;border-radius:8px;background:rgba(217,119,6,.12);border-left:3px solid #d97706">'
            .'<b>Дальше:</b> '.e($next).'</div>' : '<div style="margin-bottom:10px;color:#16a34a"><b>По 1С всё сделано.</b></div>';
        $html .= '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px">';
        foreach ($steps as $i => $step) {
            [$icon, $color] = $icons[$step['state']];
            $html .= '<div style="padding:8px 10px;border-radius:8px;border:1px solid '.($step['state'] === self::TODO ? $color : 'rgba(127,127,127,.25)').'">'
                .'<div style="font-weight:600"><span style="display:inline-block;width:18px;height:18px;line-height:18px;text-align:center;border-radius:9px;background:'.$color
                .';color:#fff;font-size:12px;margin-right:6px">'.$icon.'</span>'.($i + 1).'. '.e($step['title']).'</div>'
                .'<div style="font-size:12px;color:#9ca3af;margin-top:4px">'.e($step['detail']).'</div></div>';
        }

        return new HtmlString($html.'</div>');
    }

    private static function at(?Carbon $at): string
    {
        return $at ? ' · '.$at->timezone('Europe/Minsk')->format('d.m H:i') : '';
    }
}
