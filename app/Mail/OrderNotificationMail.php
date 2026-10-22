<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\NotificationKind;
use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OrderNotificationMail extends Mailable
{
    public function __construct(
        public readonly Order $order,
        public readonly NotificationKind $kind,
    ) {}

    public function envelope(): Envelope
    {
        $number = $this->order->number;

        return new Envelope(subject: match ($this->kind) {
            NotificationKind::Paid => "Заказ №{$number} оплачен",
            NotificationKind::Shipped => "Заказ №{$number} передан в доставку",
            NotificationKind::Cancelled => "Заказ №{$number} отменён",
        });
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.order-'.$this->kind->value,
            with: ['order' => $this->order],
        );
    }
}
