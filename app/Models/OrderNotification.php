<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationKindEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $order_id
 * @property NotificationKindEnum $kind
 * @property CarbonInterface $claimed_at
 */
class OrderNotification extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'kind' => NotificationKindEnum::class,
            'claimed_at' => 'datetime',
        ];
    }
}
