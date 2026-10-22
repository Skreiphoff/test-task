<?php

declare(strict_types=1);

namespace App\Services\Marketplace;

use App\Enums\OrderStatusEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\WebhookEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class TestEventFactory
{
    private const CATALOG = [
        ['sku' => 'TSHIRT-BLK-M', 'name' => 'Футболка базовая, чёрная, M', 'price' => '1290.00'],
        ['sku' => 'CAP-NVY', 'name' => 'Бейсболка Classic, синяя', 'price' => '890.50'],
        ['sku' => 'MUG-WHT', 'name' => 'Кружка белая', 'price' => '649.00'],
        ['sku' => 'BAG-GRN', 'name' => 'Сумка-шоппер, зелёная', 'price' => '1150.00'],
    ];

    /** @return array<string, mixed> */
    public function newOrder(): array
    {
        $suffix = Str::upper(Str::random(8));
        $now = CarbonImmutable::now()->utc();

        $items = [];
        foreach (collect(self::CATALOG)->shuffle()->take(random_int(1, 3)) as $product) {
            $items[] = [...$product, 'quantity' => random_int(1, 3)];
        }

        $deliveryPrice = '349.00';
        $discount = '0.00';
        $sum = '0.00';
        foreach ($items as $item) {
            $sum = bcadd($sum, bcmul($item['price'], (string) $item['quantity'], 2), 2);
        }

        return $this->envelope('order.created', [
            'id' => "ord_{$suffix}",
            'number' => 'MH-'.random_int(1000000, 9999999),
            'status' => OrderStatusEnum::Created->value,
            'currency' => 'RUB',
            'customer' => [
                'email' => 'test.'.Str::lower($suffix).'@example.com',
                'name' => 'Тест Покупатель',
                'phone' => null,
            ],
            'items' => $items,
            'delivery_price' => $deliveryPrice,
            'discount' => $discount,
            'total' => bcadd($sum, bcsub($deliveryPrice, $discount, 2), 2),
            'shipment' => null,
            'paid_at' => null,
            'cancelled_at' => null,
            'cancel_reason' => null,
            'created_at' => $now->toIso8601String(),
            'updated_at' => $now->toIso8601String(),
        ]);
    }

    /** @return array<string, mixed> */
    public function transition(Order $order, OrderStatusEnum $target): array
    {
        $at = $this->nextInstant($order);
        $snapshot = $this->snapshot($order);
        $snapshot['status'] = $target->value;
        $snapshot['updated_at'] = $at->toIso8601String();

        // `??=`: keep facts that already happened, e.g. the original payment time after a rollback.
        if ($target === OrderStatusEnum::Paid) {
            $snapshot['paid_at'] ??= $at->toIso8601String();
        }

        if ($target === OrderStatusEnum::Shipped) {
            $snapshot['shipment'] ??= [
                'carrier' => 'СДЭК',
                'tracking_number' => (string) random_int(1000000000, 1999999999),
                'tracking_url' => 'https://www.cdek.ru/ru/tracking',
            ];
        }

        if ($target === OrderStatusEnum::Cancelled) {
            $snapshot['cancelled_at'] ??= $at->toIso8601String();
            $snapshot['cancel_reason'] ??= 'Тест: отмена из админки';
        }

        return $this->envelope('order.'.$target->value, $snapshot);
    }

    /** @return array<string, mixed>|null */
    public function duplicateOfLast(Order $order): ?array
    {
        return WebhookEvent::query()
            ->where('payload->data->order->id', $order->id)
            ->orderByDesc('received_at')
            ->first()?->payload;
    }

    /** @return array<string, mixed> */
    public function stale(Order $order): array
    {
        $snapshot = $this->snapshot($order);
        $snapshot['status'] = OrderStatusEnum::Created->value;
        $snapshot['updated_at'] = $order->updated_at->utc()->subHour()->toIso8601String();

        return $this->envelope('order.updated', $snapshot);
    }

    /** @return array<string, mixed> */
    private function snapshot(Order $order): array
    {
        return [
            'id' => $order->id,
            'number' => $order->number,
            'status' => $order->status->value,
            'currency' => $order->currency,
            'customer' => [
                'email' => $order->customer_email,
                'name' => $order->customer_name,
                'phone' => $order->customer_phone,
            ],
            'items' => $order->items->map(fn (OrderItem $item): array => [
                'sku' => $item->sku,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'price' => $item->price,
            ])->all(),
            'delivery_price' => $order->delivery_price,
            'discount' => $order->discount,
            'total' => $order->total,
            'shipment' => $order->hasShipment() ? [
                'carrier' => $order->shipment_carrier,
                'tracking_number' => $order->shipment_tracking_number,
                'tracking_url' => $order->shipment_tracking_url,
            ] : null,
            'paid_at' => $order->paid_at?->utc()->toIso8601String(),
            'cancelled_at' => $order->cancelled_at?->utc()->toIso8601String(),
            'cancel_reason' => $order->cancel_reason,
            'created_at' => $order->created_at->utc()->toIso8601String(),
            'updated_at' => $order->updated_at->utc()->toIso8601String(),
        ];
    }

    /** Must be newer than the stored state, otherwise the event is rejected as stale. */
    private function nextInstant(Order $order): CarbonImmutable
    {
        $now = CarbonImmutable::now()->utc()->startOfSecond();
        $minimum = CarbonImmutable::instance($order->updated_at)->utc()->addSecond();

        return $now > $minimum ? $now : $minimum;
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    private function envelope(string $type, array $order): array
    {
        return [
            'id' => 'evt_test_'.Str::upper(Str::ulid()->toBase32()),
            'type' => $type,
            'created_at' => CarbonImmutable::now()->utc()->toIso8601String(),
            'data' => ['order' => $order],
        ];
    }
}
