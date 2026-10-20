<?php

namespace App\Console\Commands;

use App\Mail\OrderPlacedMail;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendOrderCustomerConfirmationCommand extends Command
{
    protected $signature = 'orders:send-customer-confirmation
        {number : Номер заказа}
        {--apply : Отправить письмо; без флага выполняется только проверка}';

    protected $description = 'Проверить и при необходимости повторно отправить подтверждение заказа покупателю';

    public function handle(): int
    {
        $order = Order::query()
            ->where('number', (string) $this->argument('number'))
            ->with(['items', 'user'])
            ->first();

        if (! $order) {
            $this->components->error('order_found=0');

            return self::FAILURE;
        }

        $recipient = mb_strtolower(trim((string) $order->user?->email));
        if (! $order->user_id || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->components->warn('order_found=1 registered_customer=0 account_email_valid=0 sent=0');

            return self::FAILURE;
        }

        if (! $this->option('apply')) {
            $this->components->info('order_found=1 registered_customer=1 account_email_valid=1 sent=0 mode=dry-run');

            return self::SUCCESS;
        }

        Mail::mailer('transactional')
            ->to($recipient)
            ->send(new OrderPlacedMail($order));

        $this->components->info('order_found=1 registered_customer=1 account_email_valid=1 sent=1 mode=apply');

        return self::SUCCESS;
    }
}
