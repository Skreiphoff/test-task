<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationKindEnum;
use App\Enums\NotificationStatusEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $order_id
 * @property NotificationKindEnum $kind
 * @property NotificationStatusEnum $status
 * @property int $attempts
 * @property ?string $last_error
 * @property ?CarbonInterface $locked_until
 * @property CarbonInterface $created_at
 * @property ?CarbonInterface $processed_at
 */
class OrderNotification extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'kind' => NotificationKindEnum::class,
            'status' => NotificationStatusEnum::class,
            'attempts' => 'integer',
            'locked_until' => 'datetime',
            'created_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
