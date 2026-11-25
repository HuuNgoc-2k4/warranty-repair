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
    <div class="page-title">
        <h1>Phiếu #{{ $ticket['id'] }}</h1>
        <span class="badge {{ $state?->badge() }}">{{ $state?->label() ?? $ticket['status'] }}</span>
        <a href="{{ route('tickets.index') }}" class="back">&larr; Danh sách</a>
    </div>

    <div class="panel">
        <dl class="field-grid">
            <div><dt>Serial</dt><dd>{{ $ticket['serial'] }}</dd></div>
            <div><dt>Bảo hành</dt><dd>{{ $ticket['in_warranty'] ? 'Còn bảo hành' : 'Hết bảo hành' }}</dd></div>
            <div><dt>Khách hàng</dt><dd>{{ $ticket['customer'] }}</dd></div>
            <div><dt>Điện thoại</dt><dd>{{ $ticket['phone'] }}</dd></div>
            <div><dt>Linh kiện</dt><dd>#{{ $ticket['part_id'] }} × {{ $ticket['qty'] }}</dd></div>
            @if ($ticket['failure_reason'])
                <div><dt>Lý do thất bại</dt><dd>{{ $ticket['failure_reason'] }}</dd></div>
            @endif
            <div class="wide"><dt>Mô tả lỗi</dt><dd class="multiline">{{ $ticket['issue'] }}</dd></div>
        </dl>
    </div>

    @if ($next || $canCancel || $canReconcile)
        <div class="panel stack">
            <h2>Thao tác</h2>
            @if ($canReconcile)
                <p>Chưa rõ kho đã giữ linh kiện hay chưa. Bấm đối soát để tra lại.</p>
                <form method="POST" action="{{ route('tickets.reconcile') }}">
                    @csrf
                    <button class="primary">Đối soát ngay</button>
                </form>
            @else
                <div class="field-grid">
                    @if ($next)
                        <form method="POST" action="{{ route('tickets.status', $ticket['id']) }}">
                            @csrf
                            <input type="hidden" name="status" value="{{ $next['status'] }}">
                            @if ($next['status'] === 'REPAIRING') @include('tickets.partials.simulate', ['suffix' => 'status']) @endif
                            <button class="btn primary follow-up">{{ $next['label'] }}</button>
                        </form>
                    @endif
                    @if ($canCancel)
                        <form method="POST" action="{{ route('tickets.cancel', $ticket['id']) }}" onsubmit="return confirm('Hủy phiếu và trả linh kiện về kho?')">
                            @csrf
                            @include('tickets.partials.simulate', ['suffix' => 'cancel'])
                            <button class="danger follow-up">Hủy phiếu</button>
                        </form>
                    @endif
                </div>
            @endif
        </div>
    @endif

    <div class="panel">
        <h2>Lịch sử</h2>
        <ul class="history">
            @foreach ($ticket['events'] ?? [] as $event)
                @php $to = TicketStatus::tryFrom($event['to_status']); @endphp
                <li>
                    <span>{{ \Carbon\Carbon::parse($event['created_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}</span>
                    <span>{{ $to?->label() ?? $event['to_status'] }}</span>
                    @if ($event['note'])<span>- {{ $event['note'] }}</span>@endif
                </li>
            @endforeach
        </ul>
    </div>
@endsection
