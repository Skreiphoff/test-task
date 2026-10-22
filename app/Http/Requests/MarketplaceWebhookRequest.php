<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\IncomingEventDto;
use App\DTO\OrderSnapshotDto;
use App\Enums\MarketplaceEventTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MarketplaceWebhookRequest extends FormRequest
{
    /** @var array<string, mixed>|null */
    private ?array $payload = null;

    public function authorize(): bool
    {
        // Authenticity is checked by VerifyMarketplaceSignature.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'id' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
        ];

        // Unknown future event types are acknowledged and ignored, never rejected, to avoid a retry storm.
        if ($this->isKnownType()) {
            $rules['data.order'] = ['required', 'array'];
            $rules += OrderSnapshotDto::rules('data.order.');
        }

        return $rules;
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->payload() === []) {
                    $validator->errors()->add('body', 'The body must be a non-empty JSON object.');

                    return;
                }

                if ($this->header('X-Webhook-Id') !== ($this->payload()['id'] ?? null)) {
                    $validator->errors()->add('X-Webhook-Id', 'The header does not match the event id.');
                }
            },
        ];
    }

    public function toDto(): IncomingEventDto
    {
        $payload = $this->payload();

        // Only known types are applied: what an unknown future event means is unknown.
        $snapshot = $this->isKnownType()
            ? OrderSnapshotDto::fromValidated($this->validated('data.order'))
            : null;

        return new IncomingEventDto($payload['id'], $payload['type'], $payload, $snapshot);
    }

    /** @return array<string, mixed> */
    public function validationData(): array
    {
        return $this->payload();
    }

    /**
     * Decoded from the raw body, independent of the Content-Type header.
     *
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        if ($this->payload === null) {
            $decoded = json_decode($this->getContent(), true);
            $this->payload = is_array($decoded) ? $decoded : [];
        }

        return $this->payload;
    }

    private function isKnownType(): bool
    {
        return MarketplaceEventTypeEnum::tryFrom((string) ($this->payload()['type'] ?? '')) !== null;
    }
}
