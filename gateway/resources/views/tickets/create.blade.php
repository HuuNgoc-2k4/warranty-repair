@extends('layouts.app')
@section('title', 'Tạo phiếu')
@section('content')
    <h1>Tạo phiếu tiếp nhận</h1>
    <form method="POST" action="{{ route('tickets.store') }}" class="panel ticket-form stack">
        @csrf
        {{-- Khóa chống gửi trùng: bấm đúp hay F5 sau khi submit vẫn chỉ tạo một phiếu --}}
        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

        <div>
            <label for="serial">Thiết bị</label>
            <select id="serial" name="serial" required>
                <option value="">-- Chọn thiết bị --</option>
                @foreach ($devices as $device)
                    <option value="{{ $device['serial'] }}" @selected(old('serial') === $device['serial'])>
                        {{ $device['serial'] }} - {{ $device['model'] }} ({{ $device['valid'] ? 'còn' : 'hết' }} bảo hành đến {{ $device['expires_at'] }})
                    </option>
                @endforeach
            </select>
        </div>
        <div class="field-grid">
            <div>
                <label for="customer">Khách hàng</label>
                <input id="customer" name="customer" value="{{ old('customer') }}" required maxlength="255">
            </div>
            <div>
                <label for="phone">Số điện thoại</label>
                <input id="phone" name="phone" value="{{ old('phone') }}" required inputmode="tel">
            </div>
        </div>
        <div>
            <label for="issue">Mô tả lỗi</label>
            <textarea id="issue" name="issue" rows="3" required maxlength="2000">{{ old('issue') }}</textarea>
        </div>
        <div class="field-grid">
            <div>
                <label for="part_id">Linh kiện cần thay</label>
                <select id="part_id" name="part_id" required>
                    <option value="">-- Chọn linh kiện --</option>
                    @foreach ($parts as $part)
                        <option value="{{ $part['id'] }}" @selected((string) old('part_id') === (string) $part['id'])>{{ $part['name'] }} (còn {{ $part['available'] }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="qty">Số lượng</label>
                <input id="qty" name="qty" type="number" min="1" max="100" value="{{ old('qty', 1) }}" required>
            </div>
        </div>
        @include('tickets.partials.simulate')
        <button class="btn primary">Tạo phiếu</button>
    </form>
@endsection
