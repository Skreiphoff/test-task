<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\MarketplaceWebhookRequest;
use App\Services\Marketplace\WebhookEventProcessor;
use Illuminate\Http\JsonResponse;

class MarketplaceWebhookController extends Controller
{
    public function __invoke(MarketplaceWebhookRequest $request, WebhookEventProcessor $processor): JsonResponse
    {
        $outcome = $processor->process($request->toDto());

        return response()->json(['status' => $outcome->value]);
    }
}
