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
        $query = $request->toDto();

        return OrderListItemResource::collection(
            Order::query()->filtered($query)->paginate($query->perPage, page: $query->page),
        );
    }

    public function show(string $id): OrderResource
    {
        return new OrderResource(
            Order::query()->with(['items', 'history'])->findOrFail($id),
        );
    }
}
