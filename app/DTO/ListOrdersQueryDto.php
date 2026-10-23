<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enums\OrderStatusEnum;

final readonly class ListOrdersQueryDto
{
    public const DEFAULT_PER_PAGE = 20;

    public function __construct(
        public ?OrderStatusEnum $status,
        public ?string $email,
        public int $page,
        public int $perPage,
    ) {}

    /** @param array{status?: ?string, email?: ?string, page?: int|string|null, per_page?: int|string|null} $data */
    public static function fromValidated(array $data): self
    {
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));

        return new self(
            status: isset($data['status']) ? OrderStatusEnum::from($data['status']) : null,
            email: $email === '' ? null : $email,
            page: (int) ($data['page'] ?? 1),
            perPage: (int) ($data['per_page'] ?? self::DEFAULT_PER_PAGE),
        );
    }
}
