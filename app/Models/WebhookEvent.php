<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $type
 * @property array<string, mixed> $payload
 * @property string $outcome
 * @property CarbonInterface $received_at
 */
class WebhookEvent extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'received_at' => 'datetime',
        ];
    }
}
