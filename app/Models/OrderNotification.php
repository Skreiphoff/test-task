<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationKind;
use Illuminate\Database\Eloquent\Model;

class OrderNotification extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'kind' => NotificationKind::class,
            'claimed_at' => 'datetime',
        ];
    }
}
