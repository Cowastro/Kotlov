<?php

namespace App\Mail;

use App\Jobs\Middleware\SkipOutdatedOrderMail;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class OrderStatusUpdatedMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var array{eyebrow:string,title:string,message:string} */
    public readonly array $statusDetails;

    public readonly string $statusAtQueue;

    public function __construct(public readonly Order $order)
    {
        $this->statusAtQueue = $order->status;
        $this->statusDetails = match ($order->status) {
            'processing' => [
                'eyebrow' => 'ЗАКАЗ В ИСПОЛНЕНИИ',
                'title' => 'Комплектация',
                'message' => 'Комплектуем ваш заказ. Состояние товаров и согласованные сроки указаны ниже.',
            ],
            'shipped' => [
                'eyebrow' => $order->isOneCOrderShipped() ? 'ЗАКАЗ ОТГРУЖЕН' : ($order->delivery_type === 'pickup' ? 'ГОТОВ К САМОВЫВОЗУ' : 'ПЕРЕДАН В ДОСТАВКУ'),
                'title' => $order->customerStatusLabel(),
                'message' => $order->isOneCOrderShipped() ? 'Ваш заказ укомплектован и отгружен.' : ($order->delivery_type === 'pickup' ? 'Ваш заказ подготовлен к самовывозу. Согласуйте получение с магазином.' : 'Ваш заказ собран и передан в доставку.'),
            ],
            'delivered' => [
                'eyebrow' => 'ЗАКАЗ ДОСТАВЛЕН',
                'title' => 'Получен',
                'message' => 'Заказ отмечен как доставленный. Благодарим вас за покупку в KOTLOV.BY.',
            ],
            default => [
                'eyebrow' => 'СТАТУС ЗАКАЗА ОБНОВЛЁН',
                'title' => Order::STATUSES[$order->status] ?? $order->status,
                'message' => 'Статус вашего заказа обновлён.',
            ],
        };
    }

    public function middleware(): array
    {
        return [new SkipOutdatedOrderMail($this->order->id, [$this->statusAtQueue])];
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                (string) config('mail.transactional_from.address'),
                (string) config('mail.transactional_from.name'),
            ),
            subject: $this->statusDetails['title'].' — заказ '.$this->order->number.' — KOTLOV.BY',
            using: [function (Email $message): void {
                $logo = public_path('img/logo-black.png');
                if (is_file($logo)) {
                    $message->embedFromPath($logo, 'kotlov-logo', 'image/png');
                }
            }],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order-status-updated');
    }
}
