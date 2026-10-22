<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Marketplace\OrderSnapshot;
use App\Services\Marketplace\WebhookEventProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MarketplaceWebhookController extends Controller
{
    private const KNOWN_TYPES = [
        'order.created',
        'order.updated',
        'order.paid',
        'order.shipped',
        'order.delivered',
        'order.cancelled',
    ];

    public function __invoke(Request $request, WebhookEventProcessor $processor): JsonResponse
    {
        // The signature has already been verified against the raw body by the middleware.
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            return $this->invalid('Body is not a JSON object.');
        }

        $envelope = Validator::make($payload, [
            'id' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
        ]);

        if ($envelope->fails()) {
            return $this->invalid('Invalid event envelope.', $envelope->errors()->toArray());
        }

        if ($request->header('X-Webhook-Id') !== $payload['id']) {
            return $this->invalid('X-Webhook-Id does not match the event id.');
        }

        $isKnownType = in_array($payload['type'], self::KNOWN_TYPES, true);
        $snapshot = null;
        $order = $payload['data']['order'] ?? null;

        if (is_array($order)) {
            $validator = Validator::make($order, OrderSnapshot::rules());

            if ($validator->fails()) {
                // A malformed snapshot of a known event will never succeed on retry,
                // but an unknown future event type must not turn into a retry storm.
                if ($isKnownType) {
                    return $this->invalid('Invalid order snapshot.', $validator->errors()->toArray());
                }
            } else {
                $snapshot = OrderSnapshot::fromValidated($validator->validated());
            }
        } elseif ($isKnownType) {
            return $this->invalid('data.order is required.');
        }

        $outcome = $processor->process($payload['id'], $payload['type'], $payload, $snapshot);

        return response()->json(['status' => $outcome]);
    }

    /** @param array<string, mixed> $errors */
    private function invalid(string $message, array $errors = []): JsonResponse
    {
        return response()->json(['message' => $message, 'errors' => $errors], 422);
    }
}
