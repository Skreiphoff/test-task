<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class OrderItemDto
{
    public function __construct(
        public string $sku,
        public string $name,
        public int $quantity,
        public string $price,
    ) {}

    /** @param array{sku: string, name: string, quantity: int|string, price: string} $data */
    public static function fromValidated(array $data): self
    {
        return new self($data['sku'], $data['name'], (int) $data['quantity'], $data['price']);
    }
}
