<?php

namespace App\Services\B2bOrders;

use App\Filament\Resources\B2bOrders\B2bOrderResource;
use App\Mail\B2bOrderStageMail;
use App\Models\B2bOrder;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class B2bOrderNotifier
{
    public function created(B2bOrder $order): void
    {
        $recipients = $order->assigned_manager_id
            ? User::query()->whereKey($order->assigned_manager_id)->where('is_active', true)->get()
            : User::query()
                ->where('is_active', true)
                ->where(fn ($query) => $query->where('role', 'admin')->orWhere('can_manage_b2b_orders', true))
                ->get()
                ->filter(fn (User $user): bool => $user->canManageB2bOrders());

        if ($recipients->isEmpty()) {
            return;
        }

        try {
            Notification::make()
                ->title('Новый B2B-заказ '.$order->number)
                ->body($order->company_name.' · '.number_format((float) $order->total, 2, ',', ' ').' BYN'
                    .' · в наличии '.$order->items->filter->isConfirmed()->count().' из '.$order->items->count())
                ->icon('heroicon-o-building-office-2')
                ->iconColor('warning')
                ->actions([
                    Action::make('openB2bOrder')
                        ->label('Открыть B2B-заказ')
                        ->url(B2bOrderResource::getUrl('edit', ['record' => $order], panel: 'admin'))
                        ->markAsRead(),
                ])
                ->sendToDatabase($recipients);
        } catch (\Throwable $exception) {
            Log::error('B2B order manager notification failed.', [
                'b2b_order_id' => $order->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /** Bell notification for the assigned B2B manager (or every B2B manager). */
    public function manager(B2bOrder $order, string $title, string $body): void
    {
        $recipients = $order->assigned_manager_id
            ? User::query()->whereKey($order->assigned_manager_id)->where('is_active', true)->get()
            : User::query()->where('is_active', true)
                ->where(fn ($query) => $query->where('role', 'admin')->orWhere('can_manage_b2b_orders', true))
                ->get()->filter(fn (User $user): bool => $user->canManageB2bOrders());
        if ($recipients->isEmpty()) {
            return;
        }

        try {
            Notification::make()
                ->title($title.' · '.$order->number)
                ->body($body)
                ->icon('heroicon-o-building-office-2')
                ->actions([
                    Action::make('openB2bOrder')
                        ->label('Открыть B2B-заказ')
                        ->url(B2bOrderResource::getUrl('edit', ['record' => $order], panel: 'admin'))
                        ->markAsRead(),
                ])
                ->sendToDatabase($recipients);
        } catch (\Throwable $exception) {
            Log::error('B2B order manager notification failed.', [
                'b2b_order_id' => $order->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    public function client(B2bOrder $order, string $stage, ?string $message = null): void
    {
        $order->loadMissing(['items', 'client.user']);
        $recipient = mb_strtolower(trim((string) (
            $order->company_email ?: $order->contact_email ?: $order->client?->user?->email
        )));
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Mail::mailer('transactional')->to($recipient)->queue(new B2bOrderStageMail($order, $stage, $message));
        } catch (\Throwable $exception) {
            Log::error('B2B order client email failed.', [
                'b2b_order_id' => $order->id,
                'stage' => $stage,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
