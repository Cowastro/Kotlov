<?php

namespace App\Mail;

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

    public function __construct(public readonly Order $order)
    {
        $this->statusDetails = match ($order->status) {
            'processing' => [
                'eyebrow' => 'ЗАКАЗ В ИСПОЛНЕНИИ',
                'title' => 'Заказ передан в исполнение',
                'message' => 'Мы начали подготовку вашего заказа. Следующим этапом будет передача в доставку или подготовка к выдаче.',
            ],
            'shipped' => [
                'eyebrow' => 'ЗАКАЗ ПЕРЕДАН В ДОСТАВКУ',
                'title' => 'Заказ передан в доставку',
                'message' => 'Ваш заказ собран и передан в доставку или подготовлен к выдаче. Менеджер свяжется с вами при необходимости.',
            ],
            'delivered' => [
                'eyebrow' => 'ЗАКАЗ ДОСТАВЛЕН',
                'title' => 'Заказ доставлен',
                'message' => 'Заказ отмечен как доставленный. Благодарим вас за покупку в KOTLOV.BY.',
            ],
            default => [
                'eyebrow' => 'СТАТУС ЗАКАЗА ОБНОВЛЁН',
                'title' => Order::STATUSES[$order->status] ?? $order->status,
                'message' => 'Статус вашего заказа обновлён.',
            ],
        };
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
