@extends('layouts.app')
@section('title', 'Không có quyền')
@section('content')
    <div class="panel compact">
        <h1>Không có quyền</h1>
        <p>Vai trò của bạn không được phép thực hiện thao tác này.</p>
        <a href="{{ route('tickets.index') }}" class="btn primary">Về danh sách phiếu</a>
    </div>
@endsection
