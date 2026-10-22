<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\DTO\ListOrdersQueryDto;
use App\Enums\OrderStatusEnum;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

/** @extends Builder<Order> */
class OrderBuilder extends Builder
{
    public function filtered(ListOrdersQueryDto $filters): static
    {
        return $this
            ->when($filters->status, fn (self $q, OrderStatusEnum $status) => $q->where('status', $status))
            ->when($filters->email, fn (self $q, string $email) => $q->whereRaw('LOWER(customer_email) = ?', [$email]))
            ->orderByDesc('created_at')
            // Tie-breaker: pages must not overlap for equal timestamps.
            ->orderByDesc('id');
    }
}
