@extends('layouts.app')
@section('title', 'Kho linh kiện')
@section('content')
    <h1 class="text-xl font-semibold mb-4">Tồn kho linh kiện</h1>
    <div class="overflow-x-auto bg-white rounded-lg shadow max-w-xl">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100 text-left text-gray-600">
            <tr><th class="px-3 py-2">#</th><th class="px-3 py-2">Linh kiện</th><th class="px-3 py-2 text-right">Còn lại</th></tr>
            </thead>
            <tbody>
            @forelse ($parts as $part)
                <tr class="border-t border-gray-100">
                    <td class="px-3 py-2">{{ $part['id'] }}</td>
                    <td class="px-3 py-2 break-words">{{ $part['name'] }}</td>
                    <td class="px-3 py-2 text-right {{ $part['available'] === 0 ? 'text-red-600 font-medium' : '' }}">{{ $part['available'] }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="px-3 py-6 text-center text-gray-500">Không có dữ liệu.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
