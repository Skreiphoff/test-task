<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\WebhookReceiptEnum;
use App\Http\Requests\MarketplaceWebhookRequest;
use App\Services\Marketplace\WebhookEventReceiver;
use Illuminate\Http\JsonResponse;

class MarketplaceWebhookController extends Controller
{
    public function __invoke(MarketplaceWebhookRequest $request, WebhookEventReceiver $receiver): JsonResponse
    {
        $receipt = $receiver->receive($request->toDto());

        return response()->json(
            ['status' => $receipt->value],
            $receipt === WebhookReceiptEnum::Accepted ? 202 : 200,
        );
    }
}
