<?php

namespace App\Services\Orders;

use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Filament\Resources\DealByOrders\DealByOrderResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\CrmMailThread;
use App\Models\SupplierOrderRequest;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SupplierReservationDesk
{
    public const STATES = [
        'draft' => 'Черновик', 'waiting' => 'Ждём подтверждения',
        'confirmed' => 'Подтверждён поставщиком', 'rejected' => 'Отказ поставщика',
        'fulfilled' => 'Исполнен', 'cancelled' => 'Отменён', 'closed' => 'Закрыт без подтверждения',
    ];

    public function query(User $user, string $search = ''): Builder
    {
        abort_unless($user->is_active && $user->isManager(), 403);
        $orders = OrderResource::getEloquentQuery()->select('orders.id')
            ->union(DealByOrderResource::getEloquentQuery()->select('orders.id'));
        $requests = SupplierOrderRequest::query()->whereIn('order_id', $orders);
        $products = CrmMailThread::query()->visibleTo($user)
            ->whereNull('supplier_order_request_id')->whereNotNull('product_id')->whereNotNull('supplier_id')
            ->whereHas('messages', fn ($q) => $q->where('metadata->source', 'supplier_product_reserve'));

        if (($search = trim(mb_substr($search, 0, 150))) !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $requests->where(fn ($q) => $q->where('number', 'like', $like)
                ->orWhereHas('order', fn ($q) => $q->where('number', 'like', $like))
                ->orWhereHas('supplier', fn ($q) => $q->where('name', 'like', $like))
                ->orWhereHas('items', fn ($q) => $q->where('product_name', 'like', $like)->orWhere('product_sku', 'like', $like)));
            $products->where(fn ($q) => $q->whereHas('product', fn ($q) => $q->where('name', 'like', $like)->orWhere('sku', 'like', $like))
                ->orWhereHas('supplier', fn ($q) => $q->where('name', 'like', $like))
                ->orWhereHas('order', fn ($q) => $q->where('number', 'like', $like))
                ->orWhereHas('messages', fn ($q) => $q->where('metadata->source', 'supplier_product_reserve')->where('metadata->supplier_article', 'like', $like)));
        }

        $requests->selectRaw("id, 'request' as kind, supplier_id, created_by as manager_id, updated_at as sort_at,
            CASE status WHEN 'draft' THEN 'draft' WHEN 'sent' THEN 'waiting' WHEN 'acknowledged' THEN 'confirmed'
            WHEN 'rejected' THEN 'rejected' WHEN 'fulfilled' THEN 'fulfilled' ELSE 'cancelled' END as state");
        $products->selectRaw("id, 'product' as kind, supplier_id, assigned_user_id as manager_id, updated_at as sort_at,
            CASE WHEN status = 'draft' THEN 'draft' WHEN status IN ('closed','spam') THEN 'closed' ELSE 'waiting' END as state");

        return DB::query()->fromSub($requests->toBase()->unionAll($products->toBase()), 'reserves');
    }

    public function rows(Collection $index, User $user): Collection
    {
        $requests = SupplierOrderRequest::query()->whereIn('id', $index->where('kind', 'request')->pluck('id'))
            ->with(['items', 'supplier:id,name', 'creator:id,name', 'order.dealByInbox',
                'mailDraft' => fn ($q) => $q->visibleTo($user)])->get()->keyBy('id');
        $products = CrmMailThread::query()->visibleTo($user)->whereIn('id', $index->where('kind', 'product')->pluck('id'))
            ->with(['product:id,name,sku', 'supplier:id,name', 'assignedUser:id,name', 'order.dealByInbox',
                'messages' => fn ($q) => $q->select('id', 'thread_id', 'metadata')->where('metadata->source', 'supplier_product_reserve')->orderByDesc('id')])
            ->get()->keyBy('id');

        return $index->map(function ($entry) use ($requests, $products): array {
            $request = $entry->kind === 'request' ? $requests->get($entry->id) : null;
            $thread = $request?->mailDraft ?? ($entry->kind === 'product' ? $products->get($entry->id) : null);
            $order = $request?->order ?? $thread?->order;
            $metadata = $request ? [] : ($thread?->messages->first()?->metadata ?? []);
            $items = $request ? $request->items->map(fn ($item) => [
                'name' => $item->product_name, 'sku' => $item->product_sku, 'quantity' => $item->quantity,
                'price' => $item->purchase_price, 'total' => $item->purchase_total,
            ])->all() : [[
                'name' => $thread?->product?->name ?? 'Товар не найден', 'sku' => $thread?->product?->sku,
                'quantity' => $metadata['quantity'] ?? null, 'price' => $metadata['purchase_price'] ?? null,
                'total' => isset($metadata['purchase_price'], $metadata['quantity']) ? round($metadata['purchase_price'] * $metadata['quantity'], 2) : null,
            ]];
            $closedMail = $request && $entry->state === 'waiting' && $thread?->status === 'closed';

            return [
                'key' => $entry->kind.'-'.$entry->id, 'number' => $request?->number ?? 'Резерв товара №'.$entry->id,
                'supplier' => $request?->supplier?->name ?? $request?->supplier_name ?? $thread?->supplier?->name ?? 'Поставщик не указан',
                'state' => $entry->state, 'label' => self::STATES[$entry->state],
                'request_id' => $request?->id,
                'can_respond' => $request?->status === 'sent' && $request?->transfer_mode === 'email',
                'color' => match ($entry->state) {
                    'confirmed', 'fulfilled' => 'success', 'waiting' => 'warning', 'rejected' => 'danger', default => 'gray'
                },
                'items' => $items, 'quantity' => ! $items || collect($items)->contains(fn ($i) => $i['quantity'] === null) ? null : collect($items)->sum('quantity'),
                'total' => ! $items || collect($items)->contains(fn ($i) => $i['total'] === null) ? null : collect($items)->sum('total'),
                'manager' => $request?->creator?->name ?? $thread?->assignedUser?->name ?? 'Не назначен',
                'updated' => $request?->updated_at ?? $thread?->updated_at,
                'control_at' => $thread?->next_follow_up_at, 'overdue' => $thread?->next_follow_up_at?->isPast() && in_array($entry->state, ['draft', 'waiting']),
                'unread' => $thread?->unread_count ?? 0, 'note' => $request?->supplier_response_note ?? $thread?->manager_note,
                'next' => $closedMail ? 'Переписка закрыта, но подтверждение резерва не зафиксировано.' : match ($entry->state) {
                    'draft' => 'Проверьте состав и отправьте запрос поставщику.', 'waiting' => 'Получите подтверждение количества и срока у поставщика.',
                    'confirmed' => 'Резерв подтверждён. Уточните срок хранения и организуйте получение.',
                    'rejected' => 'Подберите другого поставщика или согласуйте замену.', default => 'Запись в архиве.',
                },
                'order_number' => $order?->number,
                'order_url' => $order && OrderResource::canView($order) ? ($order->dealByInbox
                    ? DealByOrderResource::getUrl('view', ['record' => $order]) : OrderResource::getUrl('view', ['record' => $order])) : null,
                'mail_url' => $thread && CrmMailThreadResource::canView($thread) ? CrmMailThreadResource::getUrl('view', ['record' => $thread]) : null,
            ];
        });
    }
}
