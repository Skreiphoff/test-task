<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Testing\TestResponse;

trait InteractsWithWebhooks
{
    private const SECRET = 'whsec_test_5f1d8c2a9b';

    /** @return array<string, mixed> */
    protected function example(string $name): array
    {
        return json_decode(
            (string) file_get_contents(base_path("task/examples/{$name}.json")),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    /**
     * Builds a variation of an example event.
     *
     * @param  array<string, mixed>  $order  overrides for data.order
     * @return array<string, mixed>
     */
    protected function event(string $example, array $order = [], ?string $id = null): array
    {
        $event = $this->example($example);
        $event['data']['order'] = array_replace($event['data']['order'], $order);

        if ($id !== null) {
            $event['id'] = $id;
        }

        return $event;
    }

    /** @param array<string, mixed> $event */
    protected function sendEvent(array $event, ?int $timestamp = null): TestResponse
    {
        return $this->sendRaw(json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $event['id'], $timestamp);
    }

    protected function sendRaw(string $body, string $eventId, ?int $timestamp = null, ?string $secret = null): TestResponse
    {
        $timestamp ??= time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret ?? self::SECRET);

        return $this->call('POST', '/api/webhooks/marketplace', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_ID' => $eventId,
            'HTTP_X_WEBHOOK_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_WEBHOOK_SIGNATURE' => 'sha256='.$signature,
        ], $body);
    }
}
