<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProcessingOutcomeEnum;
use App\Jobs\ProcessWebhookEvent;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\WebhookEvent;
use App\Services\Marketplace\WebhookEventProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithWebhooks;
use Tests\TestCase;

class WebhookInboxTest extends TestCase
{
    use InteractsWithWebhooks;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Queue::fake();
    }

    public function test_the_request_only_stores_the_event_and_queues_the_processing(): void
    {
        $this->sendEvent($this->event('order-paid'))
            ->assertAccepted()
            ->assertExactJson(['status' => 'accepted']);

        $this->assertSame(0, Order::query()->count(), 'Nothing is applied inside the request.');
        $event = WebhookEvent::query()->findOrFail('evt_01JAXM4Q9T6V2K8R3N5W7Y0ZBC');
        $this->assertSame(ProcessingOutcomeEnum::Pending, $event->outcome);
        $this->assertSame('ord_7Q2KX9M4', $event->order_id);
        Queue::assertPushedOn('webhooks', ProcessWebhookEvent::class, fn (ProcessWebhookEvent $job) => $job->eventId === $event->id);
    }

    public function test_the_worker_applies_the_stored_event_once(): void
    {
        $this->sendEvent($this->event('order-paid'));
        $processor = $this->app->make(WebhookEventProcessor::class);

        $processor->process('evt_01JAXM4Q9T6V2K8R3N5W7Y0ZBC');
        $processor->process('evt_01JAXM4Q9T6V2K8R3N5W7Y0ZBC');

        $this->assertSame('paid', Order::query()->findOrFail('ord_7Q2KX9M4')->status->value);
        $this->assertSame(2, OrderStatusHistory::query()->count()); // created (backfilled) + paid, not duplicated
        $this->assertSame(
            ProcessingOutcomeEnum::Applied,
            WebhookEvent::query()->findOrFail('evt_01JAXM4Q9T6V2K8R3N5W7Y0ZBC')->outcome,
        );
    }

    public function test_a_duplicate_is_acknowledged_without_a_second_job(): void
    {
        $event = $this->event('order-paid');

        $this->sendEvent($event)->assertAccepted();
        $this->sendEvent($event)->assertOk()->assertExactJson(['status' => 'duplicate']);

        Queue::assertPushed(ProcessWebhookEvent::class, 1);
        $this->assertSame(1, WebhookEvent::query()->count());
    }

    public function test_an_unknown_event_type_is_stored_as_ignored_without_a_job(): void
    {
        $event = ['id' => 'evt_future', 'type' => 'order.something_new', 'data' => ['foo' => 'bar']];

        $this->sendEvent($event)->assertOk()->assertExactJson(['status' => 'ignored']);

        Queue::assertNothingPushed();
        $this->assertSame(ProcessingOutcomeEnum::Ignored, WebhookEvent::query()->findOrFail('evt_future')->outcome);
    }

    public function test_a_failing_event_stays_pending_and_records_the_attempt(): void
    {
        $this->sendEvent($this->event('order-paid'));
        $event = WebhookEvent::query()->findOrFail('evt_01JAXM4Q9T6V2K8R3N5W7Y0ZBC');
        $event->forceFill(['payload' => ['data' => ['order' => ['id' => 'ord_7Q2KX9M4']]]])->save();

        try {
            (new ProcessWebhookEvent($event->id))->handle($this->app->make(WebhookEventProcessor::class));
            $this->fail('A broken payload must make the job fail so that the queue retries it.');
        } catch (\Throwable) {
            $event->refresh();
            $this->assertSame(ProcessingOutcomeEnum::Pending, $event->outcome);
            $this->assertSame(1, $event->attempts);
            $this->assertNotNull($event->last_error);
        }
    }

    public function test_stuck_events_are_redispatched_but_fresh_ones_and_exhausted_ones_are_not(): void
    {
        $this->sendEvent($this->event('order-paid'));
        Queue::fake();

        $this->artisan('webhooks:dispatch-stuck')->assertSuccessful();
        Queue::assertNothingPushed();

        $event = WebhookEvent::query()->findOrFail('evt_01JAXM4Q9T6V2K8R3N5W7Y0ZBC');
        $event->forceFill(['received_at' => now()->subMinutes(5), 'attempts' => 10])->save();
        $this->artisan('webhooks:dispatch-stuck')->assertSuccessful();
        Queue::assertNothingPushed();

        $event->forceFill(['attempts' => 0])->save();
        $this->artisan('webhooks:dispatch-stuck')->assertSuccessful();
        Queue::assertPushed(ProcessWebhookEvent::class, fn (ProcessWebhookEvent $job) => $job->eventId === $event->id);
    }

    public function test_events_arriving_out_of_order_are_applied_by_updated_at_whatever_the_worker_order(): void
    {
        $this->sendEvent($this->event('order-created'));
        $this->sendEvent($this->event('order-shipped'));
        $this->sendEvent($this->event('order-paid'));
        $processor = $this->app->make(WebhookEventProcessor::class);

        // The workers pick the newest snapshot first and the older ones afterwards.
        foreach (['evt_01JAZ8R2W4Y6A8C0E2G4J6L8N0', 'evt_01JAXM4Q9T6V2K8R3N5W7Y0ZBC', 'evt_01JAXKZB7M2D9F4H6J8K0N3P5R'] as $id) {
            $processor->process($id);
        }

        $this->assertSame('shipped', Order::query()->findOrFail('ord_7Q2KX9M4')->status->value);
        $this->assertSame(
            ['stale', 'applied', 'stale'],
            collect(['evt_01JAXM4Q9T6V2K8R3N5W7Y0ZBC', 'evt_01JAZ8R2W4Y6A8C0E2G4J6L8N0', 'evt_01JAXKZB7M2D9F4H6J8K0N3P5R'])
                ->map(fn (string $id) => WebhookEvent::query()->findOrFail($id)->outcome->value)->all(),
        );
    }
}
