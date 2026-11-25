{{-- Chọn lỗi giả lập cho Inventory (chỉ có tác dụng khi Inventory chạy với APP_SIMULATE=true) --}}
<div>
    <label class="block text-sm mb-1 text-gray-600" for="simulate-{{ $suffix ?? 'form' }}">Giả lập lỗi kho (chỉ để demo)</label>
    <select id="simulate-{{ $suffix ?? 'form' }}" name="simulate" class="w-full min-h-11 rounded border border-gray-300 bg-white px-3 text-sm">
        <option value="">Không</option>
        <option value="fail" @selected(old('simulate') === 'fail')>fail - kho trả lỗi ngay</option>
        <option value="slow" @selected(old('simulate') === 'slow')>slow - kho chậm hơn timeout</option>
        <option value="commit-then-timeout" @selected(old('simulate') === 'commit-then-timeout')>commit-then-timeout - kho ghi xong rồi mới trả lời chậm</option>
    </select>
</div>
