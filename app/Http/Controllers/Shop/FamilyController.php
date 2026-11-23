<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Prisoner;
use App\Services\PrisonerAccounts;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Родственник по коду из кабинета осуждённого видит баланс и лимит и просит пополнить баланс. */
class FamilyController extends Controller
{
    private const COOKIE = 'dosmart_family';

    // Родственник вводит код один раз — телефон помнит вход 30 дней (cookie шифруется Laravel).
    private const REMEMBER_MINUTES = 60 * 24 * 30;

    public function form(Request $request)
    {
        if ($this->prisoner($request)) {
            return redirect()->route('family.show');
        }

        return $this->noStore(response()->view('shop.family-login'));
    }

    public function enter(Request $request)
    {
        $data = $request->validate([
            'surname' => 'required|string|max:100',
            'code' => 'required|string|max:20',
        ], ['surname.required' => 'Введите фамилию родственника.', 'code.required' => 'Введите код из 6 цифр.']);
        $hash = Prisoner::familyCodeHash($data['code']);
        $prisoner = strlen(preg_replace('/\D/', '', $data['code'])) === 6 ? Prisoner::where('family_code_hash', $hash)->first() : null;
        if (! $prisoner || ! $prisoner->hasSurname($data['surname'])) {
            throw ValidationException::withMessages(['code' => 'Фамилия или код не подходят. Попросите родственника открыть «Настройки» в кабинете Dosmart и продиктовать код.']);
        }
        $value = json_encode(['id' => $prisoner->id, 'hash' => $hash], JSON_THROW_ON_ERROR);

        return redirect()->route('family.show')->withCookie(cookie(
            self::COOKIE, $value, $request->boolean('remember') ? self::REMEMBER_MINUTES : 0, null, null, null, true, false, 'lax',
        ));
    }

    public function show(Request $request, PrisonerAccounts $accounts)
    {
        $prisoner = $this->prisoner($request);
        if (! $prisoner) {
            return redirect()->route('family.form')->withErrors(['code' => 'Введите фамилию и код ещё раз.']);
        }

        return $this->noStore(response()->view('shop.family', [
            'prisoner' => $prisoner,
            'profile' => $accounts->profile($prisoner),
            'requests' => $prisoner->depositRequests()->latest('id')->limit(5)->get(),
            'kaspiUrl' => config('pilot.kaspi_url'),
        ]));
    }

    public function deposit(Request $request)
    {
        $prisoner = $this->prisoner($request);
        abort_unless($prisoner, 403, 'Введите код ещё раз.');
        $phone = '+'.preg_replace('/\D/', '', (string) $request->input('sender_phone'));
        $request->merge(['sender_phone' => $phone]);
        $data = $request->validate([
            'sender_name' => 'required|string|min:3|max:255',
            'sender_phone' => ['required', 'regex:/^\+7[0-9]{10}$/'],
            'amount' => 'required|integer|min:500|max:1000000',
            'consent' => 'accepted',
        ], [
            'sender_phone.regex' => 'Укажите номер WhatsApp в формате +7 705 621 8748.',
            'consent.accepted' => 'Подтвердите согласие на обработку данных.',
            'amount.min' => 'Минимальная сумма — 500 ₸.',
        ]);
        $deposit = $prisoner->depositRequests()->create([
            'sender_name' => trim($data['sender_name']), 'sender_phone' => $data['sender_phone'],
            'amount_minor' => PrisonerAccounts::minor($data['amount']),
        ]);

        return redirect()->route('family.show')->with('deposit', $deposit->id);
    }

    public function logout()
    {
        return redirect()->route('family.form')->withoutCookie(self::COOKIE);
    }

    /** Доступ пропадает, когда осуждённый выдал новый код или отключил его. */
    private function prisoner(Request $request): ?Prisoner
    {
        $access = json_decode((string) $request->cookie(self::COOKIE), true);
        if (! is_array($access)) {
            return null;
        }
        $prisoner = Prisoner::with('institution')->find($access['id'] ?? 0);

        return $prisoner && $prisoner->family_code_hash !== null && hash_equals($prisoner->family_code_hash, (string) ($access['hash'] ?? ''))
            ? $prisoner : null;
    }

    private function noStore($response)
    {
        return $response->header('Cache-Control', 'private, no-store');
    }
}
