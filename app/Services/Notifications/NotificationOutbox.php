<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\NotificationKindEnum;
use App\Enums\NotificationStatusEnum;
use App\Mail\OrderNotificationMail;
use App\Models\Order;
use App\Models\OrderNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\LazyCollection;
use Throwable;

class NotificationOutbox
{
    public const MAX_ATTEMPTS = 10;

    /**
     * Writes the intent to send. Call it inside the transaction that changes the order.
     * Returns true when there is a pending row to deliver: new, or revived after being skipped.
     */
    public function enqueue(string $orderId, NotificationKindEnum $kind): bool
    {
        return DB::affectingStatement(
            <<<'SQL'
            INSERT INTO order_notifications (order_id, kind, status, created_at)
            VALUES (?, ?, 'pending', now())
            ON CONFLICT (order_id, kind) DO UPDATE
                SET status = 'pending', attempts = 0, last_error = NULL, processed_at = NULL, created_at = now()
                WHERE order_notifications.status = 'skipped'
            SQL,
            [$orderId, $kind->value],
        ) === 1;
    }

    /**
     * Sends a pending notification. The row lock and the send share one transaction: a failure or a killed
     * worker rolls back and leaves the row pending, so the message is neither lost nor sent twice in parallel.
     */
    public function deliver(string $orderId, NotificationKindEnum $kind): void
    {
        DB::transaction(function () use ($orderId, $kind): void {
            $notification = OrderNotification::query()
                ->where('order_id', $orderId)
                ->where('kind', $kind)
                ->where('status', NotificationStatusEnum::Pending)
                ->lock('for update skip locked')
                ->first();

            if ($notification === null) {
                return;
            }

            $order = Order::query()->with('items')->find($orderId);

            // Decided at send time: a "paid" email must not go out for an order cancelled meanwhile,
            // and a malformed address can never be delivered, so retrying would be pointless.
            if ($order === null
                || ! in_array($order->status, $kind->validStatuses(), true)
                || filter_var($order->customer_email, FILTER_VALIDATE_EMAIL) === false) {
                $this->finish($notification, NotificationStatusEnum::Skipped);

                return;
            }

            Mail::to($order->customer_email, $order->customer_name)->send(new OrderNotificationMail($order, $kind));

            $this->finish($notification, NotificationStatusEnum::Sent);
        });
    }

    public function recordFailure(string $orderId, NotificationKindEnum $kind, Throwable $error): void
    {
        OrderNotification::query()
            ->where('order_id', $orderId)
            ->where('kind', $kind)
            ->where('status', NotificationStatusEnum::Pending)
            ->update([
                'attempts' => DB::raw('attempts + 1'),
                'last_error' => mb_substr($error->getMessage(), 0, 1000),
            ]);
    }

    /**
     * Pending rows that nobody picked up: the dispatch was lost between the commit and the queue.
     *
     * @return LazyCollection<int, OrderNotification>
     */
    public function stuck(int $olderThanSeconds)
    {
        return OrderNotification::query()
            ->where('status', NotificationStatusEnum::Pending)
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->where('created_at', '<', now()->subSeconds($olderThanSeconds))
            ->lazyById();
    }

    private function finish(OrderNotification $notification, NotificationStatusEnum $status): void
    {
        $notification->forceFill(['status' => $status, 'processed_at' => now()])->save();
    }
}
