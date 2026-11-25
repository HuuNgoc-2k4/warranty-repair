@extends('layouts.app')
@section('title', 'Đăng nhập')
@section('content')
    <div class="panel compact">
        <h1>Đăng nhập nhân viên</h1>
        <form method="POST" action="{{ url('/login') }}" class="stack">
            @csrf
            <div>
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                      >
            </div>
            <div>
                <label for="password">Mật khẩu</label>
                <input id="password" name="password" type="password" required>
            </div>
            <button class="btn primary">Đăng nhập</button>
        </form>
    </div>
@endsection
