<?php

namespace App\Validators;

use App\Enums\TicketStatus;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TicketValidator
{
    /**
     * Giá trị X-Simulate được phép chuyển tiếp xuống Inventory.
     */
    public const SIMULATE_MODES = ['fail', 'slow', 'commit-then-timeout'];

    /**
     * @param array $input
     * @return \Illuminate\Validation\Validator
     */
    public function storeValidator(array $input)
    {
        return Validator::make($input, [
            'idempotency_key' => 'required|string|max:64',
            'serial' => 'required|string|max:64',
            'customer' => 'required|string|max:255',
            'phone' => ['required', 'string', 'regex:/^[0-9+\-\s]{8,20}$/'],
            'issue' => 'required|string|max:2000',
            'part_id' => 'required|integer|min:1',
            'qty' => 'required|integer|min:1|max:100',
            'simulate' => ['nullable', Rule::in(self::SIMULATE_MODES)]
        ], [
            'serial.required' => 'Vui lòng chọn thiết bị.',
            'customer.required' => 'Vui lòng nhập tên khách hàng.',
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'phone.regex' => 'Số điện thoại không hợp lệ.',
            'issue.required' => 'Vui lòng mô tả lỗi.',
            'part_id.required' => 'Vui lòng chọn linh kiện cần thay.',
            'qty.min' => 'Số lượng phải lớn hơn 0.',
            'simulate.in' => 'Chế độ mô phỏng không hợp lệ.'
        ]);
    }

    /**
     * @param array $input
     * @return \Illuminate\Validation\Validator
     */
    public function statusValidator(array $input)
    {
        return Validator::make($input, [
            'status' => ['required', Rule::in(array_column(TicketStatus::cases(), 'value'))],
            'simulate' => ['nullable', Rule::in(self::SIMULATE_MODES)]
        ], [
            'status.required' => 'Thiếu trạng thái mới.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'simulate.in' => 'Chế độ mô phỏng không hợp lệ.'
        ]);
    }

    /**
     * @param array $input
     * @return \Illuminate\Validation\Validator
     */
    public function simulateValidator(array $input)
    {
        return Validator::make($input, [
            'simulate' => ['nullable', Rule::in(self::SIMULATE_MODES)]
        ], [
            'simulate.in' => 'Chế độ mô phỏng không hợp lệ.'
        ]);
    }

    /**
     * @param array $input
     * @return \Illuminate\Validation\Validator
     */
    public function filterValidator(array $input)
    {
        return Validator::make($input, [
            'status' => ['nullable', Rule::in(array_column(TicketStatus::cases(), 'value'))]
        ]);
    }
}
