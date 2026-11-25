<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Bảo hành & sửa chữa')</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen">
@auth
    <header class="bg-white border-b border-gray-200">
        <div class="max-w-5xl mx-auto px-4 py-2 flex flex-wrap items-center gap-x-4 gap-y-1">
            <a href="{{ route('tickets.index') }}" class="font-semibold text-indigo-700 py-2">Bảo hành &amp; sửa chữa</a>
            <nav class="flex gap-1 text-sm">
                <a href="{{ route('tickets.index') }}" class="px-3 py-3 rounded hover:bg-gray-100">Phiếu</a>
                <a href="{{ route('tickets.create') }}" class="px-3 py-3 rounded hover:bg-gray-100">Tạo phiếu</a>
                <a href="{{ route('inventory.index') }}" class="px-3 py-3 rounded hover:bg-gray-100">Kho</a>
            </nav>
            <form method="POST" action="{{ route('logout') }}" class="ml-auto flex items-center gap-2 text-sm">
                @csrf
                <span class="text-gray-500 hidden sm:inline min-w-0 break-words">{{ auth()->user()->name }}</span>
                <button class="px-3 min-h-11 rounded border border-gray-300 hover:bg-gray-100">Đăng xuất</button>
            </form>
        </div>
    </header>
@endauth

<main class="max-w-5xl mx-auto px-4 py-4 sm:py-6">
    @foreach (['success' => 'emerald', 'warning' => 'amber', 'error' => 'red'] as $type => $color)
        @if (session($type))
            <div class="mb-4 rounded border border-{{ $color }}-300 bg-{{ $color }}-50 text-{{ $color }}-800 px-4 py-3 break-words">{{ session($type) }}</div>
        @endif
    @endforeach
    @if ($errors->any())
        <div class="mb-4 rounded border border-red-300 bg-red-50 text-red-800 px-4 py-3">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $message)
                    <li class="break-words">{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</main>
@stack('scripts')
</body>
</html>
