<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RequirePrisonerTerminal;
use App\Models\Institution;
use App\Models\Prisoner;
use App\Services\PilotCheckout;
use App\Services\PrisonerAccounts;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PrisonerAccountController extends Controller
{
    private const MESSAGES = [
        'pin.regex' => 'Пароль — ровно 6 цифр.',
        'birth_date.required' => 'Укажите дату рождения.',
        'birth_date.date_format' => 'Дата рождения указана неверно.',
        'birth_date.before' => 'Дата рождения указана неверно.',
        'birth_date.after' => 'Дата рождения указана неверно.',
        'condition.required_without' => 'Выберите условия содержания.',
        'condition.in' => 'Выберите условия содержания.',
        'pin.confirmed' => 'Пароли не совпадают.',
        'answer.min' => 'Ответ слишком короткий.',
        'full_name.min' => 'Введите фамилию и имя (не меньше 3 букв).',
        'consent.required' => 'Отметьте согласие на обработку данных.',
        'consent.accepted' => 'Отметьте согласие на обработку данных.',
    ];

    /** Меняйте версию при изменении текста согласия на планшете. */
    public const CONSENT_VERSION = 'cabinet-2026-11-24';

    /** Сотрудник вводит код учреждения из админки — планшет запоминает учреждение. */
    public function setup(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $code = preg_replace('/\D/', '', $data['code']);
        $institution = $code === '' ? null : Institution::where('tablet_code', $code)->where('is_active', true)->first();
        if (! $institution) {
            throw ValidationException::withMessages(['code' => 'Неверный код учреждения. Возьмите актуальный код в админке.']);
        }

        return $this->noStore(response()->json([
            'token' => RequirePrisonerTerminal::tabletToken($institution),
            'institution' => ['id' => $institution->id, 'name' => $institution->name],
        ]));
    }

    public function tablet(Request $request)
    {
        $institution = Institution::findOrFail($this->institutionId($request));

        return $this->noStore(response()->json(['institution' => ['id' => $institution->id, 'name' => $institution->name]]));
    }

    public function squads(Request $request)
    {
        $squads = Prisoner::where('institution_id', $this->institutionId($request))->whereNotNull('squad')
            ->distinct()->pluck('squad')->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();

        return $this->noStore(response()->json(['squads' => $squads]));
    }

    /** Человек пишет ФИО и выбирает отряд — показываем совпадения в этом отряде. */
    public function find(Request $request)
    {
        $data = $this->identity($request);
        $matches = $this->candidates($request, $data)->take(10)->map(fn (Prisoner $prisoner) => [
            'id' => $prisoner->id,
            'full_name' => $prisoner->full_name,
            'squad' => $prisoner->squad,
            // Есть ли у человека кабинет и как он восстанавливает пароль, не раскрываем:
            // по этим признакам можно подбирать дату рождения и захватывать чужой кабинет.
        ])->values();

        return $this->noStore(response()->json(['matches' => $matches]));
    }

    public function register(Request $request, PrisonerAccounts $accounts)
    {
        if (! $request->has('prisoner_id')) {
            return $this->selfRegister($request, $accounts);
        }
        $identity = $this->identity($request);
        $squad = Prisoner::normalizeSquad($identity['squad'] ?? null);
        $data = $request->validate([
            'prisoner_id' => 'required|integer',
            'consent' => 'required|accepted',
            'birth_date' => $this->birthDateRules(),
            ...$this->pinRules(),
        ], self::MESSAGES);
        $candidate = $this->candidate($request, $identity, (int) $data['prisoner_id']);

        $prisoner = DB::transaction(function () use ($candidate, $data, $squad) {
            $prisoner = Prisoner::with('institution')->whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            abort_if($prisoner->isRegistered(), 409, 'Этот человек уже зарегистрирован. Войдите или обратитесь к сотруднику магазина.');
            $prisoner->setCredentials('none', null, null, null);
            $prisoner->setPin($data['pin']);
            $prisoner->forceFill([
                'birth_date' => $data['birth_date'], 'recovery_method' => 'birth_date',
                'recovery_failed' => 0, 'recovery_locked_until' => null,
                'registered_at' => now(), 'squad' => $prisoner->squad ?? $squad,
                'consented_at' => now(), 'consent_version' => self::CONSENT_VERSION,
            ])->save();

            return $prisoner;
        });

        return $this->session($prisoner, $accounts);
    }

    public function login(Request $request, PrisonerAccounts $accounts)
    {
        if (! $request->has('prisoner_id')) {
            $data = $request->validate([
                'full_name' => 'required|string|min:3|max:255',
                'birth_date' => ['nullable', 'date_format:Y-m-d'],
                'secret' => 'required|string|max:100',
            ], self::MESSAGES);
            // Обычно хватает ФИО и пароля. Дату рождения спрашиваем, только если в учреждении есть полный тёзка.
            if (empty($data['birth_date'])) {
                $same = Prisoner::registeredByName($this->institutionId($request), $data['full_name']);
                abort_if($same->count() > 1, 409, 'В учреждении есть человек с такими же ФИО. Укажите дату рождения.');
                $candidate = $same->first();
            } else {
                $candidate = Prisoner::findRegistered($this->institutionId($request), $data['full_name'], $data['birth_date']);
            }
            // Один и тот же ответ на неверные ФИО, дату и пароль: по разнице ответов нельзя подобрать дату рождения.
            $wrong = empty($data['birth_date']) ? 'Неверные ФИО или пароль.' : 'Неверные ФИО, дата рождения или пароль.';
            abort_unless($candidate && $candidate->institution->is_active, 401, $wrong);
            if ($candidate->isLocked()) {
                return $this->locked($candidate);
            }
            [$prisoner, $left] = $this->verify($candidate, $data['secret']);
            if ($prisoner->isLocked()) {
                return $this->locked($prisoner);
            }
            abort_if($left !== null, 401, $wrong);

            return $this->session($prisoner, $accounts);
        }
        $identity = $this->identity($request);
        $data = $request->validate(['prisoner_id' => 'required|integer', 'secret' => 'required|string|max:100']);
        $candidate = $this->candidate($request, $identity, (int) $data['prisoner_id']);
        abort_unless($candidate->isRegistered(), 409, 'Сначала зарегистрируйтесь.');
        if ($candidate->isLocked()) {
            return $this->locked($candidate);
        }

        // Счётчик неудачных попыток сохраняется до ответа, поэтому ошибку выбрасываем уже после транзакции.
        [$prisoner, $left] = $this->verify($candidate, $data['secret']);
        if ($prisoner->isLocked()) {
            return $this->locked($prisoner);
        }
        abort_if($left !== null, 401, ($prisoner->authMethod() === 'pin' ? 'Неверный пароль.' : 'Неверный ответ.').' Осталось попыток: '.$left.'.');

        return $this->session($prisoner, $accounts);
    }

    public function profile(Request $request, PrisonerAccounts $accounts)
    {
        return $this->noStore(response()->json($accounts->ownProfile($this->account($request))));
    }

    public function history(Request $request, PrisonerAccounts $accounts)
    {
        $prisoner = $this->account($request);

        return $this->noStore(response()->json([
            'profile' => $accounts->ownProfile($prisoner),
            'operations' => $accounts->history($prisoner, 30),
        ]));
    }

    public function purchase(Request $request, PrisonerAccounts $accounts, PilotCheckout $checkout)
    {
        $prisoner = $this->account($request);
        $data = $request->validate([
            'request_id' => 'required|uuid',
            'expected_total_minor' => 'required|integer|min:1|max:9999999999',
            'items' => 'required|array|min:1|max:100',
            'items.*.product_id' => 'required|integer|distinct',
            'items.*.quantity' => 'required|integer|min:1|max:1000',
            'squad_number' => 'required|string|max:50',
            'consent' => 'required|accepted',
        ]);
        $order = $accounts->purchase($prisoner, $data, $checkout);

        return $this->noStore(response()->json([
            'order_number' => $order->order_number,
            'total_minor' => PrisonerAccounts::minor($order->total),
        ], 201));
    }

    /** Смена пароля: нужен текущий пароль. Новый — 6 цифр. */
    public function credentials(Request $request, PrisonerAccounts $accounts)
    {
        $prisoner = $this->account($request);
        $data = $request->validate(['current' => 'required|string|max:100', ...$this->pinRules()], self::MESSAGES);
        $prisoner = $this->checkCurrent($prisoner, $data['current']);
        if ($prisoner instanceof JsonResponse) {
            return $prisoner;
        }
        $prisoner->setPin($data['pin']);
        $prisoner->save();

        return $this->noStore(response()->json(['profile' => $accounts->ownProfile($prisoner)]));
    }

    /**
     * Регистрация без поиска по списку: ФИО, условия содержания, отряд, дата рождения, пароль.
     * Если в учреждении есть одна незарегистрированная карточка с точно такими ФИО — регистрация
     * привязывается к ней. Иначе создаётся новая карточка, она ждёт проверки сотрудником
     * (условия содержания и режим лимита). До проверки покупки со счёта недоступны.
     */
    private function selfRegister(Request $request, PrisonerAccounts $accounts)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'min:5', 'max:255', 'regex:/^\S+\s+\S+/u'],
            'condition' => ['required', Rule::in(array_keys(Prisoner::CONDITIONS))],
            'squad' => 'required|string|max:50',
            'birth_date' => $this->birthDateRules(),
            'consent' => 'required|accepted',
            ...$this->pinRules(),
        ], self::MESSAGES + [
            'full_name.regex' => 'Введите фамилию и имя полностью.',
            'full_name.min' => 'Введите фамилию и имя полностью.',
            'squad.required' => 'Укажите номер отряда.',
            'condition.required' => 'Выберите условия содержания.',
        ]);
        $institutionId = $this->institutionId($request);
        $name = trim((string) preg_replace('/\s+/u', ' ', $data['full_name']));

        $prisoner = DB::transaction(function () use ($institutionId, $name, $data) {
            // Блокируем учреждение, чтобы два планшета не создали две карточки одному человеку.
            $institution = Institution::whereKey($institutionId)->lockForUpdate()->firstOrFail();
            abort_unless($institution->is_active, 403, 'Учреждение сейчас не обслуживается.');
            // Проверяем только ФИО, без даты рождения: иначе ответ подсказывал бы, верна ли дата.
            abort_if(Prisoner::registeredByName($institutionId, $name)->isNotEmpty(), 409,
                'Человек с такими ФИО уже зарегистрирован. Войдите или обратитесь к сотруднику магазина.');
            $sameName = Prisoner::where('institution_id', $institutionId)->whereNull('registered_at')->lockForUpdate()->get()
                ->filter(fn (Prisoner $p) => Prisoner::normalizeName($p->full_name) === Prisoner::normalizeName($name));
            abort_if($sameName->count() > 1, 409, 'В списке несколько человек с такими ФИО. Обратитесь к сотруднику магазина.');

            $prisoner = $sameName->first();
            if (! $prisoner) {
                $prisoner = new Prisoner([
                    'full_name' => $name, 'institution_id' => $institutionId, 'condition' => $data['condition'],
                    // По умолчанию — норма учреждения (ст. 105 УИК). Работающим сотрудник ставит «без лимита».
                    'spending_mode' => 'norm', 'parcel_code' => strtoupper(bin2hex(random_bytes(12))),
                ]);
                $prisoner->limit_started_on = now('Asia/Almaty')->startOfMonth()->toDateString();
                $prisoner->reviewed_at = null;
            } else {
                // Карточку завёл сотрудник, и на ней могут быть деньги. Кто угодно мог назвать эти ФИО,
                // поэтому до проверки сотрудником покупки закрыты. Условия в карточке не меняем.
                $prisoner->reviewed_at = null;
            }
            $prisoner->setCredentials('none', null, null, null);
            $prisoner->setPin($data['pin']);
            $prisoner->forceFill([
                'squad' => Prisoner::normalizeSquad($data['squad']), 'birth_date' => $data['birth_date'],
                'recovery_method' => 'birth_date', 'recovery_failed' => 0, 'recovery_locked_until' => null,
                'failed_logins' => 0, 'locked_until' => null,
                'registered_at' => now(), 'consented_at' => now(), 'consent_version' => self::CONSENT_VERSION,
            ])->save();

            return $prisoner->load('institution');
        });

        return $this->session($prisoner, $accounts);
    }

    /**
     * Забыл пароль: ФИО + дата рождения (+ ответ на вопрос, если выбран он) → новый пароль.
     * Не больше 3 попыток за сутки на одну карточку; время восстановления видно в админке.
     */
    private function recoverByBirthDate(Request $request, PrisonerAccounts $accounts)
    {
        $data = $request->validate([
            'full_name' => 'required|string|min:3|max:255',
            'birth_date' => ['required', 'date_format:Y-m-d'],
            'proof' => 'nullable|string|max:100',
            ...$this->pinRules(),
        ], self::MESSAGES);
        $candidate = Prisoner::findRegistered($this->institutionId($request), $data['full_name'], $data['birth_date']);
        abort_unless($candidate && $candidate->institution->is_active, 404, 'Не нашли регистрацию с такими ФИО и датой рождения. Обратитесь к сотруднику магазина.');

        [$prisoner, $result] = DB::transaction(function () use ($candidate, $data) {
            $prisoner = Prisoner::with('institution')->whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            if ($prisoner->recovery_window_at === null || $prisoner->recovery_window_at->lt(now()->subHours(Prisoner::RECOVERY_LOCK_HOURS))) {
                $prisoner->forceFill(['recovery_window_at' => now(), 'recovery_failed' => 0]);
            }
            if ($prisoner->recovery_failed >= Prisoner::MAX_FAILED_RECOVERY) {
                $prisoner->save();

                return [$prisoner, 'locked'];
            }
            $prisoner->recovery_failed++;
            $ok = $prisoner->recoveryMethod() !== 'question' || $prisoner->checkSecurityAnswer((string) ($data['proof'] ?? ''));
            if ($ok) {
                $prisoner->setPin($data['pin']);
                // Дату рождения могут знать и другие: покупки закрыты, пока сотрудник не подтвердит,
                // что пароль сменил сам владелец. Старые сеансы перестают действовать (recovered_at).
                $prisoner->forceFill(['recovered_at' => now(), 'reviewed_at' => null, 'failed_logins' => 0, 'locked_until' => null]);
            }
            $prisoner->save();

            return [$prisoner, $ok ? 'ok' : 'wrong'];
        });
        if ($result === 'locked') {
            return $this->noStore(response()->json([
                'message' => 'Восстановить пароль можно не больше 3 раз в сутки. Попробуйте завтра или обратитесь к сотруднику магазина.',
            ], 423));
        }
        abort_if($result === 'wrong', 401, 'Неверный ответ на контрольный вопрос. Осталось попыток сегодня: '
            .(Prisoner::MAX_FAILED_RECOVERY - $prisoner->recovery_failed).'.');

        return $this->session($prisoner, $accounts);
    }

    /** Отряд человек пишет сам и может поменять: для доставки, не для проверки личности. */
    public function squad(Request $request, PrisonerAccounts $accounts)
    {
        $prisoner = $this->account($request);
        $data = $request->validate(['squad' => 'required|string|max:50'], ['squad.required' => 'Укажите номер отряда.']);
        $prisoner->forceFill(['squad' => Prisoner::normalizeSquad($data['squad'])])->save();

        return $this->noStore(response()->json(['profile' => $accounts->ownProfile($prisoner)]));
    }

    /** Как восстановить пароль, если забыл: дата рождения (по умолчанию) или контрольный вопрос. */
    public function recoverySettings(Request $request, PrisonerAccounts $accounts)
    {
        $prisoner = $this->account($request);
        $data = $request->validate([
            'current' => 'required|string|max:100',
            'method' => ['required', Rule::in(array_keys(Prisoner::RECOVERY_METHODS))],
            'birth_date' => $prisoner->birth_date ? ['exclude'] : ['exclude_unless:method,birth_date', ...$this->birthDateRules()],
            'question' => ['exclude_unless:method,question', 'required', Rule::in(array_keys(Prisoner::SECURITY_QUESTIONS))],
            'answer' => ['exclude_unless:method,question', 'required', 'string', 'min:2', 'max:100'],
        ], self::MESSAGES + ['answer.required' => 'Введите ответ на вопрос.', 'answer.min' => 'Ответ слишком короткий.']);
        $prisoner = $this->checkCurrent($prisoner, $data['current']);
        if ($prisoner instanceof JsonResponse) {
            return $prisoner;
        }
        if ($data['method'] === 'question') {
            $prisoner->setSecurityAnswer($data['question'], $data['answer']);
        } elseif (isset($data['birth_date'])) {
            $prisoner->birth_date = $data['birth_date'];
        }
        $prisoner->recovery_method = $data['method'];
        $prisoner->save();

        return $this->noStore(response()->json(['profile' => $accounts->ownProfile($prisoner)]));
    }

    /**
     * Забыл пароль: ФИО и условия + дата рождения (или ответ на вопрос) → новый пароль.
     * После 3 неудачных попыток восстановление закрывается на сутки — дальше только через сотрудника.
     */
    public function recover(Request $request, PrisonerAccounts $accounts)
    {
        if (! $request->has('prisoner_id')) {
            return $this->recoverByBirthDate($request, $accounts);
        }
        $identity = $this->identity($request);
        $data = $request->validate([
            'prisoner_id' => 'required|integer',
            'proof' => 'required|string|max:100',
            ...$this->pinRules(),
        ], self::MESSAGES + ['proof.required' => 'Укажите дату рождения или ответ на вопрос.']);
        $candidate = $this->candidate($request, $identity, (int) $data['prisoner_id']);
        abort_unless($candidate->isRegistered(), 409, 'Сначала зарегистрируйтесь.');
        abort_unless($candidate->recoveryMethod(), 409, 'Восстановить пароль можно только через сотрудника магазина.');

        [$prisoner, $ok] = DB::transaction(function () use ($candidate, $data) {
            $prisoner = Prisoner::with('institution')->whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            if ($prisoner->isRecoveryLocked()) {
                return [$prisoner, false];
            }
            if ($prisoner->checkRecovery($data['proof'])) {
                $prisoner->setPin($data['pin']);
                $prisoner->forceFill([
                    'recovery_failed' => 0, 'recovery_locked_until' => null,
                    'recovered_at' => now(), 'reviewed_at' => null,
                ])->save();

                return [$prisoner, true];
            }
            $failed = $prisoner->recovery_failed + 1;
            $lock = $failed >= Prisoner::MAX_FAILED_RECOVERY;
            $prisoner->forceFill([
                'recovery_failed' => $lock ? 0 : $failed,
                'recovery_locked_until' => $lock ? now()->addHours(Prisoner::RECOVERY_LOCK_HOURS) : null,
            ])->save();

            return [$prisoner, false];
        });
        if ($prisoner->isRecoveryLocked()) {
            return $this->noStore(response()->json([
                'message' => 'Восстановление пароля закрыто на сутки после нескольких неверных попыток. Обратитесь к сотруднику магазина.',
            ], 423));
        }
        if (! $ok) {
            $left = Prisoner::MAX_FAILED_RECOVERY - $prisoner->recovery_failed;
            abort(401, ($prisoner->recoveryMethod() === 'question' ? 'Неверный ответ.' : 'Дата рождения не совпадает.').' Осталось попыток: '.$left.'.');
        }

        return $this->session($prisoner, $accounts);
    }

    /** Новый код для родственников. Показывается один раз; старый код перестаёт работать. */
    public function familyCode(Request $request)
    {
        return $this->noStore(response()->json(['code' => $this->account($request)->issueFamilyCode()]));
    }

    public function disableFamilyCode(Request $request)
    {
        $this->account($request)->disableFamilyCode();

        return $this->noStore(response()->json(['ok' => true]));
    }

    private function pinRules(): array
    {
        return ['pin' => ['required', 'string', 'regex:/^[0-9]{6}$/', 'confirmed']];
    }

    private function birthDateRules(): array
    {
        return ['required', 'date_format:Y-m-d', 'after:1920-01-01', 'before:'.now('Asia/Almaty')->subYears(14)->toDateString()];
    }


    /** Проверка текущего пароля в настройках: ошибки считаются так же, как при входе. */
    private function checkCurrent(Prisoner $prisoner, string $current): Prisoner|JsonResponse
    {
        [$prisoner, $left] = $this->verify($prisoner, $current);
        if ($prisoner->isLocked()) {
            return $this->locked($prisoner);
        }
        if ($left !== null) {
            throw ValidationException::withMessages(['current' => 'Текущий пароль неверный. Осталось попыток: '.$left.'.']);
        }

        return $prisoner;
    }

    private function institutionId(Request $request): int
    {
        $institution = $request->attributes->get('prisoner_terminal_institution');
        abort_unless($institution, 428, 'Планшет не привязан к учреждению. Попросите сотрудника ввести код учреждения.');

        return (int) $institution;
    }

    private function identity(Request $request): array
    {
        // Новое приложение присылает условия содержания, прежнее — отряд.
        return $request->validate([
            'full_name' => 'required|string|min:3|max:255',
            'condition' => ['required_without:squad', 'nullable', Rule::in(array_keys(Prisoner::CONDITIONS))],
            'squad' => 'required_without:condition|nullable|string|max:50',
        ], self::MESSAGES);
    }

    private function candidates(Request $request, array $identity)
    {
        // Отряд в списке может быть не указан — тогда человек называет его сам при регистрации.
        return Prisoner::where('institution_id', $this->institutionId($request))
            ->when(! empty($identity['condition']),
                fn ($query) => $query->where('condition', $identity['condition']),
                fn ($query) => $query->where(fn ($query) => $query->where('squad', Prisoner::normalizeSquad($identity['squad']))->orWhereNull('squad')))
            ->orderBy('full_name')->orderBy('id')->limit(500)->get()
            ->filter(fn (Prisoner $prisoner) => $prisoner->matchesName($identity['full_name']));
    }

    /** Повторно сверяем ФИО и отряд, чтобы нельзя было перебирать номера карточек. */
    private function candidate(Request $request, array $identity, int $id): Prisoner
    {
        $prisoner = $this->candidates($request, $identity)->firstWhere('id', $id);
        abort_unless($prisoner, 404, 'Человек не найден. Проверьте ФИО и отряд.');
        $prisoner->load('institution');
        abort_unless($prisoner->institution->is_active, 403, 'Учреждение сейчас не обслуживается.');

        return $prisoner;
    }

    /** @return array{0: Prisoner, 1: ?int} карточка и остаток попыток (null — секрет верный). */
    private function verify(Prisoner $prisoner, string $secret): array
    {
        return DB::transaction(function () use ($prisoner, $secret) {
            $prisoner = Prisoner::with('institution')->whereKey($prisoner->id)->lockForUpdate()->firstOrFail();
            if ($prisoner->isLocked() || $prisoner->checkSecret(trim($secret))) {
                return [$prisoner, null];
            }
            $failed = $prisoner->failed_logins + 1;
            $lock = $failed >= Prisoner::MAX_FAILED_LOGINS;
            $prisoner->forceFill([
                'failed_logins' => $lock ? 0 : $failed,
                'locked_until' => $lock ? now()->addMinutes(Prisoner::LOCK_MINUTES) : $prisoner->locked_until,
            ])->save();

            return [$prisoner, $lock ? 0 : Prisoner::MAX_FAILED_LOGINS - $failed];
        });
    }

    private function session(Prisoner $prisoner, PrisonerAccounts $accounts): JsonResponse
    {
        $prisoner->forceFill(['failed_logins' => 0, 'locked_until' => null, 'last_login_at' => now()])->save();
        $token = Crypt::encryptString(json_encode([
            'scope' => 'dosmart-prisoner-tablet', 'prisoner_id' => $prisoner->id,
            'institution_id' => (int) $prisoner->institution_id, 'expires' => now()->addMinutes(15)->timestamp,
            // Сброс регистрации в админке завершает все открытые сеансы.
            'registered' => $prisoner->registered_at?->timestamp,
            // Восстановление пароля завершает все сеансы, открытые до него.
            'recovered' => $prisoner->recovered_at?->timestamp,
        ], JSON_THROW_ON_ERROR));

        return $this->noStore(response()->json(['token' => $token, 'profile' => $accounts->ownProfile($prisoner)]));
    }

    private function locked(Prisoner $prisoner): JsonResponse
    {
        $minutes = max(1, (int) ceil(now()->diffInSeconds($prisoner->locked_until) / 60));

        return $this->noStore(response()->json([
            'message' => "Вход временно заблокирован после нескольких неверных попыток. Повторите через {$minutes} мин. или обратитесь к сотруднику.",
        ], 423));
    }

    private function account(Request $request): Prisoner
    {
        $prisoner = self::sessionPrisoner((string) $request->bearerToken());
        $institution = $request->attributes->get('prisoner_terminal_institution');
        abort_unless($prisoner && ($institution === null || (int) $prisoner->institution_id === (int) $institution), 401);

        return $prisoner;
    }

    /** Человек по токену сессии кабинета или null, если токен неверный или истёк. */
    public static function sessionPrisoner(string $token): ?Prisoner
    {
        if ($token === '' || strlen($token) > 2048) {
            return null;
        }
        try {
            $session = json_decode(Crypt::decryptString($token), true);
        } catch (DecryptException) {
            return null;
        }
        if (! is_array($session) || ($session['scope'] ?? null) !== 'dosmart-prisoner-tablet' || ($session['expires'] ?? 0) <= now()->timestamp) {
            return null;
        }
        $prisoner = Prisoner::with('institution')->find($session['prisoner_id'] ?? null);

        return $prisoner && $prisoner->isRegistered() && $prisoner->institution->is_active
            && (int) $prisoner->institution_id === ($session['institution_id'] ?? null)
            && $prisoner->registered_at->timestamp === ($session['registered'] ?? null)
            && $prisoner->recovered_at?->timestamp === ($session['recovered'] ?? null) ? $prisoner : null;
    }

    private function noStore($response)
    {
        return $response->header('Cache-Control', 'private, no-store');
    }
}
