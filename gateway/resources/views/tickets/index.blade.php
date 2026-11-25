@php use App\Enums\TicketStatus; @endphp
@extends('layouts.app')
@section('title', 'Danh sách phiếu')
@section('content')
    <section class="ticket-hero">
        <div><h1>Phiếu tiếp nhận</h1>
        <p>Theo dõi thiết bị từ lúc tiếp nhận đến khi bàn giao.</p>
        <div class="actions">
        @can('tickets.reconcile')
            <form method="POST" action="{{ route('tickets.reconcile') }}">
                @csrf
                <button  title="Tra lại Inventory cho các phiếu đang xử lý">Đối soát phiếu chờ</button>
            </form>
        @endcan
        @can('tickets.create')
            <a href="{{ route('tickets.create') }}" class="btn primary"><span aria-hidden="true">＋</span> Tạo phiếu</a>
        @endcan
        </div></div>
        <img src="{{ asset('antam/laptop.webp') }}" alt="" width="1060" height="795">
    </section>

    <section class="panel listing">
    <div class="list-head"><h2>Danh sách phiếu</h2>
    <form method="GET" class="filter">
        <label for="status">Trạng thái</label>
        <select id="status" name="status" onchange="this.form.submit()">
            <option value="">Tất cả trạng thái</option>
            @foreach (TicketStatus::cases() as $case)
                <option value="{{ $case->value }}" @selected($status === $case->value)>{{ $case->label() }}</option>
            @endforeach
        </select>
    </form></div>

    <div class="table-scroll">
        <table>
            <thead>
            <tr>
                <th>Mã phiếu</th>
                <th>Số sê-ri</th>
                <th>Khách hàng</th>
                <th>Bảo hành</th>
                <th>Trạng thái</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($tickets as $ticket)
                @php $state = TicketStatus::tryFrom($ticket['status']); @endphp
                <tr>
                    <td><a href="{{ route('tickets.show', $ticket['id']) }}">#{{ $ticket['id'] }}</a></td>
                    <td>{{ $ticket['serial'] }}</td>
                    <td>{{ $ticket['customer'] }}</td>
                    <td>{{ $ticket['in_warranty'] ? 'Còn' : 'Hết' }}</td>
                    <td><span class="badge {{ $state?->badge() }}">{{ $state?->label() ?? $ticket['status'] }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if (empty($tickets))
        <div class="empty">
            <svg aria-hidden="true" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><rect x="11" y="9" width="26" height="33" rx="5"/><rect x="18" y="6" width="12" height="7" rx="3" fill="#fff0d9"/><path d="M18 22h12m-12 7h8"/></svg>
            @if(session('error'))<h3>Chưa tải được danh sách phiếu</h3>
            @elseif($status)<h3>Không có phiếu ở trạng thái này</h3><a href="{{ route('tickets.index') }}">Xem tất cả phiếu</a>
            @else<h3>Chưa có phiếu nào.</h3><p>Phiếu bảo hành sẽ xuất hiện tại đây khi thiết bị được tiếp nhận.</p>@endif
        </div>
    @endif
    </section>
@endsection
