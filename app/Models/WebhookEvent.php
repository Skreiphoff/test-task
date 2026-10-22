<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProcessingOutcomeEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $type
 * @property ?string $order_id
 * @property array<string, mixed> $payload
 * @property ProcessingOutcomeEnum $outcome
 * @property int $attempts
 * @property ?string $last_error
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
            'outcome' => ProcessingOutcomeEnum::class,
            'attempts' => 'integer',
            'received_at' => 'datetime',
        ];
    }
}
