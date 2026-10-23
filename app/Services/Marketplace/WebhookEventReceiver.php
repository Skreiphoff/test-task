<?php

declare(strict_types=1);

namespace App\Services\Marketplace;

use App\DTO\IncomingEventDto;
use App\Enums\ProcessingOutcomeEnum;
use App\Enums\WebhookReceiptEnum;
use App\Jobs\ProcessWebhookEvent;
use App\Models\WebhookEvent;
use Illuminate\Support\LazyCollection;

/**
 * The inbox: stores the event durably and hands it to the queue. Nothing is applied in the request,
 * so the endpoint answers in milliseconds regardless of the load on the order tables.
 */
class WebhookEventReceiver
{
    public const MAX_ATTEMPTS = 10;

    public function receive(IncomingEventDto $event): WebhookReceiptEnum
    {
        // ON CONFLICT DO NOTHING: a retry or a concurrent duplicate inserts 0 rows.
        $inserted = WebhookEvent::query()->insertOrIgnore([
            'id' => $event->id,
            'type' => $event->type,
            'order_id' => $event->snapshot?->id,
            'payload' => json_encode($event->payload, JSON_THROW_ON_ERROR),
            'outcome' => $event->snapshot === null ? ProcessingOutcomeEnum::Ignored->value : ProcessingOutcomeEnum::Pending->value,
            'received_at' => now()->toIso8601String(),
        ]);

        if ($inserted === 0) {
            return WebhookReceiptEnum::Duplicate;
        }

        if ($event->snapshot === null) {
            return WebhookReceiptEnum::Ignored;
        }

        ProcessWebhookEvent::dispatch($event->id);

        return WebhookReceiptEnum::Accepted;
    }

    /**
     * Stored events that nobody applied: the dispatch was lost after the insert, or the job failed for good.
     *
     * @return LazyCollection<int, WebhookEvent>
     */
    public function stuck(int $olderThanSeconds): LazyCollection
    {
        return WebhookEvent::query()
            ->where('outcome', ProcessingOutcomeEnum::Pending)
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->where('received_at', '<', now()->subSeconds($olderThanSeconds))
            ->lazyById(100, 'id');
    }
}
