<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Order;
use App\Models\Prisoner;
use App\Models\Product;
use App\Models\User;
use App\Services\PilotCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrisonerSelfRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private string $secret;

    private ?string $tablet = null;

    private Institution $institution;

    protected function setUp(): void
    {
        parent::setUp();
        $this->secret = str_repeat('s', 40);
        config(['pilot.prisoner_gateway_secret' => $this->secret]);
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->institution = Institution::create(['name' => 'ИК-48', 'city' => 'Петропавловск', 'is_active' => true, 'order_code' => '48']);
        $this->tablet = $this->api('POST', '/api/v1/tablet/setup', ['code' => $this->institution->issueTabletCode()])->assertOk()->json('token');
    }

    private function api(string $method, string $path, array $body = [], ?string $token = null)
    {
        $headers = ['X-Dosmart-Tablet-Gateway' => $this->secret, 'X-Dosmart-Tablet' => $this->tablet];
        if ($token) {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        return $this->withHeaders($headers)->json($method, $path, $body);
    }

    private function form(array $over = []): array
    {
        return $over + [
            'full_name' => 'Тестов Арман Серикович', 'condition' => 'ordinary', 'squad' => '5',
            'birth_date' => '1990-03-15', 'pin' => '482915', 'pin_confirmation' => '482915', 'consent' => true,
        ];
    }

    public function test_person_registers_without_search_and_lands_in_admin_for_review(): void
    {
        $this->api('POST', '/api/v1/prisoner/register', $this->form(['full_name' => 'Тестов']))->assertUnprocessable()->assertJsonValidationErrors('full_name');
        $this->api('POST', '/api/v1/prisoner/register', $this->form(['squad' => '']))->assertUnprocessable()->assertJsonValidationErrors('squad');
        $this->api('POST', '/api/v1/prisoner/register', $this->form(['condition' => 'privileged', 'pin' => '12345', 'pin_confirmation' => '12345']))
            ->assertUnprocessable()->assertJsonValidationErrors('pin');

        $token = $this->api('POST', '/api/v1/prisoner/register', $this->form(['condition' => 'privileged']))->assertOk()
            ->assertJsonPath('profile.reviewed', false)->assertJsonPath('profile.squad', '5')
            // Приложение ждёт число: новая карточка должна отдавать 0, а не null.
            ->assertJsonPath('profile.balance_minor', 0)
            ->assertJsonPath('profile.condition', 'Льготные')->json('token');
        $prisoner = Prisoner::firstOrFail();
        $this->assertSame([$this->institution->id, 'norm', 'privileged', '1990-03-15'],
            [$prisoner->institution_id, $prisoner->spending_mode, $prisoner->condition, $prisoner->birth_date->format('Y-m-d')]);
        $this->assertTrue($prisoner->needsReview());

        // Повторно с теми же ФИО и датой — уже зарегистрирован.
        $this->api('POST', '/api/v1/prisoner/register', $this->form(['pin' => '583920', 'pin_confirmation' => '583920']))->assertConflict();

        // До проверки покупки со счёта закрыты.
        $prisoner->forceFill(['balance_minor' => 1_000_000])->save();
        $product = Product::create(['name' => 'Чай', 'price' => 1000]);
        $purchase = ['request_id' => (string) Str::uuid(), 'expected_total_minor' => 400000, 'squad_number' => '5', 'consent' => true,
            'items' => [['product_id' => $product->id, 'quantity' => 1]]];
        $this->api('POST', '/api/v1/prisoner/purchases', $purchase, $token)->assertUnprocessable()->assertJsonValidationErrors('balance');

        // Админка: по учреждению видно, кто ждёт проверки; сотрудник проверяет.
        $this->actingAs(User::factory()->create());
        $this->get('/admin/prisoners/people?institution_id='.$this->institution->id.'&review=waiting')->assertOk()
            ->assertSee('Тестов Арман Серикович')->assertSee('Ждёт проверки');
        $this->get('/admin/prisoners/'.$prisoner->id)->assertSee('Зарегистрировался сам на планшете');
        $this->post('/admin/prisoners/'.$prisoner->id.'/review')->assertRedirect();
        $this->assertFalse($prisoner->fresh()->needsReview());
        // Норма учреждения ещё не задана — покупка со счёта не пройдёт, пока сотрудник не задаст норму или режим.
        $this->api('POST', '/api/v1/prisoner/purchases', $purchase, $token)->assertUnprocessable()->assertJsonValidationErrors('balance');
        $prisoner->fresh()->forceFill(['spending_mode' => 'unlimited'])->save(); // работает — «Без лимита» (ст. 105)
        $this->api('POST', '/api/v1/prisoner/purchases', $purchase, $token)->assertCreated();
    }

    public function test_registration_attaches_to_the_card_from_staff_list(): void
    {
        $card = Prisoner::create(['full_name' => 'Тестов Арман Серикович', 'institution_id' => $this->institution->id, 'condition' => 'strict',
            'spending_mode' => 'custom', 'monthly_limit_minor' => 500000, 'parcel_code' => strtoupper(Str::random(24))]);
        $this->assertFalse($card->needsReview());

        // Человек выбрал другие условия: карточка сотрудника не меняется, но уходит на проверку.
        $this->api('POST', '/api/v1/prisoner/register', $this->form(['full_name' => 'тестов  арман серикович']))->assertOk();
        $this->assertSame(1, Prisoner::count());
        $card->refresh();
        $this->assertSame(['strict', 'custom', '5'], [$card->condition, $card->spending_mode, $card->squad]);
        $this->assertTrue($card->needsReview());
    }

    public function test_login_by_name_birth_date_and_pin_recovery_three_times_a_day_and_squad_change(): void
    {
        $token = $this->api('POST', '/api/v1/prisoner/register', $this->form())->assertOk()->json('token');
        $login = ['full_name' => 'Тестов Арман Серикович', 'birth_date' => '1990-03-15'];

        $this->api('POST', '/api/v1/prisoner/login', ['birth_date' => '1990-03-16', 'secret' => '482915'] + $login)
            ->assertUnauthorized()->assertJsonPath('message', 'Неверные ФИО, дата рождения или пароль.');
        // Неверный пароль — тот же ответ, что и неверная дата: дату так не подобрать.
        $this->api('POST', '/api/v1/prisoner/login', $login + ['secret' => '000001'])->assertUnauthorized()
            ->assertJsonPath('message', 'Неверные ФИО, дата рождения или пароль.');
        $this->api('POST', '/api/v1/prisoner/login', $login + ['secret' => '482915'])->assertOk();

        // Отряд меняется в настройках, на вход не влияет.
        $this->api('POST', '/api/v1/prisoner/squad', ['squad' => ' 12 '], $token)->assertOk()->assertJsonPath('profile.squad', '12');
        $this->api('POST', '/api/v1/prisoner/login', $login + ['secret' => '482915'])->assertOk();

        // Забыл пароль: ФИО + дата рождения → новый пароль. Не больше 3 раз в сутки.
        $recover = fn (string $pin) => $this->api('POST', '/api/v1/prisoner/recover', $login + ['pin' => $pin, 'pin_confirmation' => $pin]);
        $this->api('POST', '/api/v1/prisoner/recover', ['full_name' => 'Тестов Арман', 'birth_date' => '1990-03-15', 'pin' => '735102', 'pin_confirmation' => '735102'])
            ->assertNotFound();
        $recover('735102')->assertOk();
        $this->api('POST', '/api/v1/prisoner/login', $login + ['secret' => '735102'])->assertOk();
        $recover('640218')->assertOk();
        $recover('951730')->assertOk();
        $recover('284061')->assertStatus(423);
        $this->api('POST', '/api/v1/prisoner/login', $login + ['secret' => '951730'])->assertOk();
        $this->assertNotNull(Prisoner::first()->recovered_at);
        $this->travel(25)->hours();
        $recover('284061')->assertOk();
    }

    public function test_login_by_name_and_pin_asks_birth_date_only_for_namesakes(): void
    {
        $this->api('POST', '/api/v1/prisoner/register', $this->form())->assertOk();
        $this->api('POST', '/api/v1/prisoner/login', ['full_name' => ' тестов  арман  серикович ', 'secret' => '482915'])->assertOk();
        $this->api('POST', '/api/v1/prisoner/login', ['full_name' => 'Тестов Арман Серикович', 'secret' => '000001'])
            ->assertUnauthorized()->assertJsonPath('message', 'Неверные ФИО или пароль.');
        $this->api('POST', '/api/v1/prisoner/login', ['full_name' => 'Нетакого Человека Нет', 'secret' => '482915'])
            ->assertUnauthorized()->assertJsonPath('message', 'Неверные ФИО или пароль.');

        // Полный тёзка сам не зарегистрируется (ответ не зависит от даты), его оформляет сотрудник.
        foreach (['1990-03-15', '1985-01-02'] as $date) {
            $this->api('POST', '/api/v1/prisoner/register', $this->form(['birth_date' => $date]))
                ->assertStatus(409)->assertJsonPath('message', 'Человек с такими ФИО уже зарегистрирован. Войдите или обратитесь к сотруднику магазина.');
        }
        $twin = Prisoner::create(['full_name' => 'Тестов Арман Серикович', 'institution_id' => $this->institution->id, 'condition' => 'ordinary', 'parcel_code' => str_repeat('A', 24)]);
        $twin->setPin('739104');
        $twin->forceFill(['birth_date' => '1985-01-02', 'registered_at' => now()])->save();
        // Без даты войти нельзя, с датой можно.
        $this->api('POST', '/api/v1/prisoner/login', ['full_name' => 'Тестов Арман Серикович', 'secret' => '482915'])
            ->assertStatus(409)->assertJsonPath('message', 'В учреждении есть человек с такими же ФИО. Укажите дату рождения.');
        $this->api('POST', '/api/v1/prisoner/login', ['full_name' => 'Тестов Арман Серикович', 'birth_date' => '1985-01-02', 'secret' => '739104'])->assertOk();
    }

    public function test_recovered_password_closes_purchases_until_staff_check_and_ends_old_sessions(): void
    {
        $old = $this->api('POST', '/api/v1/prisoner/register', $this->form())->assertOk()->json('token');
        $prisoner = Prisoner::firstOrFail();
        $prisoner->forceFill(['reviewed_at' => now()])->save();
        $this->api('GET', '/api/v1/prisoner/profile', [], $old)->assertOk()->assertJsonPath('reviewed', true);

        // Кто-то знает ФИО и дату рождения и ставит свой пароль.
        $new = $this->api('POST', '/api/v1/prisoner/recover', ['full_name' => 'Тестов Арман Серикович', 'birth_date' => '1990-03-15', 'pin' => '111111', 'pin_confirmation' => '111111'])
            ->assertOk()->json('token');
        $this->assertTrue($prisoner->fresh()->needsReview());
        $this->api('GET', '/api/v1/prisoner/profile', [], $old)->assertUnauthorized();
        $this->api('GET', '/api/v1/prisoner/profile', [], $new)->assertOk()->assertJsonPath('reviewed', false);
        $product = Product::create(['name' => 'Чай', 'price' => 1000]);
        $this->api('POST', '/api/v1/prisoner/purchases', [
            'request_id' => (string) Str::uuid(), 'expected_total_minor' => 100000, 'squad_number' => '5', 'consent' => true,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ], $new)->assertUnprocessable()->assertJsonValidationErrors('balance');
    }

    public function test_recovery_by_security_question_needs_the_answer(): void
    {
        $token = $this->api('POST', '/api/v1/prisoner/register', $this->form())->json('token');
        $this->api('POST', '/api/v1/prisoner/recovery', ['current' => '482915', 'method' => 'question', 'question' => 'birth_place', 'answer' => 'Кокшетау'], $token)->assertOk();
        $base = ['full_name' => 'Тестов Арман Серикович', 'birth_date' => '1990-03-15', 'pin' => '735102', 'pin_confirmation' => '735102'];
        $this->api('POST', '/api/v1/prisoner/recover', $base)->assertUnauthorized();
        $this->api('POST', '/api/v1/prisoner/recover', $base + ['proof' => 'Астана'])->assertUnauthorized();
        $this->api('POST', '/api/v1/prisoner/recover', $base + ['proof' => 'кокшетау'])->assertOk();
    }

    public function test_relative_order_is_blocked_when_yearly_parcels_by_law_are_used_up(): void
    {
        $this->api('POST', '/api/v1/prisoner/register', $this->form(['condition' => 'strict']))->assertOk();
        $prisoner = Prisoner::firstOrFail();
        $this->actingAs(User::factory()->create());
        // Нормы по закону: количество передач в год (строгие — 1 для теста).
        $this->patch('/admin/institutions/'.$this->institution->id.'/parcel-limits',
            ['mode' => 'count', 'ordinary' => 6, 'eased' => 12, 'strict' => 1, 'privileged' => 12])->assertSessionHasNoErrors();
        $this->assertSame(['ordinary' => 6, 'eased' => 12, 'strict' => 1, 'privileged' => 12], $this->institution->fresh()->parcel_limits);

        $product = Product::create(['name' => 'Чай', 'price' => 1000]);
        $draft = fn () => app(PilotCheckout::class)->create(['request_id' => (string) Str::uuid(), 'items' => [['product_id' => $product->id, 'quantity' => 1]]], 'website');
        $checkout = ['prisoner_name' => 'Тестов Арман Серикович', 'relative_name' => 'Тестова Мария', 'institution_id' => $this->institution->id,
            'contact_phone' => '+77001112233', 'delivery_type' => 'standard', 'consent' => 1, 'expected_total' => 4000, 'squad_number' => '5'];

        // Человека нет в списке учреждения — заказ не оформить.
        $free = $draft();
        $this->post('/o/'.$free->code, ['prisoner_name' => 'Другой Человек Без Кабинета'] + $checkout)->assertSessionHasErrors('prisoner_name');
        $this->assertSame(0, Order::count());

        // Есть в списке: достаточно ФИО, без даты рождения и кода.
        $first = $draft();
        $this->post('/o/'.$first->code, ['prisoner_name' => 'тестов  арман серикович'] + $checkout)->assertRedirect('/o/'.$first->code.'/payment');
        $order = Order::firstOrFail();
        $this->assertSame([$prisoner->id, 'parcel', 'reserved'], [$order->prisoner_id, $order->prisoner_order_type, $order->parcel_state]);

        // Передача на год использована — следующий заказ блокируется.
        $second = $draft();
        $this->postJson('/o/'.$second->code.'/recipient-limits', ['institution_id' => $this->institution->id,
            'prisoner_name' => 'Тестов Арман Серикович'])->assertOk()->assertJsonPath('parcels_available', 0)->assertJsonMissingPath('birth_date');
        $this->post('/o/'.$second->code, $checkout)
            ->assertSessionHasErrors(['prisoner_name' => 'У получателя закончились передачи на этот год. Заказ оформить нельзя.']);
        $this->assertSame(1, Order::count());
    }

    public function test_tablet_relative_order_from_cabinet_is_blocked_over_the_parcel_limit(): void
    {
        $token = $this->api('POST', '/api/v1/prisoner/register', $this->form(['condition' => 'strict']))->json('token');
        $this->institution->forceFill(['parcel_limits' => ['ordinary' => 6, 'eased' => 12, 'strict' => 1]])->save();
        $product = Product::create(['name' => 'Чай', 'price' => 1000]);
        $order = fn () => ['request_id' => (string) Str::uuid(), 'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'prisoner_name' => 'Тестов Арман Серикович', 'institution_id' => $this->institution->id,
            'contact_phone' => '+77001112233', 'consent' => true];

        // Без входа в кабинет — как раньше (старое приложение и каталог).
        $this->postJson('/api/v1/guest/orders', $order())->assertCreated();
        $this->assertNull(Order::latest('id')->first()->prisoner_id);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])->postJson('/api/v1/guest/orders', $order())->assertCreated();
        $this->assertSame('reserved', Order::latest('id')->first()->parcel_state);
        $this->withHeaders(['Authorization' => 'Bearer '.$token])->postJson('/api/v1/guest/orders', $order())
            ->assertUnprocessable()->assertJsonValidationErrors('prisoner_name');
        // Чужой или истёкший токен (например, от старого приложения) — заказ принимается как раньше.
        $this->withHeaders(['Authorization' => 'Bearer broken'])->postJson('/api/v1/guest/orders', $order())->assertCreated();
        $this->assertNull(Order::latest('id')->first()->prisoner_id);
        $this->assertSame(3, Order::count());
    }
}
