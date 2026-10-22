<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListOrdersRequest;
use App\Http\Resources\OrderListItemResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function index(ListOrdersRequest $request): AnonymousResourceCollection
    {
        $query = Order::query()
            ->when($request->validated('status'), fn ($q, string $status) => $q->where('status', $status))
            ->when($request->validated('email'), fn ($q, string $email) => $q->whereRaw(
                'LOWER(customer_email) = ?',
                [mb_strtolower(trim($email))],
            ))
            // `id` keeps the order stable for equal timestamps, so pages never overlap.
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return OrderListItemResource::collection(
            $query->paginate((int) $request->validated('per_page', 20)),
        );
    }

    public function show(string $id): OrderResource
    {
        return new OrderResource(
            Order::query()->with(['items', 'history'])->findOrFail($id),
        );
    }
}
