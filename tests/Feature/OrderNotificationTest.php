<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\NotificationKind;
use App\Jobs\SendOrderNotification;
use App\Models\Order;
use App\Models\OrderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\SentMessage;
use Tests\Concerns\InteractsWithWebhooks;
use Tests\TestCase;

class OrderNotificationTest extends TestCase
{
    use InteractsWithWebhooks;
    use RefreshDatabase;

    public function test_paid_email_has_contents_and_total(): void
    {
        $this->sendEvent($this->event('order-paid'));

        $message = $this->onlyMessage();
        $this->assertSame('Заказ №MH-1048213 оплачен', $message->getSubject());
        $this->assertSame('anna.smirnova@example.com', $message->getTo()[0]->getAddress());
        $this->assertStringContainsString('TSHIRT-BLK-M', (string) $message->getTextBody());
        $this->assertStringContainsString('4909.50 RUB', (string) $message->getTextBody());
        $this->assertNull($message->getHtmlBody());
    }

    public function test_shipped_email_has_carrier_and_tracking(): void
    {
        $this->sendEvent($this->event('order-shipped'));

        $message = $this->onlyMessage();
        $this->assertSame('Заказ №MH-1048213 передан в доставку', $message->getSubject());
        $this->assertStringContainsString('СДЭК', (string) $message->getTextBody());
        $this->assertStringContainsString('1098765432', (string) $message->getTextBody());
        $this->assertStringContainsString('https://www.cdek.ru/ru/tracking?order_id=1098765432', (string) $message->getTextBody());
    }

    public function test_cancelled_paid_order_email_mentions_refund(): void
    {
        $this->sendEvent($this->event('order-cancelled'));

        $message = $this->onlyMessage();
        $this->assertSame('Заказ №MH-1048377 отменён', $message->getSubject());
        $this->assertStringContainsString('Покупатель отказался от заказа', (string) $message->getTextBody());
        $this->assertStringContainsString('будут возвращены в течение 10 дней', (string) $message->getTextBody());
    }

    public function test_cancelled_unpaid_order_email_has_no_refund_line(): void
    {
        $this->sendEvent($this->event('order-cancelled', ['paid_at' => null]));

        $this->assertStringNotContainsString('возвращены', (string) $this->onlyMessage()->getTextBody());
    }

    public function test_queued_job_skips_email_that_became_untrue(): void
    {
        $this->sendEvent($this->event('order-cancelled'));
        Mail::fake();

        // A stale "paid" job that was queued before the cancellation.
        (new SendOrderNotification('ord_3HV8PW2C', NotificationKind::Paid))->handle();

        Mail::assertNothingSent();
        $this->assertSame(0, OrderNotification::query()->where('kind', 'paid')->count());
    }

    public function test_job_sends_each_kind_at_most_once_even_if_run_twice(): void
    {
        $this->sendEvent($this->event('order-paid'));
        Mail::fake();

        (new SendOrderNotification('ord_7Q2KX9M4', NotificationKind::Paid))->handle();

        Mail::assertNothingSent();
    }

    public function test_failed_send_releases_the_claim_so_a_retry_can_send(): void
    {
        $this->sendEvent($this->event('order-created'));
        $order = Order::query()->findOrFail('ord_7Q2KX9M4');
        $order->forceFill(['status' => 'paid'])->save();

        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('smtp down'));

        try {
            (new SendOrderNotification($order->id, NotificationKind::Paid))->handle();
            $this->fail('Expected the failure to propagate so the queue retries.');
        } catch (\RuntimeException) {
            $this->assertSame(0, OrderNotification::query()->count());
        }
    }

    private function onlyMessage(): \Symfony\Component\Mime\Email
    {
        $messages = $this->app['mailer']->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);

        /** @var SentMessage $sent */
        $sent = $messages[0];

        return $sent->getOriginalMessage();
    }
}
