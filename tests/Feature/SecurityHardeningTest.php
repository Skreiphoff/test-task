<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\WebhookEvent;
use App\Support\ErrorSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\InteractsWithWebhooks;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use InteractsWithWebhooks;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_the_api_sends_no_cors_headers(): void
    {
        $this->sendEvent($this->event('order-paid'));

        $this->withHeaders(['Origin' => 'https://evil.example'])
            ->getJson('/api/orders')
            ->assertOk()
            ->assertHeaderMissing('Access-Control-Allow-Origin');

        $this->withHeaders(['Origin' => 'https://evil.example', 'Access-Control-Request-Method' => 'GET'])
            ->call('OPTIONS', '/api/orders')
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_responses_carry_security_headers_and_a_csp_outside_debug(): void
    {
        $this->get('/admin/orders')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeaderMissing('Content-Security-Policy');

        config()->set('app.debug', false);

        $csp = $this->get('/admin/orders')->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", (string) $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", (string) $csp);
    }

    public function test_a_hostile_order_id_cannot_break_out_of_the_alpine_expression(): void
    {
        $id = "x');alert(document.domain);('";
        $this->sendEvent($this->event('order-paid', ['id' => $id]));

        $html = $this->get('/admin/orders/'.rawurlencode($id))->assertOk()->getContent();

        $this->assertStringNotContainsString("writeText('x');alert", (string) $html);
        $this->assertStringNotContainsString("');alert(document.domain)", (string) $html);
        $this->assertStringContainsString('\\u0027', (string) $html);
    }

    public function test_test_tools_are_gated_on_every_request(): void
    {
        $this->post('/admin/test/orders')->assertRedirect();
        $this->assertSame(1, Order::query()->count());

        config()->set('app.admin_test_tools', false);

        $this->post('/admin/test/orders')->assertNotFound();
        $this->post('/admin/test/orders/ord_7Q2KX9M4/duplicate')->assertNotFound();
        $this->assertSame(1, Order::query()->count());
    }

    public function test_long_cancel_reason_and_tracking_url_are_stored_not_poisoned(): void
    {
        $reason = str_repeat('я', 1000);
        $url = 'https://example.com/track?q='.str_repeat('a', 1900);

        $this->sendEvent($this->event('order-cancelled', ['cancel_reason' => $reason]))->assertAccepted();
        $this->assertLastOutcome('applied');
        $this->assertSame($reason, Order::query()->findOrFail('ord_3HV8PW2C')->cancel_reason);

        $this->sendEvent($this->event('order-shipped', ['shipment' => [
            'carrier' => 'СДЭК', 'tracking_number' => '1', 'tracking_url' => $url,
        ]]))->assertAccepted();
        $this->assertLastOutcome('applied');
        $this->assertSame($url, Order::query()->findOrFail('ord_7Q2KX9M4')->shipment_tracking_url);
    }

    public function test_a_snapshot_dated_in_the_future_is_rejected(): void
    {
        $this->sendEvent($this->event('order-paid', ['updated_at' => now()->addDays(2)->toIso8601String()]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['data.order.updated_at']);

        $this->assertSame(0, WebhookEvent::query()->count());
    }

    public function test_the_stored_error_has_no_message_so_it_cannot_leak_bindings(): void
    {
        $error = new \RuntimeException('SQLSTATE[22001]: insert into orders values (jane@example.com, +7916...)', 22001);

        $summary = ErrorSummary::of($error);

        $this->assertSame('RuntimeException (code 22001)', $summary);
        $this->assertStringNotContainsString('jane@example.com', $summary);
    }
}
