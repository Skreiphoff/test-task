<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\NotificationKindEnum;
use App\Services\Notifications\NotificationOutbox;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendOrderNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 30, 120, 600];
    }

    public function __construct(
        public readonly string $orderId,
        public readonly NotificationKindEnum $kind,
    ) {
        $this->afterCommit();
    }

    public function handle(NotificationOutbox $outbox): void
    {
        try {
            $outbox->deliver($this->orderId, $this->kind);
        } catch (Throwable $e) {
            $outbox->recordFailure($this->orderId, $this->kind, $e);

            throw $e;
        }
    }
}
