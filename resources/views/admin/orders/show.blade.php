@extends('admin.layout')

@section('title', 'Заказ '.$order->number)

@section('content')
    <p><a href="{{ route('admin.orders.index') }}">← К списку</a></p>

    <div class="header-row">
        <h1>Заказ {{ $order->number }}</h1>
        <x-status-badge :status="$order->status" />
    </div>

    @if (config('app.admin_test_tools'))
        <div class="tools">
            @foreach ($order->status->nextStatuses() as $next)
                <form method="post" action="{{ route('admin.test.transition', [$order->id, $next->value]) }}">
                    @csrf
                    <button type="submit">→ {{ $next->value }}</button>
                </form>
            @endforeach
            <form method="post" action="{{ route('admin.test.duplicate', $order->id) }}">
                @csrf
                <button class="secondary" type="submit">Дубль последнего события</button>
            </form>
            <form method="post" action="{{ route('admin.test.stale', $order->id) }}">
                @csrf
                <button class="secondary" type="submit">Устаревший снимок</button>
            </form>
        </div>
    @endif

    <div class="card">
        <dl>
            <dt>ID в MarketHub</dt>
            <dd x-data="{ copied: false }">
                <code>{{ $order->id }}</code>
                <button type="button" class="secondary copy"
                        @click="navigator.clipboard?.writeText(@js($order->id)); copied = true; setTimeout(() => copied = false, 1500)">
                    <span x-show="!copied">Копировать</span><span x-show="copied" x-cloak>Скопировано</span>
                </button>
            </dd>
            <dt>Покупатель</dt>
            <dd>{{ $order->customer_name }}<br><small>{{ $order->customer_email }} · {{ $order->customer_phone ?? 'телефон не указан' }}</small></dd>
            <dt>Создан</dt>
            <dd><x-datetime :value="$order->created_at" /></dd>
            <dt>Обновлён</dt>
            <dd><x-datetime :value="$order->updated_at" /></dd>
            <dt>Оплачен</dt>
            <dd><x-datetime :value="$order->paid_at" /></dd>
            @if ($order->hasShipment())
                <dt>Доставка</dt>
                <dd>
                    {{ $order->shipment_carrier }}
                    @if ($order->shipment_tracking_number)
                        · {{ $order->shipment_tracking_number }}
                    @endif
                    @if ($order->shipment_tracking_url)
                        · <a href="{{ $order->shipment_tracking_url }}" rel="noopener noreferrer" target="_blank">отследить</a>
                    @endif
                </dd>
            @endif
            @if ($order->cancelled_at)
                <dt>Отменён</dt>
                <dd><x-datetime :value="$order->cancelled_at" /><br><small>{{ $order->cancel_reason }}</small></dd>
            @endif
        </dl>
    </div>

    <h3>Позиции</h3>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>SKU</th>
                <th>Название</th>
                <th class="num">Кол-во</th>
                <th class="num">Цена</th>
                <th class="num">Сумма</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->sku }}</td>
                    <td>{{ $item->name }}</td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">{{ $item->price }}</td>
                    <td class="num">{{ $item->subtotal() }}</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
            <tr><td colspan="4" class="num">Доставка</td><td class="num">{{ $order->delivery_price }}</td></tr>
            <tr><td colspan="4" class="num">Скидка</td><td class="num">@if (bccomp($order->discount, '0', 2) > 0)−@endif{{ $order->discount }}</td></tr>
            <tr><th colspan="4" class="num">Итого, {{ $order->currency }}</th><th class="num">{{ $order->total }}</th></tr>
            </tfoot>
        </table>
    </div>

    <h3>События MarketHub</h3>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Получено</th>
                <th>Тип</th>
                <th>ID события</th>
                <th>Результат</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($order->webhookEvents as $event)
                <tr>
                    <td><x-datetime :value="$event->received_at" /></td>
                    <td>{{ $event->type }}</td>
                    <td><small>{{ $event->id }}</small></td>
                    <td><span class="badge badge-{{ $event->outcome->value }}">{{ $event->outcome->value }}</span></td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">Событий нет.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <h3>История статусов</h3>
    <div class="card">
        <ol class="timeline">
            @foreach ($order->history as $entry)
                <li>
                    <x-status-badge :status="$entry->status" />
                    <x-datetime :value="$entry->at" />
                    <br><small>событие {{ $entry->event_id }}</small>
                </li>
            @endforeach
        </ol>
    </div>
@endsection
