<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    /**
     * Nullable fields are always emitted as null, never omitted: the contract lists them as required.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'customer' => [
                'email' => $this->customer_email,
                'name' => $this->customer_name,
                'phone' => $this->customer_phone,
            ],
            'currency' => $this->currency,
            'items' => $this->items->map(fn (OrderItem $item): array => [
                'sku' => $item->sku,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'subtotal' => $item->subtotal(),
            ])->all(),
            'delivery_price' => $this->delivery_price,
            'discount' => $this->discount,
            'total' => $this->total,
            'shipment' => $this->hasShipment() ? [
                'carrier' => $this->shipment_carrier,
                'tracking_number' => $this->shipment_tracking_number,
                'tracking_url' => $this->shipment_tracking_url,
            ] : null,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'history' => $this->history->map(fn (OrderStatusHistory $entry): array => [
                'status' => $entry->status->value,
                'at' => $entry->at->toIso8601String(),
            ])->all(),
        ];
    }
}
