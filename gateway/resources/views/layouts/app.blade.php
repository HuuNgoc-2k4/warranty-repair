<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@hasSection('title')@yield('title') · @endif Bảo hành &amp; sửa chữa</title>
    <link rel="stylesheet" href="{{ asset('antam/workspace.css') }}">
</head>
<body>
<a class="skip" href="#content">Đến nội dung chính</a>
<header><div class="shell topbar">
    <a class="brand" href="{{ route('home') }}"><span class="mark"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.7 20 6v5.4c0 4.6-3.2 7.9-8 10-4.8-2.1-8-5.4-8-10V6z"/><path d="m8.6 11.8 2.3 2.3 4.5-4.7"/></svg></span>Bảo hành <b>&amp; sửa chữa</b></a>
    @auth
        <nav aria-label="Điều hướng nhân viên">
            <a href="{{ route('tickets.index') }}" @if(request()->routeIs('tickets.index','tickets.show')) aria-current="page" @endif>Quản lý phiếu</a>
            @can('tickets.create')<a href="{{ route('tickets.create') }}" @if(request()->routeIs('tickets.create')) aria-current="page" @endif>Tạo phiếu</a>@endcan
            <a href="{{ route('inventory.index') }}" @if(request()->routeIs('inventory.index')) aria-current="page" @endif>Kho linh kiện</a>
        </nav>
        <form class="account" method="POST" action="{{ route('logout') }}">
            @csrf
            <span>{{ auth()->user()->name }}<small>{{ \App\Enums\UserRole::tryFrom((string) auth()->user()->role)?->label() ?? auth()->user()->role }}</small></span>
            <button>Đăng xuất</button>
        </form>
    @endauth
</div></header>
<main id="content" class="shell">
    @foreach (['success', 'warning', 'error'] as $type)
        @if (session($type))
            <div class="notice {{ $type }}" role="status">{{ session($type) }}</div>
        @endif
    @endforeach
    @if ($errors->any())
        <div class="notice error" role="alert"><ul>
            @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
        </ul></div>
    @endif
    @yield('content')
</main>
@stack('scripts')
</body>
</html>
