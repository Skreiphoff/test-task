<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\NotificationKind;
use App\Mail\OrderNotificationMail;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendOrderNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 30, 120, 600];
    }

    public function __construct(
        public readonly string $orderId,
        public readonly NotificationKind $kind,
    ) {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $order = Order::query()->with('items')->find($this->orderId);

        // Decide on the state at send time, not at dispatch time: a queued "paid"
        // email must not go out for an order that has been cancelled in the meantime.
        if ($order === null || ! in_array($order->status, $this->kind->validStatuses(), true)) {
            return;
        }

        // The unique (order_id, kind) row is the atomic "at most once" guard.
        $claimed = DB::table('order_notifications')->insertOrIgnore([
            'order_id' => $order->id,
            'kind' => $this->kind->value,
            'claimed_at' => now()->toIso8601String(),
        ]) === 1;

        if (! $claimed) {
            return;
        }

        try {
            Mail::to($order->customer_email, $order->customer_name)
                ->send(new OrderNotificationMail($order, $this->kind));
        } catch (Throwable $e) {
            // Release the claim so that a queue retry can send the email.
            DB::table('order_notifications')
                ->where('order_id', $order->id)
                ->where('kind', $this->kind->value)
                ->delete();

            throw $e;
        }
    }
}
