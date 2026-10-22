<?php

declare(strict_types=1);

namespace App\Services\Marketplace;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a correctly signed event through the real webhook route (middleware included),
 * exactly as MarketHub would, without needing an HTTP hop to ourselves.
 */
class TestWebhookSender
{
    public function __construct(
        private readonly Router $router,
        private readonly Container $container,
    ) {}

    /**
     * @param array<string, mixed> $event
     * @return array{status: int, body: array<string, mixed>}
     */
    public function send(array $event): array
    {
        $body = json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, (string) config('services.marketplace.webhook_secret'));

        $request = Request::create('/api/webhooks/marketplace', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_ID' => $event['id'],
            'HTTP_X_WEBHOOK_TIMESTAMP' => $timestamp,
            'HTTP_X_WEBHOOK_SIGNATURE' => 'sha256='.$signature,
        ], $body);

        // Controllers type-hinting Request resolve it from the container, so it must point at the signed request.
        $outer = $this->container->make('request');
        $this->container->instance('request', $request);

        try {
            /** @var Response $response */
            $response = $this->router->dispatch($request);
        } finally {
            $this->container->instance('request', $outer);
        }

        return [
            'status' => $response->getStatusCode(),
            'body' => json_decode((string) $response->getContent(), true) ?? [],
        ];
    }
}
