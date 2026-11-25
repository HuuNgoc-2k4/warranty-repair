<?php

namespace App\Validators;

use Illuminate\Support\Facades\Validator;

class ReservationValidator
{
    /**
     * @param array $input
     * @return \Illuminate\Validation\Validator
     */
    public function storeValidator(array $input)
    {
        return Validator::make($input, [
            'ref' => 'required|string|max:64',
            'part_id' => 'required|integer|min:1',
            'qty' => 'required|integer|min:1|max:100',
        ], [
            'ref.required' => 'Thiếu mã tham chiếu giữ hàng.',
            'part_id.required' => 'Thiếu mã linh kiện.',
            'qty.required' => 'Thiếu số lượng.',
            'qty.min' => 'Số lượng phải lớn hơn 0.',
        ]);
    }
}
