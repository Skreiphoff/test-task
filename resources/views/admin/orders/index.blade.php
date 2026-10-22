@extends('admin.layout')

@section('title', 'Заказы')

@section('content')
    <form class="filters" method="get">
        <select name="status">
            <option value="">Все статусы</option>
            @foreach ($statuses as $s)
                <option value="{{ $s->value }}" @selected($status === $s->value)>{{ $s->value }}</option>
            @endforeach
        </select>
        <input type="text" name="email" value="{{ $email }}" placeholder="Email покупателя">
        <button type="submit">Найти</button>
    </form>

    @if (config('app.admin_test_tools'))
        <div class="tools">
            <form method="post" action="{{ route('admin.test.create') }}">@csrf<button type="submit">+ Создать тестовый заказ</button></form>
        </div>
    @endif

    <table>
        <thead>
        <tr><th>Номер</th><th>Статус</th><th>Покупатель</th><th class="num">Сумма</th><th>Создан (UTC)</th><th>Обновлён (UTC)</th></tr>
        </thead>
        <tbody>
        @forelse ($orders as $order)
            <tr>
                <td><a href="{{ route('admin.orders.show', $order->id) }}">{{ $order->number }}</a></td>
                <td><span class="badge badge-{{ $order->status->value }}">{{ $order->status->value }}</span></td>
                <td>{{ $order->customer_name }}<br><small>{{ $order->customer_email }}</small></td>
                <td class="num">{{ $order->total }} {{ $order->currency }}</td>
                <td>{{ $order->created_at->utc()->format('Y-m-d H:i:s') }}</td>
                <td>{{ $order->updated_at->utc()->format('Y-m-d H:i:s') }}</td>
            </tr>
        @empty
            <tr><td colspan="6">Заказов нет.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="pager">
        @if ($orders->previousPageUrl()) <a href="{{ $orders->previousPageUrl() }}">← Назад</a> @endif
        <span>Страница {{ $orders->currentPage() }} из {{ $orders->lastPage() }}, всего {{ $orders->total() }}</span>
        @if ($orders->nextPageUrl()) <a href="{{ $orders->nextPageUrl() }}">Вперёд →</a> @endif
    </div>
@endsection
