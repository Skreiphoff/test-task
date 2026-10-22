<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatusEnum;
use App\Models\Builders\OrderBuilder;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * `created_at` and `updated_at` are marketplace timestamps, not local row timestamps.
 *
 * @property string $id
 * @property string $number
 * @property OrderStatusEnum $status
 * @property string $currency
 * @property string $customer_email
 * @property string $customer_name
 * @property ?string $customer_phone
 * @property string $delivery_price
 * @property string $discount
 * @property string $total
 * @property ?string $shipment_carrier
 * @property ?string $shipment_tracking_number
 * @property ?string $shipment_tracking_url
 * @property ?CarbonInterface $paid_at
 * @property ?CarbonInterface $cancelled_at
 * @property ?string $cancel_reason
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Collection<int, OrderItem> $items
 * @property-read Collection<int, OrderStatusHistory> $history
 *
 * @method static OrderBuilder query()
 */
#[UseEloquentBuilder(OrderBuilder::class)]
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
