<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithWebhooks;
use Tests\TestCase;

class WebhookSignatureTest extends TestCase
{
    use InteractsWithWebhooks;
    use RefreshDatabase;

    public function test_documented_signature_example_is_accepted(): void
    {
        // The documented example uses a fixed timestamp far from "now".
        config()->set('services.marketplace.webhook_tolerance', PHP_INT_MAX);

        $body = (string) file_get_contents(base_path('task/examples/signature-example-body.json'));

        $this->call('POST', '/api/webhooks/marketplace', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_ID' => json_decode($body, true)['id'],
            'HTTP_X_WEBHOOK_TIMESTAMP' => '1792577149',
            'HTTP_X_WEBHOOK_SIGNATURE' => 'sha256=4d4f2d50f98596ab503d29dc96db6fda247e3c482bff130bd97353564cb163f7',
        ], $body)->assertAccepted();
    }

    public function test_valid_signature_over_pretty_printed_body_is_accepted(): void
    {
        $body = (string) file_get_contents(base_path('task/examples/order-created.json'));

        $this->sendRaw($body, json_decode($body, true)['id'])->assertAccepted();
    }

    public function test_invalid_signature_is_rejected_and_nothing_is_stored(): void
    {
        $event = $this->event('order-created');

        $this->sendRaw(json_encode($event), $event['id'], secret: 'wrong-secret')->assertUnauthorized();

        $this->assertSame(0, WebhookEvent::query()->count());
    }

    public function test_tampered_body_is_rejected(): void
    {
        $event = $this->event('order-created');
        $body = json_encode($event);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test_5f1d8c2a9b');

        $this->call('POST', '/api/webhooks/marketplace', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_ID' => $event['id'],
            'HTTP_X_WEBHOOK_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_WEBHOOK_SIGNATURE' => 'sha256='.$signature,
        ], str_replace('RUB', 'USD', $body))->assertUnauthorized();
    }

    public function test_stale_and_future_timestamps_are_rejected(): void
    {
        $event = $this->event('order-created');

        $this->sendEvent($event, time() - 301)->assertUnauthorized();
        $this->sendEvent($event, time() + 301)->assertUnauthorized();
        $this->sendEvent($event, time() - 299)->assertAccepted();
    }

    public function test_missing_signature_headers_are_rejected(): void
    {
        $this->postJson('/api/webhooks/marketplace', $this->event('order-created'))->assertUnauthorized();
    }

    public function test_header_event_id_must_match_body_id(): void
    {
        $event = $this->event('order-created');

        $this->sendRaw(json_encode($event), 'evt_other')->assertStatus(422);
    }

    public function test_missing_secret_fails_closed(): void
    {
        config()->set('services.marketplace.webhook_secret', '');

        $this->sendEvent($this->event('order-created'))->assertStatus(500);
    }
}
