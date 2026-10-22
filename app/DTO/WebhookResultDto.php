<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enums\ProcessingOutcomeEnum;

final readonly class WebhookResultDto
{
    /** @param array<string, mixed> $body */
    public function __construct(
        public int $httpStatus,
        public array $body,
    ) {}

    public function isOk(): bool
    {
        return $this->httpStatus === 200;
    }

    public function outcome(): ?ProcessingOutcomeEnum
    {
        return ProcessingOutcomeEnum::tryFrom((string) ($this->body['status'] ?? ''));
    }
}
