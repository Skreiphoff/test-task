<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\ListOrdersQueryDto;
use App\Enums\OrderStatusEnum;
use PHPUnit\Framework\TestCase;

class ListOrdersQueryDtoTest extends TestCase
{
    public function test_empty_query_gets_defaults(): void
    {
        $dto = ListOrdersQueryDto::fromValidated([]);

        $this->assertNull($dto->status);
        $this->assertNull($dto->email);
        $this->assertSame(1, $dto->page);
        $this->assertSame(20, $dto->perPage);
    }

    public function test_values_are_typed_and_email_is_normalized(): void
    {
        $dto = ListOrdersQueryDto::fromValidated([
            'status' => 'paid',
            'email' => '  Anna.Smirnova@Example.COM ',
            'page' => '3',
            'per_page' => '50',
        ]);

        $this->assertSame(OrderStatusEnum::Paid, $dto->status);
        $this->assertSame('anna.smirnova@example.com', $dto->email);
        $this->assertSame(3, $dto->page);
        $this->assertSame(50, $dto->perPage);
    }

    public function test_blank_email_means_no_filter(): void
    {
        $this->assertNull(ListOrdersQueryDto::fromValidated(['email' => '   '])->email);
    }
}
