<?php

namespace App\Validators;

use Illuminate\Support\Facades\Validator;

class AuthValidator
{
    /**
     * @param array $input
     * @return \Illuminate\Validation\Validator
     */
    public function loginValidator(array $input)
    {
        return Validator::make($input, [
            'email' => 'required|email',
            'password' => 'required|string'
        ], [
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'password.required' => 'Vui lòng nhập mật khẩu.'
        ]);
    }
}
