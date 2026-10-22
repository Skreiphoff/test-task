<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Заказы') · Админка</title>
    <style>
        body { font: 14px/1.5 system-ui, sans-serif; margin: 0; background: #f5f6f8; color: #1d2330; }
        header { background: #1d2330; padding: 12px 24px; }
        header a { color: #fff; text-decoration: none; font-weight: 600; }
        main { max-width: 1100px; margin: 24px auto; padding: 0 24px; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 6px; overflow: hidden; }
        th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #e6e8ee; }
        th { background: #eef0f5; font-weight: 600; }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
        form.filters { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
        input, select, button { font: inherit; padding: 6px 10px; border: 1px solid #c8ccd6; border-radius: 4px; background: #fff; }
        button { background: #2f5fd0; color: #fff; border-color: #2f5fd0; cursor: pointer; }
        .badge { padding: 2px 8px; border-radius: 10px; font-size: 12px; background: #e3e6ee; }
        .badge-paid { background: #d8f0dd; } .badge-shipped { background: #d6e6fb; }
        .badge-delivered { background: #c9ecec; } .badge-cancelled { background: #f8d9d9; }
        .card { background: #fff; border-radius: 6px; padding: 16px 20px; margin-bottom: 16px; }
        dl { display: grid; grid-template-columns: 180px 1fr; gap: 4px 12px; margin: 0; }
        dt { color: #66708a; }
        .flash { border-left: 4px solid #2f5fd0; }
        .tools { display: flex; gap: 8px; flex-wrap: wrap; margin: 12px 0; }
        .tools form { margin: 0; }
        button.secondary { background: #fff; color: #2f5fd0; }
        .pager { margin-top: 16px; display: flex; gap: 12px; align-items: center; }
    </style>
</head>
<body>
<header><a href="{{ route('admin.orders.index') }}">Заказы MarketHub</a></header>
<main>
    @if (session('flash'))<div class="card flash">{{ session('flash') }}</div>@endif
    @yield('content')
</main>
</body>
</html>
