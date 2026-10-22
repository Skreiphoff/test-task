<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatusEnum;
use Illuminate\Database\Eloquent\Model;

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
