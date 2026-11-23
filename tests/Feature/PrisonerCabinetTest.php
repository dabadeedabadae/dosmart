<?php

namespace Tests\Feature;

use App\Models\DepositRequest;
use App\Models\Institution;
use App\Models\Order;
use App\Models\Prisoner;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrisonerCabinetTest extends TestCase
{
    use RefreshDatabase;

    private string $secret;

    private ?string $tablet = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->secret = str_repeat('s', 40);
        config(['pilot.prisoner_gateway_secret' => $this->secret]);
        // Лимиты частоты считаются по IP; здесь проверяем бизнес-логику, а не throttle.
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function prisoner(array $attributes = []): Prisoner
    {
        $institution = Institution::first() ?? Institution::create(['name' => 'ИК-1', 'city' => 'Петропавловск', 'is_active' => true, 'order_code' => '01']);

        return Prisoner::create($attributes + [
            'iin' => '900101300123', 'full_name' => 'Иванов Иван Петрович', 'squad' => '7', 'institution_id' => $institution->id,
            'condition' => 'ordinary', 'spending_mode' => 'unlimited', 'parcel_code' => strtoupper(Str::random(24)),
        ]);
    }

    private function api(string $method, string $path, array $body = [], ?string $token = null)
    {
        $headers = ['X-Dosmart-Tablet-Gateway' => $this->secret];
        if ($this->tablet) {
            $headers['X-Dosmart-Tablet'] = $this->tablet;
        }
        if ($token) {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        return $this->withHeaders($headers)->json($method, $path, $body);
    }

    private function bindTablet(Institution $institution): void
    {
        $this->tablet = $this->api('POST', '/api/v1/tablet/setup', ['code' => $institution->tablet_code ?? $institution->issueTabletCode()])
            ->assertOk()->assertJsonPath('institution.id', $institution->id)->json('token');
    }

    private const PIN = '482915';

    private function register(Prisoner $prisoner, string $pin = self::PIN, string $birthDate = '1990-03-15'): string
    {
        return $this->api('POST', '/api/v1/prisoner/register', [
            'full_name' => $prisoner->full_name, 'condition' => $prisoner->condition, 'prisoner_id' => $prisoner->id, 'consent' => true,
            'birth_date' => $birthDate, 'pin' => $pin, 'pin_confirmation' => $pin,
        ])->assertOk()->json('token');
    }

    public function test_tablet_is_bound_to_one_institution_by_code_and_rebinding_revokes_old_tablets(): void
    {
        $prisoner = $this->prisoner();
        $institution = $prisoner->institution;
        $this->api('GET', '/api/v1/prisoner/squads')->assertStatus(428);
        $this->api('POST', '/api/v1/tablet/setup', ['code' => '12345678'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->flushHeaders()->postJson('/api/v1/tablet/setup', ['code' => $institution->issueTabletCode()])->assertForbidden();
        $this->bindTablet($institution->fresh());

        $other = Institution::create(['name' => 'ИК-2', 'city' => 'Алматы', 'is_active' => true, 'order_code' => '02']);
        $this->prisoner(['iin' => null, 'full_name' => 'Иванов Иван Сергеевич', 'institution_id' => $other->id, 'squad' => '7']);
        $this->prisoner(['iin' => null, 'full_name' => 'Сидоров Пётр', 'squad' => '10']);

        $this->api('GET', '/api/v1/tablet')->assertOk()->assertJsonPath('institution.name', 'ИК-1');
        $this->api('GET', '/api/v1/prisoner/squads')->assertOk()->assertExactJson(['squads' => ['7', '10']]);
        // Только своё учреждение и выбранный отряд; слова ФИО — в любом порядке, ё = е.
        $this->api('POST', '/api/v1/prisoner/find', ['full_name' => 'иван иванов', 'squad' => '7'])->assertOk()
            ->assertJsonCount(1, 'matches')->assertJsonPath('matches.0.id', $prisoner->id)->assertJsonPath('matches.0.registered', false);
        $this->api('POST', '/api/v1/prisoner/find', ['full_name' => 'сидоров петр', 'squad' => '7'])->assertOk()->assertJsonCount(0, 'matches');
        $this->api('POST', '/api/v1/prisoner/find', ['full_name' => 'сидоров петр', 'squad' => '10'])->assertOk()->assertJsonCount(1, 'matches');

        $this->actingAs(User::factory()->create())->post('/admin/institutions/'.$institution->id.'/tablet-code')->assertRedirect();
        $this->api('GET', '/api/v1/tablet')->assertStatus(428);
    }

    public function test_registration_by_name_condition_and_birth_date_with_six_digit_pin_lockout_and_admin_reset(): void
    {
        $prisoner = $this->prisoner();
        $this->prisoner(['iin' => null, 'full_name' => 'Иванов Иван Сергеевич', 'condition' => 'strict']);
        $this->bindTablet($prisoner->institution);
        // Поиск по ФИО и условиям содержания: человек с другими условиями не показывается.
        $this->api('POST', '/api/v1/prisoner/find', ['full_name' => 'иванов иван', 'condition' => 'ordinary'])->assertOk()
            ->assertJsonCount(1, 'matches')->assertJsonPath('matches.0.id', $prisoner->id);
        $this->api('POST', '/api/v1/prisoner/find', ['full_name' => 'иванов иван', 'condition' => 'eased'])->assertOk()->assertJsonCount(0, 'matches');
        $this->api('POST', '/api/v1/prisoner/find', ['full_name' => 'иванов иван'])->assertUnprocessable();

        $identity = ['full_name' => 'Иванов Иван', 'condition' => 'ordinary', 'prisoner_id' => $prisoner->id];
        $pin = fn (string $p) => ['pin' => $p, 'pin_confirmation' => $p];
        $this->api('POST', '/api/v1/prisoner/register', $identity + ['birth_date' => '1990-03-15'] + $pin(self::PIN))
            ->assertUnprocessable()->assertJsonPath('errors.consent.0', 'Отметьте согласие на обработку данных.');
        $identity['consent'] = true;
        $this->api('POST', '/api/v1/prisoner/register', $identity + $pin(self::PIN))
            ->assertUnprocessable()->assertJsonPath('errors.birth_date.0', 'Укажите дату рождения.');
        $this->api('POST', '/api/v1/prisoner/register', $identity + ['birth_date' => '1990-03-15'] + $pin('900101300123'))
            ->assertUnprocessable()->assertJsonPath('errors.pin.0', 'Пароль — ровно 6 цифр.');
        $this->api('POST', '/api/v1/prisoner/register', $identity + ['birth_date' => '1990-03-15', 'pin' => self::PIN, 'pin_confirmation' => '482916'])
            ->assertUnprocessable()->assertJsonValidationErrors('pin');
        foreach (['111111', '123456', '987654', '150390', '900315', '199003'] as $weak) {
            $this->api('POST', '/api/v1/prisoner/register', $identity + ['birth_date' => '1990-03-15'] + $pin($weak))
                ->assertUnprocessable()->assertJsonPath('errors.pin.0', 'Слишком простой пароль. Не используйте одинаковые цифры, цифры подряд и дату рождения.');
        }
        // Нельзя зарегистрировать карточку, подставив номер, если ФИО и условия не совпали.
        $this->api('POST', '/api/v1/prisoner/register', ['condition' => 'strict'] + $identity + ['birth_date' => '1990-03-15'] + $pin(self::PIN))
            ->assertNotFound();

        $token = $this->register($prisoner);
        $this->assertSame('1990-03-15', $prisoner->fresh()->birth_date->format('Y-m-d'));
        $this->api('GET', '/api/v1/prisoner/profile', [], $token)->assertOk()
            ->assertJsonPath('auth_method', 'pin')->assertJsonPath('recovery_method', 'birth_date')
            ->assertJsonMissingPath('iin')->assertJsonMissingPath('birth_date');
        $this->api('POST', '/api/v1/prisoner/register', $identity + ['birth_date' => '1990-03-15'] + $pin('583920'))->assertConflict();
        $this->api('POST', '/api/v1/prisoner/find', $identity)->assertJsonPath('matches.0.registered', true)
            ->assertJsonPath('matches.0.method', 'pin')->assertJsonPath('matches.0.recovery', 'birth_date');

        for ($i = 0; $i < 4; $i++) {
            $this->api('POST', '/api/v1/prisoner/login', $identity + ['secret' => '000000'])->assertUnauthorized();
        }
        $this->api('POST', '/api/v1/prisoner/login', $identity + ['secret' => '000000'])->assertStatus(423);
        $this->api('POST', '/api/v1/prisoner/login', $identity + ['secret' => self::PIN])->assertStatus(423);
        $admin = User::factory()->create();
        $this->actingAs($admin)->post('/admin/prisoners/'.$prisoner->id.'/unlock')->assertRedirect();
        $this->api('POST', '/api/v1/prisoner/login', $identity + ['secret' => self::PIN])->assertOk()->assertJsonStructure(['token']);
        $this->get('/admin/prisoners/'.$prisoner->id)->assertSee('Дата рождения: 15.03.1990')->assertSee('восстановление: по дате рождения');

        // Кто-то занял чужую карточку — сотрудник сбрасывает регистрацию, старые сеансы перестают работать.
        $this->actingAs($admin)->post('/admin/prisoners/'.$prisoner->id.'/reset-registration')->assertRedirect();
        $this->api('GET', '/api/v1/prisoner/profile', [], $token)->assertUnauthorized();
        $this->assertNull($prisoner->fresh()->pin_hash);
        $this->assertNull($prisoner->fresh()->birth_date);
        $this->register($prisoner, '583920', '1991-07-02');
        $this->api('POST', '/api/v1/prisoner/login', $identity + ['secret' => '583920'])->assertOk();
    }

    public function test_forgotten_pin_is_restored_by_birth_date_with_three_tries_per_day(): void
    {
        $prisoner = $this->prisoner();
        $this->bindTablet($prisoner->institution);
        $this->register($prisoner);
        $identity = ['full_name' => 'Иванов Иван', 'condition' => 'ordinary', 'prisoner_id' => $prisoner->id];
        $newPin = ['pin' => '735102', 'pin_confirmation' => '735102'];

        $this->api('POST', '/api/v1/prisoner/recover', $identity + ['proof' => '1990-03-16'] + $newPin)
            ->assertUnauthorized()->assertJsonPath('message', 'Дата рождения не совпадает. Осталось попыток: 2.');
        $this->api('POST', '/api/v1/prisoner/recover', $identity + ['proof' => '1990-03-15', 'pin' => '150390', 'pin_confirmation' => '150390'])
            ->assertUnprocessable()->assertJsonValidationErrors('pin');
        $this->api('POST', '/api/v1/prisoner/recover', $identity + ['proof' => '1990-03-15'] + $newPin)->assertOk()->assertJsonStructure(['token']);
        $this->api('POST', '/api/v1/prisoner/login', $identity + ['secret' => self::PIN])->assertUnauthorized();
        $this->api('POST', '/api/v1/prisoner/login', $identity + ['secret' => '735102'])->assertOk();

        // Три ошибки — восстановление закрыто на сутки, даже с верной датой.
        $this->api('POST', '/api/v1/prisoner/recover', $identity + ['proof' => '1980-01-01'] + $newPin)->assertUnauthorized();
        $this->api('POST', '/api/v1/prisoner/recover', $identity + ['proof' => '1980-01-02'] + $newPin)->assertUnauthorized();
        $this->api('POST', '/api/v1/prisoner/recover', $identity + ['proof' => '1980-01-03'] + $newPin)->assertStatus(423);
        $this->api('POST', '/api/v1/prisoner/recover', $identity + ['proof' => '1990-03-15'] + $newPin)->assertStatus(423);
        $this->travel(25)->hours();
        $this->api('POST', '/api/v1/prisoner/recover', $identity + ['proof' => '1990-03-15', 'pin' => '640218', 'pin_confirmation' => '640218'])->assertOk();
    }

    public function test_recovery_can_be_switched_to_security_question_in_settings(): void
    {
        $prisoner = $this->prisoner();
        $this->bindTablet($prisoner->institution);
        $token = $this->register($prisoner);
        $identity = ['full_name' => 'Иванов Иван', 'condition' => 'ordinary', 'prisoner_id' => $prisoner->id];

        $this->api('POST', '/api/v1/prisoner/recovery', ['current' => '111222', 'method' => 'question', 'question' => 'birth_place', 'answer' => 'Кокшетау'], $token)
            ->assertUnprocessable()->assertJsonValidationErrors('current');
        $this->api('POST', '/api/v1/prisoner/recovery', ['current' => self::PIN, 'method' => 'question', 'question' => 'birth_place'], $token)
            ->assertUnprocessable()->assertJsonValidationErrors('answer');
        $this->api('POST', '/api/v1/prisoner/recovery', ['current' => self::PIN, 'method' => 'question', 'question' => 'birth_place', 'answer' => 'Кокшетау'], $token)
            ->assertOk()->assertJsonPath('profile.recovery_method', 'question')
            ->assertJsonPath('profile.recovery_question', Prisoner::SECURITY_QUESTIONS['birth_place']);
        $this->api('POST', '/api/v1/prisoner/find', $identity)->assertJsonPath('matches.0.recovery', 'question')
            ->assertJsonPath('matches.0.recovery_question', Prisoner::SECURITY_QUESTIONS['birth_place']);

        // Теперь дата рождения не подходит, а ответ на вопрос — да.
        $this->api('POST', '/api/v1/prisoner/recover', $identity + ['proof' => '1990-03-15', 'pin' => '735102', 'pin_confirmation' => '735102'])
            ->assertUnauthorized()->assertJsonPath('message', 'Неверный ответ. Осталось попыток: 2.');
        $this->api('POST', '/api/v1/prisoner/recover', $identity + ['proof' => ' кокшетау ', 'pin' => '735102', 'pin_confirmation' => '735102'])->assertOk();

        // Вернуть дату рождения по умолчанию.
        $token = $this->api('POST', '/api/v1/prisoner/login', $identity + ['secret' => '735102'])->json('token');
        $this->api('POST', '/api/v1/prisoner/recovery', ['current' => '735102', 'method' => 'birth_date'], $token)
            ->assertOk()->assertJsonPath('profile.recovery_method', 'birth_date');

        // Смена пароля в настройках — тоже 6 цифр и не простой.
        $this->api('POST', '/api/v1/prisoner/credentials', ['current' => '735102', 'pin' => '123456', 'pin_confirmation' => '123456'], $token)
            ->assertUnprocessable()->assertJsonValidationErrors('pin');
        $this->api('POST', '/api/v1/prisoner/credentials', ['current' => '735102', 'pin' => '940271', 'pin_confirmation' => '940271'], $token)->assertOk();
        $this->api('POST', '/api/v1/prisoner/login', $identity + ['secret' => '940271'])->assertOk();
    }

    public function test_people_registered_earlier_with_twelve_digits_or_question_still_log_in(): void
    {
        $prisoner = $this->prisoner();
        $prisoner->setCredentials('pin', '900101300123', null, null);
        $prisoner->forceFill(['registered_at' => now()])->save();
        $other = $this->prisoner(['iin' => null, 'full_name' => 'Сидоров Пётр', 'squad' => '10']);
        $other->setCredentials('question', null, 'grandmother', 'Айгүль Ёлкина');
        $other->forceFill(['registered_at' => now()])->save();
        $this->bindTablet($prisoner->institution);

        // Прежнее приложение ищет по отряду — это продолжает работать.
        $this->api('POST', '/api/v1/prisoner/login', ['full_name' => 'Иванов Иван', 'squad' => '7', 'prisoner_id' => $prisoner->id, 'secret' => '900101300123'])->assertOk();
        $this->api('POST', '/api/v1/prisoner/login', ['full_name' => 'Иванов Иван', 'condition' => 'ordinary', 'prisoner_id' => $prisoner->id, 'secret' => '900101300123'])->assertOk();
        $this->api('POST', '/api/v1/prisoner/find', ['full_name' => 'сидоров петр', 'condition' => 'ordinary'])
            ->assertJsonPath('matches.0.question', Prisoner::SECURITY_QUESTIONS['grandmother'])->assertJsonPath('matches.0.recovery', 'question');
        $this->api('POST', '/api/v1/prisoner/login', ['full_name' => 'Сидоров Пётр', 'condition' => 'ordinary', 'prisoner_id' => $other->id, 'secret' => '  айгүль   елкина '])->assertOk();
        // Даты рождения у Иванова нет — восстановить сам не может, только сотрудник.
        $this->api('POST', '/api/v1/prisoner/recover', ['full_name' => 'Иванов Иван', 'condition' => 'ordinary', 'prisoner_id' => $prisoner->id,
            'proof' => '1990-03-15', 'pin' => '735102', 'pin_confirmation' => '735102'])->assertConflict();
    }

    public function test_change_credentials_and_family_code_gives_relatives_read_only_view_and_deposit_requests(): void
    {
        $prisoner = $this->prisoner(['spending_mode' => 'custom', 'monthly_limit_minor' => 2000000]);
        $prisoner->forceFill(['balance_minor' => 1500000, 'limit_started_on' => now('Asia/Almaty')->startOfMonth()])->save();
        $this->bindTablet($prisoner->institution);
        $token = $this->register($prisoner);

        $this->api('POST', '/api/v1/prisoner/credentials', ['current' => '111222', 'pin' => '735102', 'pin_confirmation' => '735102'], $token)
            ->assertUnprocessable()->assertJsonValidationErrors('current');
        $this->api('POST', '/api/v1/prisoner/credentials', ['current' => self::PIN, 'pin' => '735102', 'pin_confirmation' => '735102'], $token)
            ->assertOk()->assertJsonPath('profile.auth_method', 'pin');
        $this->assertTrue($prisoner->fresh()->checkSecret('735102'));

        $code = $this->api('POST', '/api/v1/prisoner/family-code', [], $token)->assertOk()->json('code');
        $this->assertMatchesRegularExpression('/^[0-9]{3} [0-9]{3}$/', $code);
        // Код можно посмотреть повторно в кабинете — записывать не обязательно.
        $this->api('GET', '/api/v1/prisoner/profile', [], $token)->assertJsonPath('family_code_active', true)->assertJsonPath('family_code', $code);

        $this->get('/family')->assertOk()->assertSee('Код для родственников')->assertSee('Фамилия родственника');
        $digits = str_replace(' ', '', $code);
        $this->post('/family', ['surname' => 'Петров', 'code' => $digits])->assertSessionHasErrors('code');
        $this->post('/family', ['surname' => 'Иванов', 'code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/family', ['surname' => 'иванов', 'code' => $digits, 'remember' => '1'])->assertRedirect('/family/balance')
            ->assertCookie('dosmart_family');
        $this->get('/family/balance')->assertRedirect('/family');
        $this->withCookie('dosmart_family', json_encode(['id' => $prisoner->id, 'hash' => Prisoner::familyCodeHash($digits)]));
        $this->get('/family/balance')->assertOk()->assertSee('Иванов Иван Петрович')->assertSee('15 000 ₸')
            ->assertSee('Осталось 20 000 ₸')->assertSee('Доставка в лимит не входит')->assertDontSee('900101300123')->assertDontSee('1990');

        $this->post('/family/deposit', ['sender_name' => 'Иванова Мария', 'sender_phone' => '+7 705 621 8748', 'amount' => 10000])
            ->assertSessionHasErrors('consent');
        $this->post('/family/deposit', ['sender_name' => 'Иванова Мария', 'sender_phone' => '+7 705 621 8748', 'amount' => 10000, 'consent' => '1'])
            ->assertRedirect('/family/balance');
        $deposit = DepositRequest::firstOrFail();
        $this->get('/family/balance')->assertSee('Заявка Р-'.$deposit->id, false)->assertSee('Ждёт оплаты');

        // Сотрудник сверяет Kaspi и зачисляет — в квитанции отправитель и получатель.
        $this->actingAs(User::factory()->create(['name' => 'Оператор Айжан']));
        $this->get('/admin/prisoners')->assertOk()->assertSee('Заявки родственников');
        $this->get('/admin/prisoners/requests')->assertOk()->assertSee('Иванова Мария')->assertSee('Иванов Иван Петрович');
        $credit = '/admin/prisoners/requests/'.$deposit->id.'/credit';
        $this->post($credit, ['amount' => '10000', 'payment_reference' => 'KSP-1'])->assertSessionHasErrors('payment_verified');
        $this->post($credit, ['amount' => '10000', 'payment_reference' => 'KSP-1', 'payment_verified' => '1'])->assertSessionHasNoErrors();
        $this->post($credit, ['amount' => '10000', 'payment_reference' => 'KSP-2', 'payment_verified' => '1'])->assertSessionHasErrors('payment_reference');
        $this->assertSame(2500000, $prisoner->fresh()->balance_minor);
        $this->get('/admin/prisoners/'.$prisoner->id)->assertSee('Код для родственников: '.$code);
        $transfer = $prisoner->transfers()->firstOrFail();
        $this->assertSame(['relative', 'Иванова Мария', 'credited'], [$transfer->source, $transfer->sender_name, $deposit->fresh()->status]);
        $this->get('/admin/prisoners/'.$prisoner->id.'/transfers/'.$transfer->id.'/receipt')->assertOk()
            ->assertSee('Отправитель:')->assertSee('Иванова Мария')->assertSee('Получатель:')->assertSee('Иванов Иван Петрович')->assertSee('10 000,00 тг');
        $this->api('GET', '/api/v1/prisoner/history', [], $token)->assertJsonPath('operations.0.title', 'Пополнение от родственника: Иванова Мария');

        // Новый код закрывает доступ по старому.
        $this->api('POST', '/api/v1/prisoner/family-code', [], $token)->assertOk();
        $this->get('/family/balance')->assertRedirect('/family');
        $this->api('POST', '/api/v1/prisoner/family-code/disable', [], $token)->assertOk();
        $this->assertNull($prisoner->fresh()->family_code_hash);
    }

    public function test_reject_deposit_request_shows_reason_to_relative(): void
    {
        $prisoner = $this->prisoner();
        $deposit = $prisoner->depositRequests()->create(['sender_name' => 'Петров', 'sender_phone' => '+77001112233', 'amount_minor' => 500000]);
        $this->actingAs(User::factory()->create());
        $this->post('/admin/prisoners/requests/'.$deposit->id.'/reject', [])->assertSessionHasErrors('reason');
        $this->post('/admin/prisoners/requests/'.$deposit->id.'/reject', ['reason' => 'Оплата не поступила'])->assertSessionHasNoErrors();
        $this->post('/admin/prisoners/requests/'.$deposit->id.'/reject', ['reason' => 'Ещё раз'])->assertStatus(409);
        $this->assertSame(['rejected', 'Оплата не поступила'], [$deposit->fresh()->status, $deposit->fresh()->reject_reason]);
        $this->get('/admin/prisoners/requests?status=rejected')->assertOk()->assertSee('Оплата не поступила');
    }

    public function test_person_without_squad_names_it_at_registration_and_it_is_fixed_after(): void
    {
        $prisoner = $this->prisoner(['squad' => null, 'iin' => null]);
        $this->bindTablet($prisoner->institution);
        $this->api('POST', '/api/v1/prisoner/find', ['full_name' => 'иванов иван', 'squad' => '12'])
            ->assertOk()->assertJsonPath('matches.0.id', $prisoner->id);
        $this->api('POST', '/api/v1/prisoner/register', ['full_name' => 'Иванов Иван', 'squad' => ' 12 ', 'prisoner_id' => $prisoner->id, 'consent' => true,
            'birth_date' => '1990-03-15', 'pin' => self::PIN, 'pin_confirmation' => self::PIN])->assertOk();
        $this->assertSame('12', $prisoner->fresh()->squad);
        $this->assertNotNull($prisoner->fresh()->consented_at);
        $this->assertSame('cabinet-2026-11-24', $prisoner->fresh()->consent_version);

        $this->api('POST', '/api/v1/prisoner/find', ['full_name' => 'иванов иван', 'squad' => '3'])
            ->assertOk()->assertJsonCount(0, 'matches');
        $this->api('POST', '/api/v1/prisoner/login', ['full_name' => 'Иванов Иван', 'squad' => '12', 'prisoner_id' => $prisoner->id, 'secret' => self::PIN])
            ->assertOk();
    }

    public function test_admin_import_people_filters_by_limit_and_norm_limit_excludes_delivery(): void
    {
        $prisoner = $this->prisoner();
        $institution = $prisoner->institution;
        $this->actingAs(User::factory()->create(['name' => 'Оператор Айжан']));
        $this->post('/admin/institutions/'.$institution->id.'/import', ['rows' => implode("\n", [
            'Петров Пётр Петрович; 3; ; строгие',
            "Сидоров Сидор\t12\t900202300456",
            'Иванов Иван Петрович; 9',
            'Кузнецов Кузьма Кузьмич; Облегченные',
            'Ив',
        ])])->assertSessionHas('success', 'Импорт: добавлено 3, обновлено 1.')->assertSessionHas('import_problems');
        $kuznetsov = Prisoner::where('full_name', 'Кузнецов Кузьма Кузьмич')->first();
        $this->assertNull($kuznetsov->squad);
        $this->assertSame('eased', $kuznetsov->condition);
        $this->assertSame('9', $prisoner->fresh()->squad);
        $this->assertSame('strict', Prisoner::where('full_name', 'Петров Пётр Петрович')->value('condition'));

        $base = '/admin/prisoners/'.$prisoner->id;
        $this->post($base.'/deposit', ['amount' => '100000', 'application_number' => 'З-15', 'application_date' => now('Asia/Almaty')->toDateString(),
            'payment_reference' => 'kaspi-777', 'payment_verified' => '1'])->assertSessionHasNoErrors();
        $this->patch('/admin/institutions/'.$institution->id.'/spending-limits', [
            'mrp_year' => now('Asia/Almaty')->year, 'mrp_value' => 4325, 'spend_ordinary' => 6, 'spend_strict' => 2,
        ])->assertSessionHasNoErrors();
        $settings = ['full_name' => 'Иванов Иван Петрович', 'squad' => '9', 'condition' => 'ordinary'];
        $this->patch($base, $settings + ['spending_mode' => 'custom'])->assertSessionHasErrors('monthly_limit');
        $this->patch($base, $settings + ['spending_mode' => 'norm'])->assertSessionHasNoErrors();
        $this->assertSame(now('Asia/Almaty')->startOfMonth()->toDateString(), $prisoner->fresh()->limit_started_on->toDateString());

        $this->get('/admin/prisoners/people?institution_id='.$institution->id.'&limit=with')->assertOk()
            ->assertSee('Иванов Иван Петрович')->assertDontSee('Сидоров Сидор')->assertSee('Планшеты учреждения')->assertSee('Импорт списка');
        $this->get('/admin/prisoners/people?limit=without')->assertOk()->assertSee('Сидоров Сидор')->assertDontSee('Иванов Иван Петрович');
        $this->get('/admin/prisoners/people?registered=no')->assertOk()->assertSee('Не зарегистрирован');

        // Норма 6 МРП = 25 950 ₸ на товары; доставка 3 000 ₸ сверх лимита.
        $this->bindTablet($institution);
        $token = $this->register($prisoner->fresh());
        $product = Product::create(['name' => 'Чай', 'price' => 25950, 'stock' => 10, 'is_active' => true]);
        $buy = fn (int $qty) => $this->api('POST', '/api/v1/prisoner/purchases', [
            'request_id' => (string) Str::uuid(), 'items' => [['product_id' => $product->id, 'quantity' => $qty]],
            'squad_number' => '9', 'consent' => true, 'expected_total_minor' => $qty * 2595000 + 300000,
        ], $token);
        $buy(1)->assertCreated();
        $buy(1)->assertUnprocessable()->assertJsonPath('errors.balance.0', 'Товары превышают остаток месячного лимита. Доставка в лимит не входит.');
        $this->api('GET', '/api/v1/prisoner/history', [], $token)->assertJsonPath('profile.monthly_remaining_minor', 0)
            ->assertJsonPath('profile.balance_minor', 10000000 - 2895000);

        $order = Order::firstOrFail();
        $this->get('/admin/orders/'.$order->id.'/receipt')->assertOk()->assertSee('От кого (ФИО):')
            ->assertSee('Экземпляр 3 из 3')->assertSee('Сам получатель — оплата с баланса Dosmart')->assertSee('Иванов Иван Петрович');
        $this->get($base)->assertOk()->assertSee('Оператор Айжан')->assertSee('Доставка в лимит не входит')->assertSee('Сбросить регистрацию');
    }
}
