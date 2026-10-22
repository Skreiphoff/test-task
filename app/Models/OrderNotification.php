<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationKindEnum;
use Illuminate\Database\Eloquent\Model;

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
