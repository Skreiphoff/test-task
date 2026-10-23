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

    public function test_filter_form_keeps_the_selected_values_and_offers_a_reset(): void
    {
        $this->get('/admin/orders?status=paid&email=Anna.Smirnova@example.com&per_page=50')
            ->assertOk()
            ->assertSee('value="anna.smirnova@example.com"', false)
            ->assertSee('<option value="paid" selected>', false)
            ->assertSee('<option value="50" selected>', false)
            ->assertSee('Сбросить');

        $this->get('/admin/orders')->assertDontSee('Сбросить');
    }

    public function test_empty_result_shows_a_message(): void
    {
        $this->get('/admin/orders?email=nobody@example.com')
            ->assertOk()
            ->assertSee('Заказов по этим условиям нет.')
            ->assertDontSee('MH-1048213');
    }

    public function test_pagination_keeps_filters_in_links(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->sendEvent($this->event('order-created', [
                'id' => "ord_page_{$i}",
                'number' => "MH-P{$i}",
                'created_at' => sprintf('2026-10-01T10:%02d:00+03:00', $i),
                'updated_at' => sprintf('2026-10-01T10:%02d:00+03:00', $i),
            ], "evt_page_{$i}"));
        }

        $response = $this->get('/admin/orders?status=created&per_page=10')->assertOk();

        $response->assertSee('Показано 1–10 из 12')
            ->assertSee('status=created', false)
            ->assertSee('per_page=10', false)
            ->assertSee('page=2', false)
            ->assertSee('MH-P12')
            ->assertDontSee('MH-P1<', false);

        $this->get('/admin/orders?status=created&per_page=10&page=2')->assertSee('Показано 11–12 из 12');
    }

    public function test_invalid_filter_values_are_reported_instead_of_ignored(): void
    {
        $this->from('/admin/orders/ord_7Q2KX9M4')->get('/admin/orders?per_page=500&status=bogus')
            ->assertRedirect('/admin/orders')
            ->assertSessionHasErrors(['per_page', 'status']);
    }

    public function test_card_shows_cancellation_and_payment_details(): void
    {
        $this->get('/admin/orders/ord_3HV8PW2C')
            ->assertOk()
            ->assertSee('Покупатель отказался от заказа')
            ->assertSee('Отменён');
    }

    public function test_card_shows_shipment_with_a_safe_tracking_link(): void
    {
        $this->sendEvent($this->event('order-shipped'));

        $this->get('/admin/orders/ord_7Q2KX9M4')
            ->assertOk()
            ->assertSee('СДЭК')
            ->assertSee('1098765432')
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertSeeInOrder(['created', 'paid', 'shipped']);
    }

    public function test_pages_load_the_local_assets_and_status_badges(): void
    {
        $this->get('/admin/orders')
            ->assertSee('css/admin.css', false)
            ->assertSee('js/alpine.min.js', false)
            ->assertSee('badge-paid', false)
            ->assertSee('badge-cancelled', false);

        $this->assertFileExists(public_path('js/alpine.min.js'));
        $this->assertFileExists(public_path('css/admin.css'));
    }

    public function test_root_redirects_to_the_orders_list(): void
    {
        $this->get('/')->assertRedirect('/admin/orders');
    }
}
