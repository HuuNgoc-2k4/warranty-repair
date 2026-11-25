@extends('layouts.app')
@section('title', 'Không có quyền')
@section('content')
    <div class="max-w-md mx-auto mt-6 bg-white rounded-lg shadow p-5 text-center">
        <h1 class="text-xl font-semibold mb-2">Không có quyền</h1>
        <p class="text-gray-600 mb-4 break-words">Vai trò của bạn không được phép thực hiện thao tác này.</p>
        <a href="{{ route('tickets.index') }}" class="inline-flex items-center px-4 min-h-11 rounded bg-indigo-600 text-white hover:bg-indigo-700">Về danh sách phiếu</a>
    </div>
@endsection
