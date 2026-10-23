<?php

declare(strict_types=1);

namespace App\Enums;

enum MarketplaceEventTypeEnum: string
{
    case OrderCreated = 'order.created';
    case OrderUpdated = 'order.updated';
    case OrderPaid = 'order.paid';
    case OrderShipped = 'order.shipped';
    case OrderDelivered = 'order.delivered';
    case OrderCancelled = 'order.cancelled';
}
