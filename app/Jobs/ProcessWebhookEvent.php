<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Marketplace\WebhookEventProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessWebhookEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 30, 120, 600];
    }

    public function __construct(public readonly string $eventId)
    {
        $this->onQueue('webhooks');
    }

    public function handle(WebhookEventProcessor $processor): void
    {
        try {
            $processor->process($this->eventId);
        } catch (Throwable $e) {
            $processor->recordFailure($this->eventId, $e);

            throw $e;
        }
    }
}
