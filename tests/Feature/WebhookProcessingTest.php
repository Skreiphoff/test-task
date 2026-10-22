<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\NotificationKindEnum;
use App\Enums\NotificationStatusEnum;
use App\Enums\OrderStatusEnum;
use App\Mail\OrderNotificationMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderNotification;
use App\Models\OrderStatusHistory;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\InteractsWithWebhooks;
use Tests\TestCase;

class WebhookProcessingTest extends TestCase
{
    use InteractsWithWebhooks;
    use RefreshDatabase;

    private const ORDER_ID = 'ord_7Q2KX9M4';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_lifecycle_is_stored_with_items_history_and_emails(): void
    {
        foreach (['order-created', 'order-updated', 'order-paid', 'order-shipped'] as $example) {
            $this->sendEvent($this->event($example))->assertAccepted();
            $this->assertLastOutcome('applied');
        }

        $order = Order::query()->findOrFail(self::ORDER_ID);
        $this->assertSame(OrderStatusEnum::Shipped, $order->status);
        $this->assertSame('4909.50', $order->total);
        $this->assertSame('СДЭК', $order->shipment_carrier);
        $this->assertSame(2, OrderItem::query()->where('order_id', self::ORDER_ID)->count());
        $this->assertSame(
            ['created', 'paid', 'shipped'],
            $order->history->map(fn (OrderStatusHistory $h) => $h->status->value)->all(),
        );

        $this->assertMailKinds([NotificationKindEnum::Paid, NotificationKindEnum::Shipped]);
    }

    public function test_updated_event_replaces_items_without_status_history_or_email(): void
    {
        $this->sendEvent($this->event('order-created'));
        $this->sendEvent($this->event('order-updated'))->assertAccepted();
        $this->assertLastOutcome('applied');

        $this->assertSame(3, OrderItem::query()->where('sku', 'TSHIRT-BLK-M')->value('quantity'));
        $this->assertSame(1, OrderStatusHistory::query()->count());
        Mail::assertNothingSent();
    }

    public function test_duplicate_delivery_with_new_timestamp_is_a_noop(): void
    {
        $event = $this->event('order-paid');

        $this->sendEvent($event, time() - 120)->assertAccepted();
        $this->assertLastOutcome('applied');
        $this->sendEvent($event)->assertOk()->assertJson(['status' => 'duplicate']);

        $this->assertSame(1, WebhookEvent::query()->count());
        $this->assertSame(2, OrderStatusHistory::query()->count()); // created (backfilled) + paid
        $this->assertMailKinds([NotificationKindEnum::Paid]);
    }

    public function test_older_snapshot_arriving_late_does_not_overwrite_newer_state(): void
    {
        $this->sendEvent($this->event('order-shipped'));
        $this->sendEvent($this->event('order-paid'))->assertAccepted();
        $this->assertLastOutcome('stale');
        $this->sendEvent($this->event('order-created'))->assertAccepted();
        $this->assertLastOutcome('stale');

        $order = Order::query()->findOrFail(self::ORDER_ID);
        $this->assertSame(OrderStatusEnum::Shipped, $order->status);
        $this->assertNotNull($order->shipment_tracking_number);
        $this->assertMailKinds([NotificationKindEnum::Paid, NotificationKindEnum::Shipped]);
    }

    public function test_timestamps_are_compared_as_instants_not_strings(): void
    {
        // 12:00+03:00 is 09:00Z, which is earlier than 10:00+00:00 although "12" > "10" as text.
        $this->sendEvent($this->event('order-updated', ['updated_at' => '2026-10-21T10:00:00+00:00', 'status' => 'created']));
        $this->sendEvent($this->event('order-paid', ['updated_at' => '2026-10-21T12:00:00+03:00'], 'evt_late'))->assertAccepted();
        $this->assertLastOutcome('stale');

        $this->assertSame(OrderStatusEnum::Created, Order::query()->findOrFail(self::ORDER_ID)->status);
    }

    public function test_same_second_snapshot_does_not_regress_status(): void
    {
        $this->sendEvent($this->event('order-shipped', ['updated_at' => '2026-10-22T09:00:00+03:00']));
        $this->sendEvent($this->event('order-paid', ['updated_at' => '2026-10-22T09:00:00+03:00'], 'evt_tie'))->assertAccepted();
        $this->assertLastOutcome('stale');

        $this->assertSame(OrderStatusEnum::Shipped, Order::query()->findOrFail(self::ORDER_ID)->status);
    }

    public function test_order_first_seen_as_shipped_gets_provable_history_and_both_emails(): void
    {
        $this->sendEvent($this->event('order-shipped'));

        $this->assertSame(
            ['created', 'paid', 'shipped'],
            Order::query()->findOrFail(self::ORDER_ID)->history->map(fn ($h) => $h->status->value)->all(),
        );
        $this->assertMailKinds([NotificationKindEnum::Paid, NotificationKindEnum::Shipped]);
    }

    public function test_cancellation_before_payment_event_never_sends_paid_email(): void
    {
        $this->sendEvent($this->event('order-created'));
        $this->sendEvent($this->event('order-created', [
            'status' => 'cancelled',
            'cancelled_at' => '2026-10-21T13:06:00+03:00',
            'cancel_reason' => 'Передумал',
            'updated_at' => '2026-10-21T13:06:00+03:00',
        ], 'evt_cancel'));
        $this->sendEvent($this->event('order-paid'))->assertAccepted();
        $this->assertLastOutcome('stale');

        $this->assertSame(OrderStatusEnum::Cancelled, Order::query()->findOrFail(self::ORDER_ID)->status);
        $this->assertMailKinds([NotificationKindEnum::Cancelled]);
    }

    public function test_manual_status_rollback_with_newer_snapshot_is_applied_without_resending_emails(): void
    {
        $this->sendEvent($this->event('order-paid'));
        $this->sendEvent($this->event('order-shipped'));
        $this->sendEvent($this->event('order-paid', [
            'updated_at' => '2026-10-23T10:00:00+03:00',
            'shipment' => null,
        ], 'evt_rollback'))->assertAccepted();
        $this->assertLastOutcome('applied');

        $order = Order::query()->findOrFail(self::ORDER_ID);
        $this->assertSame(OrderStatusEnum::Paid, $order->status);
        $this->assertNull($order->shipment_carrier);
        $this->assertSame(
            ['created', 'paid', 'shipped', 'paid'],
            $order->history->map(fn ($h) => $h->status->value)->all(),
        );
        $this->assertMailKinds([NotificationKindEnum::Paid, NotificationKindEnum::Shipped]);
    }

    public function test_unknown_event_type_with_snapshot_is_acknowledged_without_changing_the_order(): void
    {
        $this->sendEvent($this->event('order-shipped'));

        $event = $this->event('order-paid', ['updated_at' => '2026-10-30T10:00:00+03:00'], 'evt_refunded');
        $event['type'] = 'order.refunded';

        $this->sendEvent($event)->assertOk()->assertJson(['status' => 'ignored']);
        $this->assertSame(OrderStatusEnum::Shipped, Order::query()->findOrFail(self::ORDER_ID)->status);
    }

    public function test_unknown_event_type_without_order_is_acknowledged_and_ignored(): void
    {
        $event = ['id' => 'evt_future', 'type' => 'order.something_new', 'created_at' => now()->toIso8601String(), 'data' => ['foo' => 'bar']];

        $this->sendEvent($event)->assertOk()->assertJson(['status' => 'ignored']);
        $this->assertSame(1, WebhookEvent::query()->count());
        $this->assertSame(0, Order::query()->count());
    }

    public function test_malformed_known_event_is_rejected_without_being_stored(): void
    {
        $event = $this->event('order-paid', ['total' => '49.5']);

        $this->sendEvent($event)->assertStatus(422);

        $this->assertSame(0, WebhookEvent::query()->count());
        $this->assertSame(0, Order::query()->count());
    }

    public function test_snapshot_values_that_would_break_storage_or_the_admin_page_are_rejected(): void
    {
        $this->sendEvent($this->event('order-shipped', ['shipment' => [
            'carrier' => 'СДЭК',
            'tracking_number' => '1',
            'tracking_url' => 'javascript:alert(1)',
        ]]))->assertStatus(422);

        $this->sendEvent($this->event('order-paid', ['total' => '123456789012.00'], 'evt_big_total'))->assertStatus(422);

        $order = $this->example('order-paid')['data']['order'];
        $order['items'][0]['quantity'] = 2000000;
        $this->sendEvent($this->event('order-paid', ['items' => $order['items']], 'evt_big_qty'))->assertStatus(422);

        $this->assertSame(0, WebhookEvent::query()->count());
    }

    public function test_non_json_body_is_rejected_with_a_clear_message(): void
    {
        $this->sendRaw('{not json', 'evt_x')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['body']);
    }

    /** @param list<NotificationKindEnum> $expected */
    private function assertMailKinds(array $expected): void
    {
        $sent = Mail::sent(OrderNotificationMail::class)
            ->map(fn (OrderNotificationMail $mail) => $mail->kind->value)
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            collect($expected)->map(fn (NotificationKindEnum $k) => $k->value)->sort()->values()->all(),
            $sent,
        );
        $this->assertSame(
            count($expected),
            OrderNotification::query()->where('status', NotificationStatusEnum::Sent)->count(),
        );
    }
}
