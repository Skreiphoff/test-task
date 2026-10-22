<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class IncomingEventDto
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $id,
        public string $type,
        public array $payload,
        public ?OrderSnapshotDto $snapshot,
    ) {}
}
