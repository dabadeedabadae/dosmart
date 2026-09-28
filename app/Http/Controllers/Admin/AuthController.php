<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function loginForm()
    {
        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ], [
            'username.required' => 'Введите логин.',
            'password.required' => 'Введите пароль.',
        ]);

        // Rate limiting: 5 попыток в минуту с одного IP
        $key = 'admin-login:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->withErrors([
                'username' => "Слишком много попыток. Попробуйте через {$seconds} сек.",
            ])->onlyInput('username');
        }

        // Ищем пользователя по username
        $user = User::where('username', $request->username)->first();

        if ($user && Hash::check($request->password, $user->password)) {
            RateLimiter::clear($key);
            Auth::login($user, true);
            $request->session()->regenerate();
            return redirect()->intended(route('admin.orders.index'));
        }

        RateLimiter::hit($key, 60);
        return back()->withErrors([
            'username' => 'Неверный логин или пароль.',
        ])->onlyInput('username');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }
}
