<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationKindEnum: string
{
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';

    public static function forStatus(OrderStatusEnum $status): ?self
    {
        return match ($status) {
            OrderStatusEnum::Paid => self::Paid,
            OrderStatusEnum::Shipped => self::Shipped,
            OrderStatusEnum::Cancelled => self::Cancelled,
            default => null,
        };
    }

    /**
     * Statuses in which the email is still true: "paid" stays valid after shipping, never after a cancellation.
     *
     * @return list<OrderStatusEnum>
     */
    public function validStatuses(): array
    {
        return match ($this) {
            self::Paid => [OrderStatusEnum::Paid, OrderStatusEnum::Shipped, OrderStatusEnum::Delivered],
            self::Shipped => [OrderStatusEnum::Shipped, OrderStatusEnum::Delivered],
            self::Cancelled => [OrderStatusEnum::Cancelled],
        };
    }
}
