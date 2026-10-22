<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatusEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $order_id
 * @property OrderStatusEnum $status
 * @property CarbonInterface $at
 * @property string $event_id
 */
class OrderStatusHistory extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => OrderStatusEnum::class,
            'at' => 'datetime',
        ];
    }
}
