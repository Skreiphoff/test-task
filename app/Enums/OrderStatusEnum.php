<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatusEnum: string
{
    case Created = 'created';
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
}
