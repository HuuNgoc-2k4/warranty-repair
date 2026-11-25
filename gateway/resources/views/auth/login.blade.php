@extends('layouts.app')
@section('title', 'Đăng nhập')
@section('content')
    <div class="max-w-sm mx-auto mt-6 sm:mt-16 bg-white rounded-lg shadow p-5">
        <h1 class="text-xl font-semibold mb-4">Đăng nhập nhân viên</h1>
        <form method="POST" action="{{ url('/login') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm mb-1" for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                       class="w-full min-h-11 rounded border border-gray-300 px-3">
            </div>
            <div>
                <label class="block text-sm mb-1" for="password">Mật khẩu</label>
                <input id="password" name="password" type="password" required class="w-full min-h-11 rounded border border-gray-300 px-3">
            </div>
            <button class="w-full min-h-11 rounded bg-indigo-600 text-white font-medium hover:bg-indigo-700">Đăng nhập</button>
        </form>
    </div>
@endsection
