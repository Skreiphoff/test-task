<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\InteractsWithWebhooks;
use Tests\TestCase;

class OrdersApiTest extends TestCase
{
    use InteractsWithWebhooks;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_order_card_has_every_contract_key_and_exact_formats(): void
    {
        $this->sendEvent($this->event('order-paid'));
        $this->sendEvent($this->event('order-shipped'));

        $response = $this->getJson('/api/orders/ord_7Q2KX9M4')->assertOk();

        $response->assertJsonPath('data.id', 'ord_7Q2KX9M4')
            ->assertJsonPath('data.status', 'shipped')
            ->assertJsonPath('data.customer.phone', '+79161234567')
            ->assertJsonPath('data.items.0.price', '1290.00')
            ->assertJsonPath('data.items.0.subtotal', '3870.00')
            ->assertJsonPath('data.delivery_price', '349.00')
            ->assertJsonPath('data.total', '4909.50')
            ->assertJsonPath('data.shipment.carrier', 'СДЭК')
            ->assertJsonPath('data.cancelled_at', null)
            ->assertJsonPath('data.cancel_reason', null)
            ->assertJsonPath('data.history.1.status', 'paid');

        $this->assertEqualsCanonicalizing(
            [
                'id', 'number', 'status', 'customer', 'currency', 'items', 'delivery_price', 'discount', 'total',
                'shipment', 'paid_at', 'cancelled_at', 'cancel_reason', 'created_at', 'updated_at', 'history',
            ],
            array_keys($response->json('data')),
        );
    }

    public function test_dates_keep_the_marketplace_instant(): void
    {
        $this->sendEvent($this->event('order-created'));

        $data = $this->getJson('/api/orders/ord_7Q2KX9M4')->json('data');

        $this->assertSame(
            strtotime('2026-10-21T13:02:11+03:00'),
            strtotime($data['created_at']),
        );
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $data['created_at']);
        $this->assertNull($data['shipment']);
        $this->assertNull($data['paid_at']);
    }

    public function test_unknown_order_is_404(): void
    {
        $this->getJson('/api/orders/ord_missing')->assertNotFound();
    }

    public function test_list_is_newest_first_and_paginated_in_laravel_resource_format(): void
    {
        $this->seedOrders(25);

        $page1 = $this->getJson('/api/orders')->assertOk();
        $page1->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('data.0.id', 'ord_seed_24')
            ->assertJsonStructure([
                'data' => [['id', 'number', 'status', 'customer' => ['email', 'name'], 'total', 'currency', 'created_at', 'updated_at']],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
        $this->assertNull($page1->json('links.prev'));

        $this->getJson('/api/orders?page=2&per_page=10')
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.0.id', 'ord_seed_14');
    }

    public function test_filters_by_status_and_case_insensitive_email(): void
    {
        $this->seedOrders(3);
        $this->sendEvent($this->event('order-cancelled'));

        $this->getJson('/api/orders?status=cancelled')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'ord_3HV8PW2C');

        $this->getJson('/api/orders?email=I.LEBEDEV@Example.org')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer.email', 'i.lebedev@example.org');

        $this->getJson('/api/orders?status=created&email=i.lebedev@example.org')->assertJsonCount(0, 'data');
    }

    public function test_invalid_query_parameters_are_rejected(): void
    {
        $this->getJson('/api/orders?status=bogus')->assertStatus(422);
        $this->getJson('/api/orders?per_page=101')->assertStatus(422);
        $this->getJson('/api/orders?per_page=0')->assertStatus(422);
        $this->getJson('/api/orders?page=0')->assertStatus(422);
    }

    private function seedOrders(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $created = sprintf('2026-10-01T10:%02d:00+03:00', $i);
            $this->sendEvent($this->event('order-created', [
                'id' => "ord_seed_{$i}",
                'number' => "MH-{$i}",
                'created_at' => $created,
                'updated_at' => $created,
            ], "evt_seed_{$i}"));
        }
    }
}
