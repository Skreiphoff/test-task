<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\ListOrdersQueryDto;
use App\Enums\OrderStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListOrdersRequest extends FormRequest
{
    /** Used for the HTML admin; the JSON API answers 422 and never redirects. */
    protected function getRedirectUrl(): string
    {
        return route('admin.orders.index');
    }

    public function toDto(): ListOrdersQueryDto
    {
        return ListOrdersQueryDto::fromValidated($this->validated());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(OrderStatusEnum::class)],
            'email' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
