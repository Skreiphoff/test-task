<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationKind: string
{
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';

    /**
     * The kind of email that a transition into the given status triggers, if any.
     */
    public static function forStatus(OrderStatus $status): ?self
    {
        return match ($status) {
            OrderStatus::Paid => self::Paid,
            OrderStatus::Shipped => self::Shipped,
            OrderStatus::Cancelled => self::Cancelled,
            default => null,
        };
    }

    /**
     * Statuses in which the email is still truthful for the customer.
     * A "paid" email stays valid once the order has been shipped, but never after a cancellation.
     *
     * @return list<OrderStatus>
     */
    public function validStatuses(): array
    {
        return match ($this) {
            self::Paid => [OrderStatus::Paid, OrderStatus::Shipped, OrderStatus::Delivered],
            self::Shipped => [OrderStatus::Shipped, OrderStatus::Delivered],
            self::Cancelled => [OrderStatus::Cancelled],
        };
    }
}
