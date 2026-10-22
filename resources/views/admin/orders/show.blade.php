@extends('admin.layout')

@section('title', 'Заказ '.$order->number)

@section('content')
    <p><a href="{{ route('admin.orders.index') }}">← К списку</a></p>
    <h2>Заказ {{ $order->number }} <span class="badge badge-{{ $order->status->value }}">{{ $order->status->value }}</span></h2>

    @if (config('app.admin_test_tools'))
        <div class="tools">
            @foreach (['paid' => 'Оплачен', 'shipped' => 'Передан в доставку', 'delivered' => 'Доставлен', 'cancelled' => 'Отменён'] as $to => $label)
                <form method="post" action="{{ route('admin.test.transition', [$order->id, $to]) }}">@csrf<button type="submit">→ {{ $label }}</button></form>
            @endforeach
            <form method="post" action="{{ route('admin.test.duplicate', $order->id) }}">@csrf<button class="secondary" type="submit">Дубль последнего события</button></form>
            <form method="post" action="{{ route('admin.test.stale', $order->id) }}">@csrf<button class="secondary" type="submit">Устаревший снимок</button></form>
        </div>
    @endif

    <div class="card">
        <dl>
            <dt>ID MarketHub</dt><dd>{{ $order->id }}</dd>
            <dt>Покупатель</dt><dd>{{ $order->customer_name }}, {{ $order->customer_email }}, {{ $order->customer_phone ?? '—' }}</dd>
            <dt>Создан / обновлён (UTC)</dt><dd>{{ $order->created_at->utc()->format('Y-m-d H:i:s') }} / {{ $order->updated_at->utc()->format('Y-m-d H:i:s') }}</dd>
            <dt>Оплачен (UTC)</dt><dd>{{ $order->paid_at?->utc()->format('Y-m-d H:i:s') ?? '—' }}</dd>
            @if ($order->hasShipment())
                <dt>Доставка</dt><dd>{{ $order->shipment_carrier }}, {{ $order->shipment_tracking_number }}
                    @if ($order->shipment_tracking_url) (<a href="{{ $order->shipment_tracking_url }}">трек</a>) @endif</dd>
            @endif
            @if ($order->cancelled_at)
                <dt>Отменён (UTC)</dt><dd>{{ $order->cancelled_at->utc()->format('Y-m-d H:i:s') }}: {{ $order->cancel_reason }}</dd>
            @endif
        </dl>
    </div>

    <h3>Позиции</h3>
    <table>
        <thead><tr><th>SKU</th><th>Название</th><th class="num">Кол-во</th><th class="num">Цена</th><th class="num">Сумма</th></tr></thead>
        <tbody>
        @foreach ($order->items as $item)
            <tr>
                <td>{{ $item->sku }}</td><td>{{ $item->name }}</td>
                <td class="num">{{ $item->quantity }}</td><td class="num">{{ $item->price }}</td><td class="num">{{ $item->subtotal() }}</td>
            </tr>
        @endforeach
        <tr><td colspan="4" class="num">Доставка</td><td class="num">{{ $order->delivery_price }}</td></tr>
        <tr><td colspan="4" class="num">Скидка</td><td class="num">{{ $order->discount }}</td></tr>
        <tr><th colspan="4" class="num">Итого, {{ $order->currency }}</th><th class="num">{{ $order->total }}</th></tr>
        </tbody>
    </table>

    <h3>История статусов</h3>
    <table>
        <thead><tr><th>Статус</th><th>Время (UTC)</th><th>Событие</th></tr></thead>
        <tbody>
        @foreach ($order->history as $entry)
            <tr>
                <td><span class="badge badge-{{ $entry->status->value }}">{{ $entry->status->value }}</span></td>
                <td>{{ $entry->at->utc()->format('Y-m-d H:i:s') }}</td>
                <td><small>{{ $entry->event_id }}</small></td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endsection
