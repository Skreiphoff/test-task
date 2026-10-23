<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class ShipmentDto
{
    public function __construct(
        public ?string $carrier,
        public ?string $trackingNumber,
        public ?string $trackingUrl,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromValidated(array $data): self
    {
        return new self($data['carrier'] ?? null, $data['tracking_number'] ?? null, $data['tracking_url'] ?? null);
    }
}
