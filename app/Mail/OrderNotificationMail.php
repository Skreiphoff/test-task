<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\NotificationKindEnum;
use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OrderNotificationMail extends Mailable
{
    public function __construct(
        public readonly Order $order,
        public readonly NotificationKindEnum $kind,
    ) {}

    public function envelope(): Envelope
    {
        $number = $this->order->number;

        return new Envelope(subject: match ($this->kind) {
            NotificationKindEnum::Paid => "Заказ №{$number} оплачен",
            NotificationKindEnum::Shipped => "Заказ №{$number} передан в доставку",
            NotificationKindEnum::Cancelled => "Заказ №{$number} отменён",
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
