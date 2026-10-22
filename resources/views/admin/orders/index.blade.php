@extends('admin.layout')

@section('title', 'Заказы')

@section('content')
    <div class="header-row">
        <h1>Заказы</h1>
        @if (config('app.admin_test_tools'))
            <form method="post" action="{{ route('admin.test.create') }}">
                @csrf
                <button type="submit" class="secondary">+ Создать тестовый заказ</button>
            </form>
        @endif
    </div>

    <form class="filters" method="get" x-data>
        <select name="status" aria-label="Статус" @change="$root.requestSubmit()">
            <option value="">Все статусы</option>
            @foreach ($statuses as $s)
                <option value="{{ $s->value }}" @selected($status === $s->value)>{{ $s->value }}</option>
            @endforeach
        </select>

        <input type="text" name="email" value="{{ $email }}" placeholder="Email покупателя" aria-label="Email покупателя">

        <select name="per_page" aria-label="На странице" @change="$root.requestSubmit()">
            @foreach ([10, 20, 50, 100] as $size)
                <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }} на странице</option>
            @endforeach
        </select>

        <button type="submit">Найти</button>

        @if ($status || $email)
            <a class="button secondary" href="{{ route('admin.orders.index', ['per_page' => $perPage]) }}">Сбросить</a>
        @endif
    </form>

    <div class="table-wrap">
        @if ($orders->isEmpty())
            <div class="empty">Заказов по этим условиям нет.</div>
        @else
            <table>
                <thead>
                <tr>
                    <th>Номер</th>
                    <th>Статус</th>
                    <th>Покупатель</th>
                    <th class="num">Сумма</th>
                    <th>Создан</th>
                    <th class="hide-sm">Обновлён</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($orders as $order)
                    <tr>
                        <td><a href="{{ route('admin.orders.show', $order->id) }}">{{ $order->number }}</a></td>
                        <td><x-status-badge :status="$order->status" /></td>
                        <td>{{ $order->customer_name }}<br><small>{{ $order->customer_email }}</small></td>
                        <td class="num">{{ $order->total }} {{ $order->currency }}</td>
                        <td><x-datetime :value="$order->created_at" /></td>
                        <td class="hide-sm"><x-datetime :value="$order->updated_at" /></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <p class="summary">
        @if ($orders->total() > 0)
            Показано {{ $orders->firstItem() }}–{{ $orders->lastItem() }} из {{ $orders->total() }}
        @endif
    </p>

    {{ $orders->links('admin.pagination') }}
@endsection
