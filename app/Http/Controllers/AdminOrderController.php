<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Http\Requests\ListOrdersRequest;
use App\Models\Order;
use Illuminate\Contracts\View\View;

/**
 * Minimal read-only admin pages for manual testing; the JSON API stays the contract.
 */
class AdminOrderController extends Controller
{
    public function index(ListOrdersRequest $request): View
    {
        $orders = Order::query()
            ->filtered($request->validated('status'), $request->validated('email'))
            ->paginate((int) $request->validated('per_page', 20))
            ->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'statuses' => OrderStatus::cases(),
            'status' => $request->validated('status'),
            'email' => $request->validated('email'),
        ]);
    }

    public function show(string $id): View
    {
        return view('admin.orders.show', [
            'order' => Order::query()->with(['items', 'history'])->findOrFail($id),
        ]);
    }
}
