<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\InteractsWithWebhooks;
use Tests\TestCase;

class AdminOrdersTest extends TestCase
{
    use InteractsWithWebhooks;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->sendEvent($this->event('order-paid'));
        $this->sendEvent($this->event('order-cancelled'));
    }

    public function test_list_shows_orders_and_applies_filters(): void
    {
        $this->get('/admin/orders')->assertOk()->assertSee('MH-1048213')->assertSee('MH-1048377');

        $this->get('/admin/orders?status=cancelled')->assertSee('MH-1048377')->assertDontSee('MH-1048213');
        $this->get('/admin/orders?email=ANNA.SMIRNOVA@example.com')->assertSee('MH-1048213')->assertDontSee('MH-1048377');
    }

    public function test_card_shows_items_and_status_history(): void
    {
        $this->get('/admin/orders/ord_7Q2KX9M4')
            ->assertOk()
            ->assertSee('TSHIRT-BLK-M')
            ->assertSee('4909.50')
            ->assertSeeInOrder(['created', 'paid']);

        $this->get('/admin/orders/unknown')->assertNotFound();
    }
}
