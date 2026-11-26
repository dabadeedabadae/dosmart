<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DepositRequest;
use App\Models\Institution;
use App\Models\Prisoner;
use App\Models\PrisonerTransfer;
use App\Models\User;
use App\Services\PrisonerAccounts;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PrisonerController extends Controller
{
    private const MESSAGES = [
        'iin.regex' => 'ИИН — ровно 12 цифр.',
        'iin.unique' => 'Карточка с таким ИИН уже есть.',
        'monthly_limit.required_if' => 'Укажите сумму месячного лимита.',
    ];

    private const CONDITION_WORDS = ['обыч' => 'ordinary', 'облег' => 'eased', 'строг' => 'strict', 'льгот' => 'privileged'];

    public function index()
    {
        return response()->view('admin.prisoners.index', [
            'institutions' => Institution::withCount('prisoners')->orderBy('name')->get(),
            'pendingRequests' => DepositRequest::where('status', 'new')->count(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function people(Request $request)
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:100',
            'institution_id' => 'nullable|integer|exists:institutions,id',
            'condition' => ['nullable', Rule::in(array_keys(Prisoner::CONDITIONS))],
            'limit' => 'nullable|in:with,without',
            'registered' => 'nullable|in:yes,no',
            'review' => 'nullable|in:waiting',
            'squad' => 'nullable|string|max:50',
        ]);
        $prisoners = Prisoner::with('institution')
            ->when($filters['q'] ?? null, fn ($query, $search) => preg_match('/^[0-9]{12}$/', trim($search))
                ? $query->where('iin', trim($search))
                : $query->where('full_name', 'like', '%'.$search.'%'))
            ->when($filters['institution_id'] ?? null, fn ($query, $id) => $query->where('institution_id', $id))
            ->when($filters['condition'] ?? null, fn ($query, $condition) => $query->where('condition', $condition))
            ->when($filters['squad'] ?? null, fn ($query, $squad) => $query->where('squad', Prisoner::normalizeSquad($squad)))
            ->when(($filters['limit'] ?? null) === 'with', fn ($query) => $query->where('spending_mode', '!=', 'unlimited'))
            ->when(($filters['limit'] ?? null) === 'without', fn ($query) => $query->where('spending_mode', 'unlimited'))
            ->when(($filters['registered'] ?? null) === 'yes', fn ($query) => $query->whereNotNull('registered_at'))
            ->when(($filters['registered'] ?? null) === 'no', fn ($query) => $query->whereNull('registered_at'))
            ->when(($filters['review'] ?? null) === 'waiting', fn ($query) => $query->whereNull('reviewed_at'))
            ->orderBy('full_name')->orderBy('id')->paginate(20)->withQueryString();
        $scope = Prisoner::query()->when($filters['institution_id'] ?? null, fn ($query, $id) => $query->where('institution_id', $id));

        return response()->view('admin.prisoners.people', [
            'prisoners' => $prisoners,
            'institutions' => Institution::orderBy('name')->get(),
            'institution' => isset($filters['institution_id']) ? Institution::find($filters['institution_id']) : null,
            'counts' => [
                'with' => (clone $scope)->where('spending_mode', '!=', 'unlimited')->count(),
                'without' => (clone $scope)->where('spending_mode', 'unlimited')->count(),
                'unregistered' => (clone $scope)->whereNull('registered_at')->count(),
                'review' => (clone $scope)->whereNull('reviewed_at')->count(),
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function create(Request $request)
    {
        $request->validate(['institution_id' => 'nullable|integer|exists:institutions,id']);

        return response()->view('admin.prisoners.create', [
            'institutions' => Institution::orderBy('name')->get(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'iin' => ['nullable', 'regex:/^[0-9]{12}$/', 'unique:prisoners,iin'],
            'full_name' => 'required|string|max:255',
            'squad' => 'nullable|string|max:50',
            'institution_id' => 'required|exists:institutions,id',
            ...$this->settingsRules(),
        ], self::MESSAGES);

        $prisoner = new Prisoner([
            'iin' => $data['iin'] ?? null, 'full_name' => trim($data['full_name']), 'squad' => Prisoner::normalizeSquad($data['squad']),
            'institution_id' => $data['institution_id'], 'condition' => $data['condition'],
            'parcel_code' => strtoupper(bin2hex(random_bytes(12))),
        ]);
        $this->applySpending($prisoner, $data);
        $prisoner->reviewed_at = now();
        $prisoner->save();

        return redirect()->route('admin.prisoners.show', $prisoner)->with('success', 'Карточка создана. Человек сможет зарегистрироваться на планшете по ФИО и дате рождения.');
    }

    public function show(Prisoner $prisoner, PrisonerAccounts $accounts)
    {
        $prisoner->load('institution');
        $transfers = $prisoner->transfers()->latest('id')->paginate(20, pageName: 'transfers');
        $staffIds = $transfers->getCollection()->flatMap(fn ($t) => [$t->registered_by, $t->confirmed_by, $t->closed_by])->filter()->unique();

        return response()->view('admin.prisoners.show', [
            'prisoner' => $prisoner, 'profile' => $accounts->profile($prisoner),
            'transfers' => $transfers,
            'staff' => User::whereIn('id', $staffIds)->get()->mapWithKeys(fn ($u) => [$u->id => $u->name ?: $u->username]),
            'orders' => $prisoner->orders()->latest('id')->paginate(20, pageName: 'orders'),
            'history' => $accounts->history($prisoner, 15),
            'depositRequests' => $prisoner->depositRequests()->latest('id')->limit(10)->get(),
            'relativeTransfers' => $this->relativeTransfersThisMonth($prisoner),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, Prisoner $prisoner)
    {
        $data = $request->validate($this->settingsRules() + [
            'full_name' => 'required|string|max:255',
            'squad' => 'nullable|string|max:50',
            'iin' => ['nullable', 'regex:/^[0-9]{12}$/', Rule::unique('prisoners', 'iin')->ignore($prisoner->id)],
        ], self::MESSAGES);
        $prisoner->fill([
            'full_name' => trim($data['full_name']), 'squad' => Prisoner::normalizeSquad($data['squad']),
            'iin' => $data['iin'] ?? null, 'condition' => $data['condition'],
        ]);
        $this->applySpending($prisoner, $data);
        // Сотрудник сохранил условия и режим лимита — регистрация проверена.
        $prisoner->reviewed_at ??= now();
        $prisoner->save();

        return back()->with('success', 'Карточка обновлена.');
    }

    /** Сотрудник сверил ФИО, условия содержания и режим лимита у самостоятельно зарегистрированного человека. */
    public function review(Prisoner $prisoner)
    {
        $prisoner->forceFill(['reviewed_at' => now()])->save();

        return back()->with('success', 'Регистрация проверена. Покупки со счёта доступны.');
    }

    /** Если под человеком зарегистрировался кто-то другой — сбрасываем пароль, вопрос и код для родственников. */
    public function resetRegistration(Prisoner $prisoner)
    {
        $prisoner->resetRegistration();
        $prisoner->save();

        return back()->with('success', 'Регистрация сброшена. Человек может зарегистрироваться на планшете заново.');
    }

    public function disableFamilyCode(Prisoner $prisoner)
    {
        $prisoner->disableFamilyCode();

        return back()->with('success', 'Код для родственников отключён.');
    }

    public function unlock(Prisoner $prisoner)
    {
        $prisoner->forceFill(['failed_logins' => 0, 'locked_until' => null, 'recovery_failed' => 0, 'recovery_locked_until' => null])->save();

        return back()->with('success', 'Блокировка входа и восстановления пароля снята.');
    }

    private function settingsRules(): array
    {
        return [
            'condition' => ['required', Rule::in(array_keys(Prisoner::CONDITIONS))],
            'spending_mode' => ['required', Rule::in(array_keys(Prisoner::SPENDING_MODES))],
            'monthly_limit' => 'required_if:spending_mode,custom|nullable|numeric|min:0|max:9999999999.99|decimal:0,2',
        ];
    }

    /** Перенос остатка лимита считается с месяца, когда лимит включили. */
    private function applySpending(Prisoner $prisoner, array $data): void
    {
        $limited = $data['spending_mode'] !== 'unlimited';
        $wasLimited = $prisoner->exists && ($prisoner->getOriginal('spending_mode') ?? 'unlimited') !== 'unlimited';
        $prisoner->forceFill([
            'spending_mode' => $data['spending_mode'],
            'monthly_limit_minor' => $data['spending_mode'] === 'custom' ? PrisonerAccounts::minor($data['monthly_limit']) : null,
            'limit_started_on' => ! $limited ? null
                : ($wasLimited && $prisoner->limit_started_on ? $prisoner->limit_started_on : now('Asia/Almaty')->startOfMonth()->toDateString()),
        ]);
    }

    public function spendingLimits(Request $request, Institution $institution)
    {
        $rules = array_fill_keys(array_map(fn ($c) => 'spend_'.$c, array_keys(Prisoner::CONDITIONS)), 'nullable|integer|min:0|max:9999');
        $data = $request->validate($rules + [
            'mrp_year' => 'required|integer|between:2000,2100',
            'mrp_value' => 'required|integer|min:1|max:1000000',
        ]);
        DB::transaction(function () use ($data, $institution) {
            $institution = Institution::whereKey($institution->id)->lockForUpdate()->firstOrFail();
            $year = $data['mrp_year'];
            $rates = $institution->parcel_mrp_rates ?? [];
            $rates[$year] = (int) $data['mrp_value'];
            $norms = $institution->spending_limits_mrp ?? [];
            $norms[$year] = [];
            foreach (array_keys(Prisoner::CONDITIONS) as $condition) {
                if (($data['spend_'.$condition] ?? null) !== null) {
                    $norms[$year][$condition] = (int) $data['spend_'.$condition];
                }
            }
            $institution->forceFill(['parcel_mrp_rates' => $rates, 'spending_limits_mrp' => $norms])->save();
        });

        return back()->with('success', 'Нормы расходования сохранены.');
    }

    public function deposit(Request $request, Prisoner $prisoner)
    {
        $data = $request->validate([
            'application_number' => 'required|string|max:100',
            'application_date' => 'required|date_format:Y-m-d|before_or_equal:'.now('Asia/Almaty')->toDateString(),
            'amount' => 'required|numeric|min:0.01|max:9999999999.99|decimal:0,2',
            'payment_reference' => 'required|string|max:100',
            'payment_verified' => 'required|accepted',
        ]);
        try {
            DB::transaction(function () use ($request, $prisoner, $data) {
                $prisoner = Prisoner::whereKey($prisoner->id)->lockForUpdate()->firstOrFail();
                $transfer = $prisoner->transfers()->create([
                    'application_number' => $data['application_number'], 'application_date' => $data['application_date'],
                    'amount_minor' => PrisonerAccounts::minor($data['amount']), 'registered_by' => $request->user()->id,
                ]);
                $transfer->forceFill([
                    'status' => 'confirmed', 'payment_reference' => mb_strtoupper(trim($data['payment_reference'])),
                    'confirmed_by' => $request->user()->id, 'confirmed_at' => now(),
                ])->save();
                $prisoner->increment('balance_minor', $transfer->amount_minor);
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            $field = str_contains($e->getMessage(), 'payment_reference') ? 'payment_reference' : 'application_number';
            throw ValidationException::withMessages([$field => $field === 'payment_reference'
                ? 'Этот платёж уже использован для пополнения.' : 'Такое заявление уже зарегистрировано.']);
        }

        return back()->with('success', 'Счёт пополнен на '.number_format((float) $data['amount'], 2, ',', ' ').' ₸.');
    }

    public function tabletCode(Institution $institution)
    {
        $code = $institution->issueTabletCode();

        return back()->with('success', "Новый код учреждения для планшетов: {$code}. Старые привязки отключены — введите код на каждом планшете заново.");
    }

    /** Импорт: «ФИО; отряд; ИИН; условия» или просто «ФИО; условия» — отряд, ИИН и условия необязательны. */
    public function import(Request $request, Institution $institution)
    {
        $data = $request->validate(['rows' => 'required|string|max:400000']);
        $lines = array_slice(preg_split('/\r\n|\r|\n/', $data['rows']), 0, 3000);
        $created = $updated = 0;
        $problems = [];
        DB::transaction(function () use ($lines, $institution, &$created, &$updated, &$problems) {
            foreach ($lines as $number => $line) {
                if (trim($line) === '') {
                    continue;
                }
                $cells = array_map('trim', preg_split('/[;\t]/', $line));
                [$name, $squad, $iin, $condition] = array_pad($cells, 4, '');
                // «ФИО; условия» — отряд не указан, человек назовёт его сам при регистрации.
                if (collect(self::CONDITION_WORDS)->keys()->contains(fn ($word) => str_contains(mb_strtolower($squad), $word))) {
                    [$squad, $iin, $condition] = ['', '', $squad];
                }
                $squad = Prisoner::normalizeSquad($squad);
                $iin = preg_replace('/\D/', '', $iin);
                if (mb_strlen($name) < 3 || ($iin !== '' && strlen($iin) !== 12)) {
                    $problems[] = 'Строка '.($number + 1).': нужны ФИО, ИИН — 12 цифр.';

                    continue;
                }
                $conditionKey = collect(self::CONDITION_WORDS)->first(fn ($key, $word) => str_contains(mb_strtolower($condition), $word));
                $existing = $iin !== '' ? Prisoner::where('iin', $iin)->first() : null;
                if (! $existing) {
                    $sameName = Prisoner::where('institution_id', $institution->id)->get()
                        ->filter(fn ($p) => Prisoner::normalizeName($p->full_name) === Prisoner::normalizeName($name));
                    if ($sameName->count() > 1) {
                        $problems[] = 'Строка '.($number + 1).': несколько карточек «'.$name.'» — укажите ИИН.';

                        continue;
                    }
                    $existing = $sameName->first();
                }
                if ($existing && (int) $existing->institution_id !== $institution->id) {
                    $problems[] = 'Строка '.($number + 1).': ИИН уже есть в другом учреждении.';

                    continue;
                }
                if ($existing) {
                    $existing->fill(['squad' => $squad ?? $existing->squad, 'iin' => $existing->iin ?? ($iin ?: null)]);
                    if ($conditionKey) {
                        $existing->condition = $conditionKey;
                    }
                    if ($existing->isDirty()) {
                        $existing->save();
                        $updated++;
                    }

                    continue;
                }
                Prisoner::create([
                    'full_name' => preg_replace('/\s+/u', ' ', $name), 'squad' => $squad, 'iin' => $iin ?: null,
                    'institution_id' => $institution->id, 'condition' => $conditionKey ?? 'ordinary',
                    // Лимит покупок — норма учреждения по условиям (ст. 105 УИК). Работающим сотрудник ставит «без лимита».
                    'spending_mode' => 'norm', 'parcel_code' => strtoupper(bin2hex(random_bytes(12))),
                ])->forceFill(['reviewed_at' => now(), 'limit_started_on' => now('Asia/Almaty')->startOfMonth()->toDateString()])->save();
                $created++;
            }
        });

        return back()->with('success', "Импорт: добавлено {$created}, обновлено {$updated}.")
            ->with('import_problems', array_slice($problems, 0, 30));
    }

    public function requests(Request $request)
    {
        $status = $request->validate(['status' => 'nullable|in:new,credited,rejected'])['status'] ?? 'new';
        $requests = DepositRequest::with('prisoner.institution')->where('status', $status)
            ->orderBy($status === 'new' ? 'id' : 'handled_at', $status === 'new' ? 'asc' : 'desc')->paginate(30)->withQueryString();

        return response()->view('admin.prisoners.requests', [
            'requests' => $requests, 'status' => $status,
            'counts' => DepositRequest::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'relative' => $requests->getCollection()->mapWithKeys(fn ($r) => [$r->id => $this->relativeTransfersThisMonth($r->prisoner)]),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function creditRequest(Request $request, DepositRequest $depositRequest)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:9999999999.99|decimal:0,2',
            'payment_reference' => 'required|string|max:100',
            'payment_verified' => 'required|accepted',
        ]);
        try {
            DB::transaction(function () use ($request, $depositRequest, $data) {
                $deposit = DepositRequest::whereKey($depositRequest->id)->lockForUpdate()->firstOrFail();
                if ($deposit->status !== 'new') {
                    throw ValidationException::withMessages(['payment_reference' => 'Заявка уже обработана.']);
                }
                $prisoner = Prisoner::whereKey($deposit->prisoner_id)->lockForUpdate()->firstOrFail();
                $transfer = $prisoner->transfers()->create([
                    'application_number' => $deposit->number(), 'source' => 'relative', 'sender_name' => $deposit->sender_name,
                    'application_date' => $deposit->created_at->timezone('Asia/Almaty')->toDateString(),
                    'amount_minor' => PrisonerAccounts::minor($data['amount']), 'registered_by' => $request->user()->id,
                ]);
                $transfer->forceFill([
                    'status' => 'confirmed', 'payment_reference' => mb_strtoupper(trim($data['payment_reference'])),
                    'confirmed_by' => $request->user()->id, 'confirmed_at' => now(),
                ])->save();
                $prisoner->increment('balance_minor', $transfer->amount_minor);
                $deposit->forceFill([
                    'status' => 'credited', 'transfer_id' => $transfer->id, 'handled_by' => $request->user()->id, 'handled_at' => now(),
                ])->save();
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['payment_reference' => 'Этот платёж уже использован для пополнения.']);
        }

        return back()->with('success', 'Заявка '.$depositRequest->number().' зачислена на счёт.');
    }

    public function rejectRequest(Request $request, DepositRequest $depositRequest)
    {
        $data = $request->validate(['reason' => 'required|string|max:255']);
        $updated = DepositRequest::whereKey($depositRequest->id)->where('status', 'new')->update([
            'status' => 'rejected', 'reject_reason' => $data['reason'], 'handled_by' => $request->user()->id,
            'handled_at' => now(), 'updated_at' => now(),
        ]);
        abort_unless($updated, 409, 'Заявка уже обработана.');

        return back()->with('success', 'Заявка '.$depositRequest->number().' отклонена.');
    }

    public function transferReceipt(Prisoner $prisoner, PrisonerTransfer $transfer)
    {
        abort_unless((int) $transfer->prisoner_id === $prisoner->id && in_array($transfer->status, ['confirmed', 'reversed'], true), 404);
        $prisoner->load('institution');

        return response()->view('admin.prisoners.transfer-receipt', [
            'prisoner' => $prisoner, 'transfer' => $transfer,
            'staff' => User::find($transfer->confirmed_by)?->name,
        ])->header('Cache-Control', 'private, no-store');
    }

    /**
     * Переводы от родственников за текущий месяц. Ст. 108 ч. 4 УИК для учреждений средней и выше
     * безопасности: не чаще 2 раз и не более 20 МРП в месяц — показываем как предупреждение.
     */
    private function relativeTransfersThisMonth(Prisoner $prisoner): array
    {
        $month = now('Asia/Almaty')->startOfMonth();
        $query = $prisoner->transfers()->where('source', 'relative')->where('status', 'confirmed')
            ->where('confirmed_at', '>=', $month->copy()->utc());
        $rate = $prisoner->institution->parcel_mrp_rates[$month->year] ?? config('pilot.mrp.'.$month->year);

        return ['count' => (clone $query)->count(), 'sum_minor' => (int) $query->sum('amount_minor'),
            'cap_minor' => $rate ? 20 * (int) $rate * 100 : null];
    }

    public function limits(Request $request, Institution $institution)
    {
        // Льготные условия добавлены позже: поле необязательное, пусто — передачи не ограничены.
        $rules = array_fill_keys(array_keys(Prisoner::CONDITIONS), 'required|integer|min:0|max:9999');
        $rules['privileged'] = 'nullable|integer|min:0|max:9999';
        $data = $request->validate($rules + [
            'mode' => 'nullable|in:count',
            'mrp_year' => 'required_with:mrp_value|nullable|integer|between:2000,2100',
            'mrp_value' => 'required_with:mrp_year|nullable|integer|min:1|max:1000000',
        ]);
        DB::transaction(function () use ($data, $institution) {
            $institution = Institution::whereKey($institution->id)->lockForUpdate()->firstOrFail();
            $limits = array_map('intval', array_filter(array_intersect_key($data, Prisoner::CONDITIONS), fn ($v) => $v !== null));
            if (($data['mode'] ?? null) === 'count') {
                // Нормы по закону — количество передач в год. Нормы в МРП отключаются.
                $institution->forceFill(['parcel_limits' => $limits, 'parcel_limits_mrp' => null])->save();

                return;
            }
            if (! isset($data['mrp_year'])) {
                if ($institution->parcel_limits_mrp !== null) {
                    throw ValidationException::withMessages(['mrp_year' => 'Укажите год и размер МРП.']);
                }
                $institution->forceFill(['parcel_limits' => $limits])->save();

                return;
            }

            $year = $data['mrp_year'];
            $rates = $institution->parcel_mrp_rates ?? [];
            $rates[$year] = (int) $data['mrp_value'];
            $annualLimits = $institution->parcel_limits_mrp ?? [];
            $annualLimits[$year] = $limits;
            $institution->forceFill([
                'parcel_limits_mrp' => $annualLimits, 'parcel_mrp_rates' => $rates, 'parcel_limits' => null,
            ])->save();
        });

        return back()->with('success', 'Месячные нормы сохранены.');
    }

    public function register(Request $request, Prisoner $prisoner)
    {
        $data = $request->validate([
            'application_number' => 'required|string|max:100',
            'application_date' => 'required|date_format:Y-m-d|before_or_equal:'.now('Asia/Almaty')->toDateString(),
            'amount' => 'required|numeric|min:0.01|max:9999999999.99|decimal:0,2',
        ]);
        try {
            DB::transaction(fn () => $prisoner->transfers()->create([
                'application_number' => $data['application_number'], 'application_date' => $data['application_date'],
                'amount_minor' => PrisonerAccounts::minor($data['amount']), 'registered_by' => $request->user()->id,
            ]));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['application_number' => 'Такое заявление уже зарегистрировано.']);
        }

        return back()->with('success', 'Заявление зарегистрировано. Ожидаем перевод.');
    }

    public function settle(Request $request, Prisoner $prisoner, PrisonerTransfer $transfer)
    {
        $data = $request->validate([
            'action' => 'required|in:confirm,reject,reverse',
            'payment_reference' => 'required_if:action,confirm|nullable|string|max:100',
            'payment_verified' => $request->input('action') === 'confirm' ? 'required|accepted' : 'exclude',
            'reason' => 'required_unless:action,confirm|nullable|string|max:255',
        ]);
        try {
            DB::transaction(function () use ($request, $prisoner, $transfer, $data) {
                $prisoner = Prisoner::whereKey($prisoner->id)->lockForUpdate()->firstOrFail();
                $transfer = $prisoner->transfers()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
                $target = ['confirm' => 'confirmed', 'reject' => 'rejected', 'reverse' => 'reversed'][$data['action']];
                if ($transfer->status === $target) {
                    return;
                }
                $actor = $request->user()->id;
                if ($target === 'confirmed' && $transfer->status === 'pending') {
                    $transfer->forceFill([
                        'status' => $target, 'payment_reference' => mb_strtoupper($data['payment_reference']),
                        'confirmed_by' => $actor, 'confirmed_at' => now(),
                    ])->save();
                    $prisoner->increment('balance_minor', $transfer->amount_minor);

                    return;
                }
                if ($target === 'reversed' && $transfer->status === 'confirmed') {
                    if ($prisoner->balance_minor < $transfer->amount_minor) {
                        throw ValidationException::withMessages(['reason' => 'На счёте недостаточно средств для сторнирования.']);
                    }
                    $prisoner->decrement('balance_minor', $transfer->amount_minor);
                } elseif ($target !== 'rejected' || $transfer->status !== 'pending') {
                    throw ValidationException::withMessages(['action' => 'Заявление уже обработано.']);
                }
                $transfer->forceFill([
                    'status' => $target, 'closed_by' => $actor, 'closed_at' => now(), 'close_reason' => $data['reason'],
                ])->save();
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['payment_reference' => 'Этот платёж уже использован для пополнения.']);
        }

        return back()->with('success', 'Операция сохранена.');
    }
}
