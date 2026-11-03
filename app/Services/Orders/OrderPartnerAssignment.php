<?php

namespace App\Services\Orders;

use App\Filament\Resources\Orders\OrderResource;
use App\Mail\PartnerOrderOfferedMail;
use App\Models\B2bSavedCart;
use App\Models\Order;
use App\Models\PartnerBranch;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * The centre hands a site order to a partner's sales point. The partner accepts
 * it (its purchase basket is prepared from the order lines) or declines it,
 * and the order's manager sees every step in the order comments.
 */
class OrderPartnerAssignment
{
    public function assign(Order $order, PartnerBranch $branch, User $manager, ?string $note = null): Order
    {
        if (! $branch->isPublic()) {
            throw ValidationException::withMessages(['partner_branch_id' => 'Точка продаж скрыта или партнёр без B2B-доступа.']);
        }

        DB::transaction(function () use ($order, $branch, $manager, $note): void {
            $order->forceFill([
                'partner_branch_id' => $branch->id,
                'partner_status' => 'offered',
                'partner_assigned_at' => now(),
                'partner_responded_at' => null,
                'partner_note' => trim((string) $note) ?: null,
            ])->save();
            $this->comment($order, $manager, 'Заказ передан партнёру: '.$branch->name.' ('.$branch->city?->name.').'
                .(trim((string) $note) !== '' ? ' Комментарий: '.trim($note) : ''));
        });

        $this->mailPartner($order->refresh(), $branch);

        return $order;
    }

    public function accept(Order $order, User $partnerUser): B2bSavedCart
    {
        $this->assertOffered($order);

        return DB::transaction(function () use ($order, $partnerUser): B2bSavedCart {
            $order->forceFill(['partner_status' => 'accepted', 'partner_responded_at' => now()])->save();
            $order->loadMissing('items');
            $items = $order->items->filter(fn ($item) => $item->product_id)
                ->map(fn ($item): array => ['id' => (int) $item->product_id, 'quantity' => max(1, (int) $item->quantity)])
                ->values()->all();
            $cart = B2bSavedCart::query()->create([
                'user_id' => $partnerUser->id,
                'name' => 'Заказ клиента '.$order->number,
                'items' => $items,
                'items_count' => count($items),
                'total' => 0,
            ]);
            $this->comment($order, $partnerUser, 'Партнёр '.$order->partnerBranch?->name.' принял заказ и готовит закупку.');
            $this->notifyManager($order, 'Партнёр принял заказ', $order->partnerBranch?->name.' принял заказ клиента.');

            return $cart;
        });
    }

    public function decline(Order $order, User $partnerUser, string $reason): Order
    {
        $this->assertOffered($order);
        DB::transaction(function () use ($order, $partnerUser, $reason): void {
            $order->forceFill(['partner_status' => 'declined', 'partner_responded_at' => now(), 'partner_note' => $reason])->save();
            $this->comment($order, $partnerUser, 'Партнёр '.$order->partnerBranch?->name.' отказался от заказа: '.$reason);
        });
        $this->notifyManager($order, 'Партнёр отказался от заказа', $order->partnerBranch?->name.': '.$reason);

        return $order;
    }

    private function assertOffered(Order $order): void
    {
        if ($order->partner_status !== 'offered') {
            throw ValidationException::withMessages(['order' => 'Заказ уже обработан.']);
        }
    }

    private function comment(Order $order, User $author, string $body): void
    {
        $order->managerComments()->create(['user_id' => $author->id, 'author_name' => $author->name, 'body' => $body]);
    }

    private function mailPartner(Order $order, PartnerBranch $branch): void
    {
        $client = $branch->client;
        $recipient = mb_strtolower(trim((string) ($branch->email ?: $client?->contact_email ?: $client?->user?->email)));
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Mail::mailer('transactional')->to($recipient)->queue(new PartnerOrderOfferedMail($order, $branch));
        } catch (\Throwable $exception) {
            Log::error('Partner order offer email failed.', ['order_id' => $order->id, 'exception' => $exception->getMessage()]);
        }
    }

    private function notifyManager(Order $order, string $title, string $body): void
    {
        $manager = User::query()->whereKey($order->manager_id ?: $order->assigned_to)->where('is_active', true)->first();
        if (! $manager) {
            return;
        }

        try {
            Notification::make()->title($title.' · '.$order->number)->body($body)
                ->actions([Action::make('open')->label('Открыть заказ')->url(OrderResource::getUrl('view', ['record' => $order], panel: 'admin'))->markAsRead()])
                ->sendToDatabase($manager);
        } catch (\Throwable $exception) {
            Log::error('Partner order manager notification failed.', ['order_id' => $order->id, 'exception' => $exception->getMessage()]);
        }
    }
}
