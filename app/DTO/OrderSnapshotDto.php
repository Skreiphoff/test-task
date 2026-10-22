<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enums\OrderStatusEnum;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;

final readonly class OrderSnapshotDto
{
    // Fits decimal(12,2); a larger value would be a database overflow, i.e. a retried 500.
    private const MONEY = '/^-?\d{1,10}\.\d{2}$/';

    /** @param list<OrderItemDto> $items */
    public function __construct(
        public string $id,
        public string $number,
        public OrderStatusEnum $status,
        public string $currency,
        public string $customerEmail,
        public string $customerName,
        public ?string $customerPhone,
        public array $items,
        public string $deliveryPrice,
        public string $discount,
        public string $total,
        public ?ShipmentDto $shipment,
        public ?CarbonImmutable $paidAt,
        public ?CarbonImmutable $cancelledAt,
        public ?string $cancelReason,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(string $prefix = ''): array
    {
        $rules = [
            'id' => ['required', 'string', 'max:255'],
            'number' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::enum(OrderStatusEnum::class)],
            'currency' => ['required', 'string', 'size:3'],
            'customer' => ['required', 'array'],
            'customer.email' => ['required', 'string', 'max:255'],
            'customer.name' => ['required', 'string', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:255'],
            'items' => ['present', 'array', 'max:500'],
            'items.*.sku' => ['required', 'string', 'max:255'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.price' => ['required', 'string', 'regex:'.self::MONEY],
            'delivery_price' => ['required', 'string', 'regex:'.self::MONEY],
            'discount' => ['required', 'string', 'regex:'.self::MONEY],
            'total' => ['required', 'string', 'regex:'.self::MONEY],
            'shipment' => ['nullable', 'array'],
            'shipment.carrier' => ['nullable', 'string', 'max:255'],
            'shipment.tracking_number' => ['nullable', 'string', 'max:255'],
            'shipment.tracking_url' => ['nullable', 'url:http,https', 'max:2048'],
            'paid_at' => ['nullable', 'date'],
            'cancelled_at' => ['nullable', 'date'],
            'cancel_reason' => ['nullable', 'string', 'max:1000'],
            'created_at' => ['required', 'date'],
            'updated_at' => ['required', 'date'],
        ];

        return array_combine(
            array_map(fn (string $key): string => $prefix.$key, array_keys($rules)),
            $rules,
        );
    }

    /** @param array<string, mixed> $data validated according to {@see rules()} */
    public static function fromValidated(array $data): self
    {
        return new self(
            id: $data['id'],
            number: $data['number'],
            status: OrderStatusEnum::from($data['status']),
            currency: $data['currency'],
            customerEmail: $data['customer']['email'],
            customerName: $data['customer']['name'],
            customerPhone: $data['customer']['phone'] ?? null,
            items: array_values(array_map(OrderItemDto::fromValidated(...), $data['items'])),
            deliveryPrice: $data['delivery_price'],
            discount: $data['discount'],
            total: $data['total'],
            shipment: isset($data['shipment']) ? ShipmentDto::fromValidated($data['shipment']) : null,
            paidAt: self::instant($data['paid_at'] ?? null),
            cancelledAt: self::instant($data['cancelled_at'] ?? null),
            cancelReason: $data['cancel_reason'] ?? null,
            createdAt: self::instant($data['created_at']),
            updatedAt: self::instant($data['updated_at']),
        );
    }

    /**
     * Dates are normalized to UTC: the contract only requires the same instant, not the original offset.
     *
     * @return array<string, mixed>
     */
    public function orderAttributes(): array
    {
        return [
            'number' => $this->number,
            'status' => $this->status->value,
            'currency' => $this->currency,
            'customer_email' => $this->customerEmail,
            'customer_name' => $this->customerName,
            'customer_phone' => $this->customerPhone,
            'delivery_price' => $this->deliveryPrice,
            'discount' => $this->discount,
            'total' => $this->total,
            'shipment_carrier' => $this->shipment?->carrier,
            'shipment_tracking_number' => $this->shipment?->trackingNumber,
            'shipment_tracking_url' => $this->shipment?->trackingUrl,
            'paid_at' => $this->paidAt,
            'cancelled_at' => $this->cancelledAt,
            'cancel_reason' => $this->cancelReason,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function transitionTime(): CarbonImmutable
    {
        return match ($this->status) {
            OrderStatusEnum::Created => $this->createdAt,
            OrderStatusEnum::Paid => $this->paidAt ?? $this->updatedAt,
            OrderStatusEnum::Cancelled => $this->cancelledAt ?? $this->updatedAt,
            default => $this->updatedAt,
        };
    }

    private static function instant(?string $value): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::parse($value)->utc();
    }
}
