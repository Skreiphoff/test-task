<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the MarketHub HMAC-SHA256 signature over "{timestamp}.{raw body}".
 */
class VerifyMarketplaceSignature
{
    private const SIGNATURE_PREFIX = 'sha256=';

    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.marketplace.webhook_secret');
        if ($secret === '') {
            // Fail closed: never accept unsigned traffic because of a missing config.
            return $this->reject('Webhook secret is not configured.', 500);
        }

        $timestamp = (string) $request->header('X-Webhook-Timestamp', '');
        $signature = (string) $request->header('X-Webhook-Signature', '');

        if (! ctype_digit($timestamp) || ! str_starts_with($signature, self::SIGNATURE_PREFIX)) {
            return $this->reject('Missing or malformed signature headers.');
        }

        $tolerance = (int) config('services.marketplace.webhook_tolerance');
        if (abs(time() - (int) $timestamp) > $tolerance) {
            return $this->reject('Timestamp is outside the allowed window.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);
        if (! hash_equals($expected, substr($signature, strlen(self::SIGNATURE_PREFIX)))) {
            return $this->reject('Invalid signature.');
        }

        return $next($request);
    }

    private function reject(string $message, int $status = 401): Response
    {
        return response()->json(['message' => $message], $status);
    }
}
