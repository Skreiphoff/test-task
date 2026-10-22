<?php

declare(strict_types=1);

namespace App\Services\Marketplace;

use App\DTO\IncomingEventDto;
use App\DTO\OrderSnapshotDto;
use App\Enums\NotificationKindEnum;
use App\Enums\OrderStatusEnum;
use App\Enums\ProcessingOutcomeEnum;
use App\Jobs\SendOrderNotification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\WebhookEvent;
use App\Services\Notifications\NotificationOutbox;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class WebhookEventProcessor
{
    public function __construct(private readonly NotificationOutbox $outbox) {}

    public function process(IncomingEventDto $event): ProcessingOutcomeEnum
    {
        return DB::transaction(function () use ($event): ProcessingOutcomeEnum {
            // A concurrent duplicate waits for the first transaction and then inserts 0 rows.
            $inserted = DB::table('webhook_events')->insertOrIgnore([
                'id' => $event->id,
                'type' => $event->type,
                'payload' => json_encode($event->payload, JSON_THROW_ON_ERROR),
                'outcome' => 'pending',
                'received_at' => now()->toIso8601String(),
            ]);

            if ($inserted === 0) {
                return ProcessingOutcomeEnum::Duplicate;
            }

            $outcome = $event->snapshot === null
                ? ProcessingOutcomeEnum::Ignored
                : $this->apply($event->id, $event->snapshot);

            WebhookEvent::query()->whereKey($event->id)->update(['outcome' => $outcome->value]);

            return $outcome;
        });
    }

    private function apply(string $eventId, OrderSnapshotDto $snapshot): ProcessingOutcomeEnum
    {
        $order = Order::query()->lockForUpdate()->find($snapshot->id);

        if ($order === null) {
            $created = DB::table('orders')->insertOrIgnore(
                ['id' => $snapshot->id, ...$this->serialize($snapshot->orderAttributes())],
            ) === 1;

            if ($created) {
                $this->replaceItems($snapshot);
                $this->recordInitialHistory($eventId, $snapshot);
                $this->notify($snapshot);

                return ProcessingOutcomeEnum::Applied;
            }

            // Lost the creation race; the other transaction has committed.
            $order = Order::query()->lockForUpdate()->findOrFail($snapshot->id);
        }

        if ($this->isStale($order, $snapshot)) {
            return ProcessingOutcomeEnum::Stale;
        }

        $previousStatus = $order->status;

        $order->forceFill($snapshot->orderAttributes())->save();
        $this->replaceItems($snapshot);

        if ($previousStatus !== $snapshot->status) {
            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'status' => $snapshot->status,
                'at' => $this->transitionTime($order, $snapshot),
                'event_id' => $eventId,
            ]);
            $this->notify($snapshot);
        }

        return ProcessingOutcomeEnum::Applied;
    }

    /** After a manual rollback `paid_at` lies in the past; fall back to `updated_at` to keep history chronological. */
    private function transitionTime(Order $order, OrderSnapshotDto $snapshot): CarbonInterface
    {
        $at = $snapshot->transitionTime();
        $lastEntry = $order->history()->reorder()->max('at');

        return $lastEntry !== null && $at < CarbonImmutable::parse($lastEntry) ? $snapshot->updatedAt : $at;
    }

    /**
     * Rank is ignored across different seconds because support may roll a status back manually;
     * within the same second (the timestamp precision) a snapshot must not regress the status.
     */
    private function isStale(Order $order, OrderSnapshotDto $snapshot): bool
    {
        $comparison = $snapshot->updatedAt <=> $order->updated_at;

        if ($comparison !== 0) {
            return $comparison < 0;
        }

        return self::rank($snapshot->status) < self::rank($order->status);
    }

    private static function rank(OrderStatusEnum $status): int
    {
        return match ($status) {
            OrderStatusEnum::Created => 0,
            OrderStatusEnum::Paid => 1,
            OrderStatusEnum::Shipped => 2,
            OrderStatusEnum::Delivered => 3,
            OrderStatusEnum::Cancelled => 4,
        };
    }

    private function replaceItems(OrderSnapshotDto $snapshot): void
    {
        OrderItem::query()->where('order_id', $snapshot->id)->delete();

        $rows = [];
        foreach ($snapshot->items as $position => $item) {
            $rows[] = [
                'order_id' => $snapshot->id,
                'position' => $position,
                'sku' => $item->sku,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'price' => $item->price,
            ];
        }

        if ($rows !== []) {
            OrderItem::query()->insert($rows);
        }
    }

    /** An order first seen in a later status still gets the transitions the snapshot proves. */
    private function recordInitialHistory(string $eventId, OrderSnapshotDto $snapshot): void
    {
        $entries = [OrderStatusEnum::Created->value => $snapshot->createdAt];

        if ($snapshot->paidAt !== null) {
            $entries[OrderStatusEnum::Paid->value] = $snapshot->paidAt;
        }

        $entries[$snapshot->status->value] ??= $snapshot->transitionTime();

        foreach ($entries as $status => $at) {
            OrderStatusHistory::query()->create([
                'order_id' => $snapshot->id,
                'status' => $status,
                'at' => $at,
                'event_id' => $eventId,
            ]);
        }
    }

    /**
     * A snapshot with `paid_at` proves the payment even if its own event was lost or arrived late,
     * so "paid" is sent together with the email of the current status. The unique
     * (order_id, kind) row keeps each kind at one email.
     */
    private function notify(OrderSnapshotDto $snapshot): void
    {
        $kinds = array_filter([
            $snapshot->paidAt !== null ? NotificationKindEnum::Paid : null,
            NotificationKindEnum::forStatus($snapshot->status),
        ]);

        foreach (array_unique($kinds, SORT_REGULAR) as $kind) {
            // The outbox row is written in this transaction; the job only speeds up the delivery.
            if (in_array($snapshot->status, $kind->validStatuses(), true) && $this->outbox->enqueue($snapshot->id, $kind)) {
                SendOrderNotification::dispatch($snapshot->id, $kind);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function serialize(array $attributes): array
    {
        return array_map(
            fn (mixed $value): mixed => $value instanceof CarbonInterface ? $value->toIso8601String() : $value,
            $attributes,
        );
    }
}
