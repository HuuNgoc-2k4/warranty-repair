@extends('layouts.app')
@section('title', 'Kho linh kiện')
@section('content')
    <h1>Tồn kho linh kiện</h1>
    <div class="panel table-scroll">
        <table>
            <thead>
            <tr><th>#</th><th>Linh kiện</th><th class="numeric">Còn lại</th></tr>
            </thead>
            <tbody>
            @forelse ($parts as $part)
                <tr>
                    <td>{{ $part['id'] }}</td>
                    <td>{{ $part['name'] }}</td>
                    <td class="numeric {{ $part['available'] === 0 ? 'depleted' : '' }}">{{ $part['available'] }}</td>
                </tr>
            @empty
                <tr><td colspan="3">Không có dữ liệu.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
