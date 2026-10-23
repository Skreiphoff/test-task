<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Заказы') · Админка</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    <script defer src="{{ asset('js/alpine.min.js') }}"></script>
</head>
<body>
<header class="topbar"><a href="{{ route('admin.orders.index') }}">Заказы MarketHub</a></header>
<main>
    @if (session('flash'))
        <div class="card flash">{{ session('flash') }}</div>
    @endif

    @if ($errors->any())
        <div class="card flash error">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @yield('content')
</main>
</body>
</html>
