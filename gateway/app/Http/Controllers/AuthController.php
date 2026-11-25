<?php

namespace App\Http\Controllers;

use App\Validators\AuthValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * @param AuthValidator $authValidator
     */
    public function __construct(protected AuthValidator $authValidator)
    {
    }

    /**
     * @return View
     */
    public function showLogin(): View
    {
        return view('auth.login');
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function login(Request $request): RedirectResponse
    {
        // 1. Validate dữ liệu
        $data = $request->only(['email', 'password']);
        $validator = $this->authValidator->loginValidator($data);
        if ($validator->fails()) {
            return back()->withInput($request->only('email'))->withErrors($validator);
        }

        // 2. Đăng nhập
        if (!Auth::attempt($data, $request->boolean('remember'))) {
            return back()->withInput($request->only('email'))->with('error', 'Email hoặc mật khẩu không đúng.');
        }

        // 3. Đổi session id chống session fixation rồi vào trang chính
        $request->session()->regenerate();

        return redirect()->intended(route('tickets.index'));
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
