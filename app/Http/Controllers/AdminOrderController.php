<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrderStatusEnum;
use App\Http\Requests\ListOrdersRequest;
use App\Models\Order;
use Illuminate\Contracts\View\View;

class AdminOrderController extends Controller
{
    public function index(ListOrdersRequest $request): View
    {
        $query = $request->toDto();

        return view('admin.orders.index', [
            'orders' => Order::query()
                ->filtered($query)
                ->paginate($query->perPage, page: $query->page)
                ->withQueryString(),
            'statuses' => OrderStatusEnum::cases(),
            'status' => $query->status?->value,
            'email' => $query->email,
            'perPage' => $query->perPage,
        ]);
    }

    public function show(string $id): View
    {
        return view('admin.orders.show', [
            'order' => Order::query()->with(['items', 'history', 'webhookEvents'])->findOrFail($id),
        ]);
    }
}
