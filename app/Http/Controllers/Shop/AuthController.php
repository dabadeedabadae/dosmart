<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function form(Request $request)
    {
        if (Auth::guard('customer')->check()) {
            return redirect()->route('shop.orders');
        }

        return view('shop.auth', ['register' => $request->routeIs('shop.register')]);
    }

    private function credentials(Request $request, bool $register): array
    {
        $request->validate(['phone' => 'required|string|max:40']);
        $phone = preg_replace('/[\s()+-]/', '', $request->phone);
        if (strlen($phone) === 11 && str_starts_with($phone, '8')) {
            $phone = '7'.substr($phone, 1);
        }
        $request->merge(['phone' => '+'.$phone]);

        return $request->validate([
            'phone' => ['required', 'regex:/^\+[1-9][0-9]{9,14}$/', ...($register ? ['unique:customers,phone'] : [])],
            'password' => $register ? 'required|string|min:8|max:128|confirmed' : 'required|string|max:128',
        ], ['phone.regex' => 'Введите номер с кодом страны, например +7 700 123 45 67.',
            'phone.unique' => 'Этот номер уже зарегистрирован. Войдите в аккаунт.',
            'password.min' => 'Пароль должен содержать не менее 8 символов.',
            'password.confirmed' => 'Пароли не совпадают.']);
    }

    public function register(Request $request)
    {
        $customer = Customer::create($this->credentials($request, true));
        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return $request->session()->has('pilot_return_code')
            ? redirect()->route('pilot.show', $request->session()->pull('pilot_return_code'))
            : redirect()->route('shop.cart');
    }

    public function login(Request $request)
    {
        if (! Auth::guard('customer')->attempt($this->credentials($request, false))) {
            throw ValidationException::withMessages(['phone' => 'Неверный телефон или пароль.']);
        }
        $request->session()->regenerate();

        return $request->session()->has('pilot_return_code')
            ? redirect()->route('pilot.show', $request->session()->pull('pilot_return_code'))
            : redirect()->route('shop.cart');
    }

    public function logout(Request $request)
    {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('shop.index');
    }

    public function orders(Request $request)
    {
        $orders = $request->user('customer')->orders()->with(['items', 'draft'])->latest()->paginate(10);

        return view('shop.orders', compact('orders'));
    }
}
