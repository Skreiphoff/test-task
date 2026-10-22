<?php

declare(strict_types=1);

namespace App\Models;

use App\DTO\ListOrdersQueryDto;
use App\Enums\OrderStatusEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** `created_at` and `updated_at` are marketplace timestamps, not local row timestamps. */
class Order extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => OrderStatusEnum::class,
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeFiltered(Builder $query, ListOrdersQueryDto $filters): Builder
    {
        return $query
            ->when($filters->status, fn (Builder $q, OrderStatusEnum $status) => $q->where('status', $status))
            ->when($filters->email, fn (Builder $q, string $email) => $q->whereRaw('LOWER(customer_email) = ?', [$email]))
            ->orderByDesc('created_at')
            // Tie-breaker: pages must not overlap for equal timestamps.
            ->orderByDesc('id');
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('position');
    }

    /** @return HasMany<OrderStatusHistory, $this> */
    public function history(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('at')->orderBy('id');
    }

    public function hasShipment(): bool
    {
        return $this->shipment_carrier !== null
            || $this->shipment_tracking_number !== null
            || $this->shipment_tracking_url !== null;
    }
}
