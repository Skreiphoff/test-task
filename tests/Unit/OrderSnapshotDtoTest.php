<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\OrderItemDto;
use App\DTO\OrderSnapshotDto;
use App\DTO\ShipmentDto;
use App\Enums\OrderStatusEnum;
use PHPUnit\Framework\TestCase;

class OrderSnapshotDtoTest extends TestCase
{
    public function test_snapshot_is_built_with_typed_values_and_utc_instants(): void
    {
        $dto = OrderSnapshotDto::fromValidated($this->order('order-shipped'));

        $this->assertSame('ord_7Q2KX9M4', $dto->id);
        $this->assertSame(OrderStatusEnum::Shipped, $dto->status);
        $this->assertSame('4909.50', $dto->total);
        $this->assertContainsOnlyInstancesOf(OrderItemDto::class, $dto->items);
        $this->assertInstanceOf(ShipmentDto::class, $dto->shipment);
        $this->assertSame('СДЭК', $dto->shipment->carrier);
        $this->assertSame('UTC', $dto->createdAt->timezoneName);
        $this->assertSame(
            strtotime('2026-10-21T13:02:11+03:00'),
            $dto->createdAt->getTimestamp(),
        );
    }

    public function test_missing_shipment_and_nullable_fields_become_null(): void
    {
        $dto = OrderSnapshotDto::fromValidated($this->order('order-created'));

        $this->assertNull($dto->shipment);
        $this->assertNull($dto->paidAt);
        $this->assertNull($dto->cancelledAt);
        $this->assertNull($dto->cancelReason);
        $this->assertSame(OrderStatusEnum::Created, $dto->status);
    }

    public function test_order_attributes_use_columns_and_keep_money_as_strings(): void
    {
        $attributes = OrderSnapshotDto::fromValidated($this->order('order-shipped'))->orderAttributes();

        $this->assertSame('shipped', $attributes['status']);
        $this->assertSame('349.00', $attributes['delivery_price']);
        $this->assertSame('СДЭК', $attributes['shipment_carrier']);
        $this->assertSame('anna.smirnova@example.com', $attributes['customer_email']);
    }

    public function test_transition_time_follows_the_status(): void
    {
        $paid = OrderSnapshotDto::fromValidated($this->order('order-paid'));
        $cancelled = OrderSnapshotDto::fromValidated($this->order('order-cancelled'));
        $shipped = OrderSnapshotDto::fromValidated($this->order('order-shipped'));

        $this->assertEquals($paid->paidAt, $paid->transitionTime());
        $this->assertEquals($cancelled->cancelledAt, $cancelled->transitionTime());
        $this->assertEquals($shipped->updatedAt, $shipped->transitionTime());
    }

    /** @return array<string, mixed> */
    private function order(string $example): array
    {
        $event = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2)."/task/examples/{$example}.json"),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        return $event['data']['order'];
    }
}
