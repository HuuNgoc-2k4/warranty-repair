{{-- Chọn lỗi giả lập cho Inventory (chỉ có tác dụng khi Inventory chạy với APP_SIMULATE=true) --}}
<div>
    <label for="simulate-{{ $suffix ?? 'form' }}">Giả lập lỗi kho (chỉ để demo)</label>
    <select id="simulate-{{ $suffix ?? 'form' }}" name="simulate">
        <option value="">Không</option>
        <option value="fail" @selected(old('simulate') === 'fail')>fail - kho trả lỗi ngay</option>
        <option value="slow" @selected(old('simulate') === 'slow')>slow - kho chậm hơn timeout</option>
        <option value="commit-then-timeout" @selected(old('simulate') === 'commit-then-timeout')>commit-then-timeout - kho ghi xong rồi mới trả lời chậm</option>
    </select>
</div>
