<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\NotificationKindEnum;
use App\Enums\NotificationStatusEnum;
use App\Jobs\SendOrderNotification;
use App\Models\Order;
use App\Models\OrderNotification;
use App\Services\Notifications\NotificationOutbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Tests\Concerns\InteractsWithWebhooks;
use Tests\TestCase;

class OrderNotificationTest extends TestCase
{
    use InteractsWithWebhooks;
    use RefreshDatabase;

    public function test_paid_email_has_contents_and_total(): void
    {
        $this->sendEvent($this->event('order-paid'));

        $message = $this->messageWithSubject('Заказ №MH-1048213 оплачен');
        $this->assertSame('Заказ №MH-1048213 оплачен', $message->getSubject());
        $this->assertSame('anna.smirnova@example.com', $message->getTo()[0]->getAddress());
        $this->assertStringContainsString('TSHIRT-BLK-M', (string) $message->getTextBody());
        $this->assertStringContainsString('4909.50 RUB', (string) $message->getTextBody());
        $this->assertNull($message->getHtmlBody());
    }

    public function test_shipped_email_has_carrier_and_tracking(): void
    {
        $this->sendEvent($this->event('order-shipped'));

        $message = $this->messageWithSubject('Заказ №MH-1048213 передан в доставку');
        $this->assertSame('Заказ №MH-1048213 передан в доставку', $message->getSubject());
        $this->assertStringContainsString('СДЭК', (string) $message->getTextBody());
        $this->assertStringContainsString('1098765432', (string) $message->getTextBody());
        $this->assertStringContainsString('https://www.cdek.ru/ru/tracking?order_id=1098765432', (string) $message->getTextBody());
    }

    public function test_cancelled_paid_order_email_mentions_refund(): void
    {
        $this->sendEvent($this->event('order-cancelled'));

        $message = $this->messageWithSubject('Заказ №MH-1048377 отменён');
        $this->assertSame('Заказ №MH-1048377 отменён', $message->getSubject());
        $this->assertStringContainsString('Покупатель отказался от заказа', (string) $message->getTextBody());
        $this->assertStringContainsString('будут возвращены в течение 10 дней', (string) $message->getTextBody());
    }

    public function test_cancelled_unpaid_order_email_has_no_refund_line(): void
    {
        $this->sendEvent($this->event('order-cancelled', ['paid_at' => null]));

        $this->assertStringNotContainsString('возвращены', (string) $this->messageWithSubject('Заказ №MH-1048377 отменён')->getTextBody());
    }

    public function test_outbox_row_for_an_email_that_became_untrue_is_skipped_not_sent(): void
    {
        $this->sendEvent($this->event('order-cancelled'));
        Mail::fake();

        // A "paid" email queued before the cancellation was processed.
        $this->outbox()->enqueue('ord_3HV8PW2C', NotificationKindEnum::Paid);
        (new SendOrderNotification('ord_3HV8PW2C', NotificationKindEnum::Paid))->handle($this->outbox());

        Mail::assertNothingSent();
        $this->assertSame(NotificationStatusEnum::Skipped, $this->notification('ord_3HV8PW2C', 'paid')->status);
    }

    public function test_a_sent_notification_is_never_sent_again(): void
    {
        $this->sendEvent($this->event('order-paid'));
        $this->assertSame(NotificationStatusEnum::Sent, $this->notification('ord_7Q2KX9M4', 'paid')->status);
        Mail::fake();

        (new SendOrderNotification('ord_7Q2KX9M4', NotificationKindEnum::Paid))->handle($this->outbox());

        Mail::assertNothingSent();
    }

    public function test_malformed_customer_address_is_skipped_without_retrying(): void
    {
        $this->sendEvent($this->event('order-created'));
        Order::query()->findOrFail('ord_7Q2KX9M4')->forceFill(['status' => 'paid', 'customer_email' => 'not-an-email'])->save();
        Mail::fake();

        $this->outbox()->enqueue('ord_7Q2KX9M4', NotificationKindEnum::Paid);
        (new SendOrderNotification('ord_7Q2KX9M4', NotificationKindEnum::Paid))->handle($this->outbox());

        Mail::assertNothingSent();
        $this->assertSame(NotificationStatusEnum::Skipped, $this->notification('ord_7Q2KX9M4', 'paid')->status);
    }

    public function test_failed_send_keeps_the_row_pending_and_records_the_attempt(): void
    {
        $this->sendEvent($this->event('order-created'));
        Order::query()->findOrFail('ord_7Q2KX9M4')->forceFill(['status' => 'paid'])->save();
        $this->outbox()->enqueue('ord_7Q2KX9M4', NotificationKindEnum::Paid);

        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('smtp down'));

        try {
            (new SendOrderNotification('ord_7Q2KX9M4', NotificationKindEnum::Paid))->handle($this->outbox());
            $this->fail('Expected the failure to propagate so the queue retries.');
        } catch (\RuntimeException) {
            $notification = $this->notification('ord_7Q2KX9M4', 'paid');
            $this->assertSame(NotificationStatusEnum::Pending, $notification->status);
            $this->assertSame(1, $notification->attempts);
            $this->assertSame('smtp down', $notification->last_error);
        }
    }

    public function test_enqueue_is_idempotent_and_revives_only_skipped_rows(): void
    {
        $this->sendEvent($this->event('order-created'));

        $this->assertTrue($this->outbox()->enqueue('ord_7Q2KX9M4', NotificationKindEnum::Paid));
        $this->assertFalse($this->outbox()->enqueue('ord_7Q2KX9M4', NotificationKindEnum::Paid));

        $this->notification('ord_7Q2KX9M4', 'paid')->forceFill(['status' => NotificationStatusEnum::Skipped])->save();
        $this->assertTrue($this->outbox()->enqueue('ord_7Q2KX9M4', NotificationKindEnum::Paid));
        $this->assertSame(NotificationStatusEnum::Pending, $this->notification('ord_7Q2KX9M4', 'paid')->status);

        $this->notification('ord_7Q2KX9M4', 'paid')->forceFill(['status' => NotificationStatusEnum::Sent])->save();
        $this->assertFalse($this->outbox()->enqueue('ord_7Q2KX9M4', NotificationKindEnum::Paid));
        $this->assertSame(1, OrderNotification::query()->count());
    }

    public function test_stuck_pending_rows_are_redispatched_but_fresh_ones_are_not(): void
    {
        $this->sendEvent($this->event('order-created'));
        $this->outbox()->enqueue('ord_7Q2KX9M4', NotificationKindEnum::Paid);
        Queue::fake();

        $this->artisan('notifications:dispatch-stuck')->assertSuccessful();
        Queue::assertNothingPushed();

        $this->notification('ord_7Q2KX9M4', 'paid')->forceFill(['created_at' => now()->subMinutes(5)])->save();
        $this->artisan('notifications:dispatch-stuck')->assertSuccessful();

        Queue::assertPushed(SendOrderNotification::class, fn (SendOrderNotification $job) => $job->orderId === 'ord_7Q2KX9M4'
            && $job->kind === NotificationKindEnum::Paid);
    }

    public function test_rows_that_exhausted_their_attempts_are_not_redispatched(): void
    {
        $this->sendEvent($this->event('order-created'));
        $this->outbox()->enqueue('ord_7Q2KX9M4', NotificationKindEnum::Paid);
        $this->notification('ord_7Q2KX9M4', 'paid')
            ->forceFill(['created_at' => now()->subHour(), 'attempts' => NotificationOutbox::MAX_ATTEMPTS])
            ->save();
        Queue::fake();

        $this->artisan('notifications:dispatch-stuck')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    private function outbox(): NotificationOutbox
    {
        return $this->app->make(NotificationOutbox::class);
    }

    private function notification(string $orderId, string $kind): OrderNotification
    {
        return OrderNotification::query()->where('order_id', $orderId)->where('kind', $kind)->firstOrFail();
    }

    private function messageWithSubject(string $subject): Email
    {
        $matching = collect($this->app['mailer']->getSymfonyTransport()->messages())
            ->map(fn (SentMessage $sent): Email => $sent->getOriginalMessage())
            ->filter(fn (Email $email): bool => $email->getSubject() === $subject);

        $this->assertCount(1, $matching, "Expected exactly one email with subject: {$subject}");

        return $matching->first();
    }
}
