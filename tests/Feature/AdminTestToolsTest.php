<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatusEnum;
use App\Models\Order;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminTestToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_create_button_sends_a_signed_event_through_the_webhook(): void
    {
        $response = $this->post('/admin/test/orders');

        $order = Order::query()->firstOrFail();
        $response->assertRedirect(route('admin.orders.show', $order->id));
        $this->assertSame(OrderStatusEnum::Created, $order->status);
        $this->assertGreaterThan(0, $order->items()->count());
    }

    public function test_transition_buttons_walk_the_order_through_its_lifecycle(): void
    {
        $this->post('/admin/test/orders');
        $id = Order::query()->firstOrFail()->id;

        foreach (['paid', 'shipped', 'delivered'] as $status) {
            $this->post("/admin/test/orders/{$id}/status/{$status}")
                ->assertSessionHas('flash', fn (string $m) => str_contains($m, 'ответ: accepted'));
        }

        $order = Order::query()->findOrFail($id);
        $this->assertSame(OrderStatusEnum::Delivered, $order->status);
        $this->assertSame(
            ['created', 'paid', 'shipped', 'delivered'],
            $order->history->map(fn ($h) => $h->status->value)->all(),
        );
    }

    public function test_duplicate_and_stale_buttons_do_not_change_the_order(): void
    {
        $this->post('/admin/test/orders');
        $id = Order::query()->firstOrFail()->id;
        $this->post("/admin/test/orders/{$id}/status/paid");

        $this->post("/admin/test/orders/{$id}/duplicate")
            ->assertSessionHas('flash', fn (string $m) => str_contains($m, 'ответ: duplicate'));
        $this->post("/admin/test/orders/{$id}/stale")
            ->assertSessionHas('flash', fn (string $m) => str_contains($m, 'ответ: accepted'));
        $this->assertSame(1, WebhookEvent::query()->where('outcome', 'stale')->count());

        $this->assertSame(OrderStatusEnum::Paid, Order::query()->findOrFail($id)->status);
    }

    public function test_pressing_the_current_status_sends_nothing_and_changes_nothing(): void
    {
        $this->post('/admin/test/orders');
        $id = Order::query()->firstOrFail()->id;
        $this->post("/admin/test/orders/{$id}/status/paid");
        $before = Order::query()->findOrFail($id);
        $eventsBefore = WebhookEvent::query()->count();

        $this->post("/admin/test/orders/{$id}/status/paid")
            ->assertSessionHas('flash', fn (string $m) => str_contains($m, 'уже в статусе paid'));

        $after = Order::query()->findOrFail($id);
        $this->assertEquals($before->updated_at, $after->updated_at);
        $this->assertEquals($before->paid_at, $after->paid_at);
        $this->assertSame($eventsBefore, WebhookEvent::query()->count());
    }

    public function test_only_valid_transitions_are_offered_and_accepted(): void
    {
        $this->post('/admin/test/orders');
        $id = Order::query()->firstOrFail()->id;
        $this->post("/admin/test/orders/{$id}/status/cancelled");

        $this->get("/admin/orders/{$id}")->assertDontSee('status/shipped', false)->assertDontSee('status/paid', false);

        $this->post("/admin/test/orders/{$id}/status/shipped")
            ->assertSessionHas('flash', fn (string $m) => str_contains($m, 'недопустим'));

        $order = Order::query()->findOrFail($id);
        $this->assertSame(OrderStatusEnum::Cancelled, $order->status);
        $this->assertSame(
            ['created', 'cancelled'],
            $order->history->map(fn ($h) => $h->status->value)->all(),
        );
    }

    public function test_every_status_offers_its_normal_next_steps(): void
    {
        $this->assertSame([OrderStatusEnum::Paid, OrderStatusEnum::Cancelled], OrderStatusEnum::Created->nextStatuses());
        $this->assertSame([OrderStatusEnum::Shipped, OrderStatusEnum::Cancelled], OrderStatusEnum::Paid->nextStatuses());
        $this->assertSame([OrderStatusEnum::Delivered], OrderStatusEnum::Shipped->nextStatuses());
        $this->assertSame([], OrderStatusEnum::Delivered->nextStatuses());
        $this->assertSame([], OrderStatusEnum::Cancelled->nextStatuses());
    }
}
