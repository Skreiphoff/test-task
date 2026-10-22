<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Current state of a MarketHub order. `created_at` and `updated_at` are marketplace timestamps.
 * Money columns are decimal strings and must never be cast to float.
 */
class Order extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Shared by the JSON API and the admin pages: optional status and case-insensitive email filters,
     * newest first. `id` keeps the order stable for equal timestamps, so pages never overlap.
     *
     * @param Builder<Order> $query
     * @return Builder<Order>
     */
    public function scopeFiltered(Builder $query, ?string $status, ?string $email): Builder
    {
        return $query
            ->when($status, fn (Builder $q, string $s) => $q->where('status', $s))
            ->when($email, fn (Builder $q, string $e) => $q->whereRaw('LOWER(customer_email) = ?', [mb_strtolower(trim($e))]))
            ->orderByDesc('created_at')
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
