@php
    use App\Enums\TicketStatus;
    $state = TicketStatus::tryFrom($ticket['status']);
    $canStatus = $state?->next() && auth()->user()->can('tickets.status');
    $next = $canStatus ? $state->next() : null;
    $canCancel = $ticket['status'] === 'RECEIVED' && auth()->user()->can('tickets.cancel');
    $canReconcile = $ticket['status'] === 'PENDING' && auth()->user()->can('tickets.reconcile');
@endphp
@extends('layouts.app')
@section('title', 'Phiếu #'.$ticket['id'])
@section('content')
    <div class="flex flex-wrap items-center gap-2 mb-4">
        <h1 class="text-xl font-semibold">Phiếu #{{ $ticket['id'] }}</h1>
        <span class="px-2 py-1 rounded text-xs {{ $state?->badge() }}">{{ $state?->label() ?? $ticket['status'] }}</span>
        <a href="{{ route('tickets.index') }}" class="ml-auto text-sm text-indigo-700 underline">&larr; Danh sách</a>
    </div>

    <div class="bg-white rounded-lg shadow p-4 sm:p-5 mb-4">
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div class="min-w-0"><dt class="text-gray-500">Serial</dt><dd class="break-words">{{ $ticket['serial'] }}</dd></div>
            <div class="min-w-0"><dt class="text-gray-500">Bảo hành</dt><dd>{{ $ticket['in_warranty'] ? 'Còn bảo hành' : 'Hết bảo hành' }}</dd></div>
            <div class="min-w-0"><dt class="text-gray-500">Khách hàng</dt><dd class="break-words">{{ $ticket['customer'] }}</dd></div>
            <div class="min-w-0"><dt class="text-gray-500">Điện thoại</dt><dd class="break-words">{{ $ticket['phone'] }}</dd></div>
            <div class="min-w-0"><dt class="text-gray-500">Linh kiện</dt><dd>#{{ $ticket['part_id'] }} × {{ $ticket['qty'] }}</dd></div>
            @if ($ticket['failure_reason'])
                <div class="min-w-0"><dt class="text-gray-500">Lý do thất bại</dt><dd class="break-words">{{ $ticket['failure_reason'] }}</dd></div>
            @endif
            <div class="min-w-0 sm:col-span-2"><dt class="text-gray-500">Mô tả lỗi</dt><dd class="break-words whitespace-pre-line">{{ $ticket['issue'] }}</dd></div>
        </dl>
    </div>

    @if ($next || $canCancel || $canReconcile)
        <div class="bg-white rounded-lg shadow p-4 sm:p-5 mb-4 space-y-3">
            <h2 class="font-semibold">Thao tác</h2>
            @if ($canReconcile)
                <p class="text-sm text-amber-800">Chưa rõ kho đã giữ linh kiện hay chưa. Bấm đối soát để tra lại.</p>
                <form method="POST" action="{{ route('tickets.reconcile') }}">
                    @csrf
                    <button class="px-4 min-h-11 rounded bg-amber-600 text-white hover:bg-amber-700">Đối soát ngay</button>
                </form>
            @else
                <div class="flex flex-col sm:flex-row gap-3 sm:items-end">
                    @if ($next)
                        <form method="POST" action="{{ route('tickets.status', $ticket['id']) }}" class="sm:flex-1">
                            @csrf
                            <input type="hidden" name="status" value="{{ $next['status'] }}">
                            @if ($next['status'] === 'REPAIRING') @include('tickets.partials.simulate', ['suffix' => 'status']) @endif
                            <button class="mt-3 w-full sm:w-auto px-4 min-h-11 rounded bg-indigo-600 text-white hover:bg-indigo-700">{{ $next['label'] }}</button>
                        </form>
                    @endif
                    @if ($canCancel)
                        <form method="POST" action="{{ route('tickets.cancel', $ticket['id']) }}" class="sm:flex-1" onsubmit="return confirm('Hủy phiếu và trả linh kiện về kho?')">
                            @csrf
                            @include('tickets.partials.simulate', ['suffix' => 'cancel'])
                            <button class="mt-3 w-full sm:w-auto px-4 min-h-11 rounded border border-red-300 text-red-700 hover:bg-red-50">Hủy phiếu</button>
                        </form>
                    @endif
                </div>
            @endif
        </div>
    @endif

    <div class="bg-white rounded-lg shadow p-4 sm:p-5">
        <h2 class="font-semibold mb-3">Lịch sử</h2>
        <ul class="space-y-2 text-sm">
            @foreach ($ticket['events'] ?? [] as $event)
                @php $to = TicketStatus::tryFrom($event['to_status']); @endphp
                <li class="flex flex-wrap gap-x-2 min-w-0">
                    <span class="text-gray-500">{{ \Carbon\Carbon::parse($event['created_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}</span>
                    <span class="font-medium">{{ $to?->label() ?? $event['to_status'] }}</span>
                    @if ($event['note'])<span class="text-gray-600 break-words">- {{ $event['note'] }}</span>@endif
                </li>
            @endforeach
        </ul>
    </div>
@endsection
