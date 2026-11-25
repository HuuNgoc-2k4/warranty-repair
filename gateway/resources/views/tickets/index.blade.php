@php use App\Enums\TicketStatus; @endphp
@extends('layouts.app')
@section('title', 'Danh sách phiếu')
@section('content')
    <div class="flex flex-wrap items-center gap-2 mb-4">
        <h1 class="text-xl font-semibold mr-auto">Phiếu tiếp nhận</h1>
        @can('tickets.reconcile')
            <form method="POST" action="{{ route('tickets.reconcile') }}">
                @csrf
                <button class="px-3 min-h-11 rounded border border-gray-300 bg-white text-sm hover:bg-gray-100" title="Tra lại Inventory cho các phiếu đang xử lý">Đối soát phiếu chờ</button>
            </form>
        @endcan
        @can('tickets.create')
            <a href="{{ route('tickets.create') }}" class="px-4 min-h-11 inline-flex items-center rounded bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">Tạo phiếu</a>
        @endcan
    </div>

    <form method="GET" class="mb-4">
        <select name="status" onchange="this.form.submit()" class="min-h-11 w-full sm:w-60 rounded border border-gray-300 bg-white px-3">
            <option value="">Tất cả trạng thái</option>
            @foreach (TicketStatus::cases() as $case)
                <option value="{{ $case->value }}" @selected($status === $case->value)>{{ $case->label() }}</option>
            @endforeach
        </select>
    </form>

    <div class="overflow-x-auto bg-white rounded-lg shadow">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100 text-left text-gray-600">
            <tr>
                <th class="px-3 py-2">#</th>
                <th class="px-3 py-2">Serial</th>
                <th class="px-3 py-2">Khách hàng</th>
                <th class="px-3 py-2">Bảo hành</th>
                <th class="px-3 py-2">Trạng thái</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($tickets as $ticket)
                @php $state = TicketStatus::tryFrom($ticket['status']); @endphp
                <tr class="border-t border-gray-100">
                    <td class="px-3 py-2"><a class="text-indigo-700 underline inline-block min-h-11 leading-[2.75rem]" href="{{ route('tickets.show', $ticket['id']) }}">#{{ $ticket['id'] }}</a></td>
                    <td class="px-3 py-2 break-words">{{ $ticket['serial'] }}</td>
                    <td class="px-3 py-2 break-words">{{ $ticket['customer'] }}</td>
                    <td class="px-3 py-2">{{ $ticket['in_warranty'] ? 'Còn' : 'Hết' }}</td>
                    <td class="px-3 py-2"><span class="px-2 py-1 rounded text-xs {{ $state?->badge() }}">{{ $state?->label() ?? $ticket['status'] }}</span></td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500">Chưa có phiếu nào.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
