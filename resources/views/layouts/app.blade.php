<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $title ?? 'AI Builds My Website' }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<header class="top"><a href="{{ route('home') }}">✨ AI Builds My Website</a></header>
<main class="wrap">
    @if(session('ok'))<div class="note ok">{{ session('ok') }}</div>@endif
    @if(session('warn'))<div class="note warn">{{ session('warn') }}</div>@endif
    @if($errors->any())<div class="note err">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
    @yield('content')
</main>
</body>
</html>
