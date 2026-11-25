@extends('layouts.app')
@section('title', 'Tạo phiếu')
@section('content')
    <h1 class="text-xl font-semibold mb-4">Tạo phiếu tiếp nhận</h1>
    <form method="POST" action="{{ route('tickets.store') }}" class="bg-white rounded-lg shadow p-4 sm:p-5 space-y-4 max-w-2xl">
        @csrf
        {{-- Khóa chống gửi trùng: bấm đúp hay F5 sau khi submit vẫn chỉ tạo một phiếu --}}
        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

        <div>
            <label class="block text-sm mb-1" for="serial">Thiết bị</label>
            <select id="serial" name="serial" required class="w-full min-h-11 rounded border border-gray-300 bg-white px-3">
                <option value="">-- Chọn thiết bị --</option>
                @foreach ($devices as $device)
                    <option value="{{ $device['serial'] }}" @selected(old('serial') === $device['serial'])>
                        {{ $device['serial'] }} - {{ $device['model'] }} ({{ $device['valid'] ? 'còn' : 'hết' }} bảo hành đến {{ $device['expires_at'] }})
                    </option>
                @endforeach
            </select>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm mb-1" for="customer">Khách hàng</label>
                <input id="customer" name="customer" value="{{ old('customer') }}" required maxlength="255" class="w-full min-h-11 rounded border border-gray-300 px-3">
            </div>
            <div>
                <label class="block text-sm mb-1" for="phone">Số điện thoại</label>
                <input id="phone" name="phone" value="{{ old('phone') }}" required inputmode="tel" class="w-full min-h-11 rounded border border-gray-300 px-3">
            </div>
        </div>
        <div>
            <label class="block text-sm mb-1" for="issue">Mô tả lỗi</label>
            <textarea id="issue" name="issue" rows="3" required maxlength="2000" class="w-full rounded border border-gray-300 px-3 py-2">{{ old('issue') }}</textarea>
        </div>
        <div class="grid grid-cols-3 gap-4">
            <div class="col-span-2">
                <label class="block text-sm mb-1" for="part_id">Linh kiện cần thay</label>
                <select id="part_id" name="part_id" required class="w-full min-h-11 rounded border border-gray-300 bg-white px-3">
                    <option value="">-- Chọn linh kiện --</option>
                    @foreach ($parts as $part)
                        <option value="{{ $part['id'] }}" @selected((string) old('part_id') === (string) $part['id'])>{{ $part['name'] }} (còn {{ $part['available'] }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm mb-1" for="qty">Số lượng</label>
                <input id="qty" name="qty" type="number" min="1" max="100" value="{{ old('qty', 1) }}" required class="w-full min-h-11 rounded border border-gray-300 px-3">
            </div>
        </div>
        @include('tickets.partials.simulate')
        <button class="w-full sm:w-auto px-6 min-h-11 rounded bg-indigo-600 text-white font-medium hover:bg-indigo-700">Tạo phiếu</button>
    </form>
@endsection
