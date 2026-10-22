<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTO\WebhookResultDto;
use App\Enums\OrderStatusEnum;
use App\Models\Order;
use App\Services\Marketplace\TestEventFactory;
use App\Services\Marketplace\TestWebhookSender;
use Illuminate\Http\RedirectResponse;

class AdminTestToolsController extends Controller
{
    public function __construct(
        private readonly TestEventFactory $events,
        private readonly TestWebhookSender $sender,
    ) {}

    public function createOrder(): RedirectResponse
    {
        $event = $this->events->newOrder();
        $result = $this->sender->send($event);

        // The order is created by the worker, so its card may not exist yet.
        $orderId = $event['data']['order']['id'];

        return $this->done($result, Order::query()->whereKey($orderId)->exists()
            ? route('admin.orders.show', $orderId)
            : route('admin.orders.index'));
    }

    public function transition(string $id, OrderStatusEnum $status): RedirectResponse
    {
        $order = Order::query()->with(['items', 'history'])->findOrFail($id);

        if ($order->status === $status) {
            return back()->with('flash', "Заказ уже в статусе {$status->value}, событие не отправлено.");
        }

        if (! in_array($status, $order->status->nextStatuses(), true)) {
            return back()->with('flash', "Переход {$order->status->value} → {$status->value} недопустим, событие не отправлено.");
        }

        return $this->done(
            $this->sender->send($this->events->transition($order, $status)),
            route('admin.orders.show', $id),
            "Событие order.{$status->value}",
        );
    }

    public function duplicate(string $id): RedirectResponse
    {
        $order = Order::query()->findOrFail($id);
        $event = $this->events->duplicateOfLast($order);

        if ($event === null) {
            return back()->with('flash', 'Нет событий для повтора.');
        }

        return $this->done($this->sender->send($event), route('admin.orders.show', $id), 'Повтор последнего события');
    }

    public function stale(string $id): RedirectResponse
    {
        $order = Order::query()->with('items')->findOrFail($id);

        return $this->done(
            $this->sender->send($this->events->stale($order)),
            route('admin.orders.show', $id),
            'Устаревший снимок',
        );
    }

    private function done(WebhookResultDto $result, string $to, string $label = 'Создан тестовый заказ'): RedirectResponse
    {
        $outcome = $result->isOk()
            ? 'ответ: '.($result->receipt()?->value ?? '?').'. Результат обработки смотрите в блоке «События» заказа'
            : 'ошибка HTTP '.$result->httpStatus.': '.($result->body['message'] ?? '');

        return redirect($to)->with('flash', "{$label}, {$outcome}.");
    }
}
