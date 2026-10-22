<?php

declare(strict_types=1);

namespace App\Services\Marketplace;

use App\Enums\OrderStatus;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;

/**
 * Validated full order snapshot from a MarketHub webhook (data.order).
 */
final readonly class OrderSnapshot
{
    private const MONEY = '/^-?\d+\.\d{2}$/';

    /**
     * @param list<array{sku: string, name: string, quantity: int, price: string}> $items
     * @param array{carrier: ?string, tracking_number: ?string, tracking_url: ?string}|null $shipment
     */
    public function __construct(
        public string $id,
        public string $number,
        public OrderStatus $status,
        public string $currency,
        public string $customerEmail,
        public string $customerName,
        public ?string $customerPhone,
        public array $items,
        public string $deliveryPrice,
        public string $discount,
        public string $total,
        public ?array $shipment,
        public ?CarbonImmutable $paidAt,
        public ?CarbonImmutable $cancelledAt,
        public ?string $cancelReason,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'id' => ['required', 'string', 'max:255'],
            'number' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'currency' => ['required', 'string', 'size:3'],
            'customer' => ['required', 'array'],
            'customer.email' => ['required', 'string', 'max:255'],
            'customer.name' => ['required', 'string', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:255'],
            'items' => ['present', 'array'],
            'items.*.sku' => ['required', 'string', 'max:255'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'string', 'regex:'.self::MONEY],
            'delivery_price' => ['required', 'string', 'regex:'.self::MONEY],
            'discount' => ['required', 'string', 'regex:'.self::MONEY],
            'total' => ['required', 'string', 'regex:'.self::MONEY],
            'shipment' => ['nullable', 'array'],
            'shipment.carrier' => ['nullable', 'string', 'max:255'],
            'shipment.tracking_number' => ['nullable', 'string', 'max:255'],
            'shipment.tracking_url' => ['nullable', 'string', 'max:2048'],
            'paid_at' => ['nullable', 'date'],
            'cancelled_at' => ['nullable', 'date'],
            'cancel_reason' => ['nullable', 'string', 'max:1000'],
            'created_at' => ['required', 'date'],
            'updated_at' => ['required', 'date'],
        ];
    }

    /** @param array<string, mixed> $data validated according to {@see rules()} */
    public static function fromValidated(array $data): self
    {
        $shipment = $data['shipment'] ?? null;

        return new self(
            id: $data['id'],
            number: $data['number'],
            status: OrderStatus::from($data['status']),
            currency: $data['currency'],
            customerEmail: $data['customer']['email'],
            customerName: $data['customer']['name'],
            customerPhone: $data['customer']['phone'] ?? null,
            items: array_values(array_map(fn (array $item): array => [
                'sku' => $item['sku'],
                'name' => $item['name'],
                'quantity' => (int) $item['quantity'],
                'price' => $item['price'],
            ], $data['items'])),
            deliveryPrice: $data['delivery_price'],
            discount: $data['discount'],
            total: $data['total'],
            shipment: $shipment === null ? null : [
                'carrier' => $shipment['carrier'] ?? null,
                'tracking_number' => $shipment['tracking_number'] ?? null,
                'tracking_url' => $shipment['tracking_url'] ?? null,
            ],
            paidAt: self::instant($data['paid_at'] ?? null),
            cancelledAt: self::instant($data['cancelled_at'] ?? null),
            cancelReason: $data['cancel_reason'] ?? null,
            createdAt: self::instant($data['created_at']),
            updatedAt: self::instant($data['updated_at']),
        );
    }

    /**
     * Columns of the `orders` table. Dates are UTC instants: the original offset
     * is irrelevant for the API contract, which only requires the same instant.
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
            'shipment_carrier' => $this->shipment['carrier'] ?? null,
            'shipment_tracking_number' => $this->shipment['tracking_number'] ?? null,
            'shipment_tracking_url' => $this->shipment['tracking_url'] ?? null,
            'paid_at' => $this->paidAt,
            'cancelled_at' => $this->cancelledAt,
            'cancel_reason' => $this->cancelReason,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    /** When the order entered its current status, according to the snapshot. */
    public function transitionTime(): CarbonImmutable
    {
        return match ($this->status) {
            OrderStatus::Created => $this->createdAt,
            OrderStatus::Paid => $this->paidAt ?? $this->updatedAt,
            OrderStatus::Cancelled => $this->cancelledAt ?? $this->updatedAt,
            default => $this->updatedAt,
        };
    }

    private static function instant(?string $value): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::parse($value)->utc();
    }
}
