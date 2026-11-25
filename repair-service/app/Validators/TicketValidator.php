<?php

namespace App\Validators;

use App\Enums\TicketStatus;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TicketValidator
{
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
            'qty' => 'required|integer|min:1|max:100'
        ], [
            'idempotency_key.required' => 'Thiếu header Idempotency-Key.',
            'serial.required' => 'Thiếu số serial.',
            'customer.required' => 'Thiếu tên khách hàng.',
            'phone.required' => 'Thiếu số điện thoại.',
            'phone.regex' => 'Số điện thoại không hợp lệ.',
            'issue.required' => 'Thiếu mô tả lỗi.',
            'part_id.required' => 'Thiếu linh kiện cần thay.',
            'qty.min' => 'Số lượng phải lớn hơn 0.'
        ]);
    }

    /**
     * @param array $input
     * @return \Illuminate\Validation\Validator
     */
    public function statusValidator(array $input)
    {
        return Validator::make($input, [
            'status' => ['required', Rule::in(array_column(TicketStatus::cases(), 'value'))]
        ], [
            'status.required' => 'Thiếu trạng thái mới.',
            'status.in' => 'Trạng thái không hợp lệ.'
        ]);
    }

    /**
     * @param array $input
     * @return \Illuminate\Validation\Validator
     */
    public function reconcileValidator(array $input)
    {
        return Validator::make($input, [
            'older_than' => 'nullable|integer|min:0|max:86400'
        ], [
            'older_than.integer' => 'older_than phải là số giây.'
        ]);
    }

    /**
     * @param array $input
     * @return \Illuminate\Validation\Validator
     */
    public function indexValidator(array $input)
    {
        return Validator::make($input, [
            'status' => ['nullable', Rule::in(array_column(TicketStatus::cases(), 'value'))]
        ], [
            'status.in' => 'Trạng thái không hợp lệ.'
        ]);
    }
}
