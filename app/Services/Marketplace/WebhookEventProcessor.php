<?php

declare(strict_types=1);

namespace App\Services\Marketplace;

use App\Enums\NotificationKind;
use App\Enums\OrderStatus;
use App\Jobs\SendOrderNotification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\WebhookEvent;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Applies MarketHub events idempotently: duplicates, retries and out-of-order
 * delivery all end in a consistent order state.
 */
class WebhookEventProcessor
{
    public const OUTCOME_APPLIED = 'applied';

    public const OUTCOME_STALE = 'stale';

    public const OUTCOME_IGNORED = 'ignored';

    public const OUTCOME_DUPLICATE = 'duplicate';

    /**
     * @param array<string, mixed> $payload raw decoded event, kept for audit
     */
    public function process(string $eventId, string $type, array $payload, ?OrderSnapshot $snapshot): string
    {
        return DB::transaction(function () use ($eventId, $type, $payload, $snapshot): string {
            // ON CONFLICT DO NOTHING: a concurrent duplicate waits for the first
            // transaction to finish and then sees 0 inserted rows.
            $inserted = DB::table('webhook_events')->insertOrIgnore([
                'id' => $eventId,
                'type' => $type,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'outcome' => 'pending',
                'received_at' => now()->toIso8601String(),
            ]);

            if ($inserted === 0) {
                return self::OUTCOME_DUPLICATE;
            }

            $outcome = $snapshot === null ? self::OUTCOME_IGNORED : $this->apply($eventId, $snapshot);

            WebhookEvent::query()->whereKey($eventId)->update(['outcome' => $outcome]);

            return $outcome;
        });
    }

    private function apply(string $eventId, OrderSnapshot $snapshot): string
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

                return self::OUTCOME_APPLIED;
            }

            // Another transaction created the order first; it has committed by now.
            $order = Order::query()->lockForUpdate()->findOrFail($snapshot->id);
        }

        if ($this->isStale($order, $snapshot)) {
            return self::OUTCOME_STALE;
        }

        $previousStatus = $order->status;

        $order->forceFill($snapshot->orderAttributes())->save();
        $this->replaceItems($snapshot);

        if ($previousStatus !== $snapshot->status) {
            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'status' => $snapshot->status,
                'at' => $snapshot->transitionTime(),
                'event_id' => $eventId,
            ]);
            $this->notify($snapshot);
        }

        return self::OUTCOME_APPLIED;
    }

    /**
     * Newer `updated_at` wins. Status rank is deliberately not used across different
     * seconds because MarketHub support may roll a status back manually. Within the
     * same second (timestamps have second precision) a snapshot must not regress the status.
     */
    private function isStale(Order $order, OrderSnapshot $snapshot): bool
    {
        $comparison = $snapshot->updatedAt <=> $order->updated_at;

        if ($comparison !== 0) {
            return $comparison < 0;
        }

        return self::rank($snapshot->status) < self::rank($order->status);
    }

    private static function rank(OrderStatus $status): int
    {
        return match ($status) {
            OrderStatus::Created => 0,
            OrderStatus::Paid => 1,
            OrderStatus::Shipped => 2,
            OrderStatus::Delivered => 3,
            OrderStatus::Cancelled => 4,
        };
    }

    private function replaceItems(OrderSnapshot $snapshot): void
    {
        OrderItem::query()->where('order_id', $snapshot->id)->delete();

        $rows = [];
        foreach ($snapshot->items as $position => $item) {
            $rows[] = ['order_id' => $snapshot->id, 'position' => $position, ...$item];
        }

        if ($rows !== []) {
            OrderItem::query()->insert($rows);
        }
    }

    /**
     * An order first seen in a later status (events arrived out of order) still
     * gets the transitions that the snapshot proves: creation and payment.
     */
    private function recordInitialHistory(string $eventId, OrderSnapshot $snapshot): void
    {
        $entries = [OrderStatus::Created->value => $snapshot->createdAt];

        if ($snapshot->paidAt !== null) {
            $entries[OrderStatus::Paid->value] = $snapshot->paidAt;
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

    private function notify(OrderSnapshot $snapshot): void
    {
        $kind = NotificationKind::forStatus($snapshot->status);

        if ($kind !== null) {
            SendOrderNotification::dispatch($snapshot->id, $kind);
        }
    }

    /**
     * @param array<string, mixed> $attributes
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
