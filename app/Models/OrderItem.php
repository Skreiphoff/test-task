<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $order_id
 * @property int $position
 * @property string $sku
 * @property string $name
 * @property int $quantity
 * @property string $price
 * @property-read Order $order
 */
class OrderItem extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function subtotal(): string
    {
        return bcmul((string) $this->price, (string) $this->quantity, 2);
    }
}
