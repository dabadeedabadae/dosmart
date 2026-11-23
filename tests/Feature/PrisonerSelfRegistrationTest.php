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
        $this->api('POST', '/api/v1/prisoner/register', $this->form(['condition' => 'privileged', 'pin' => '150390', 'pin_confirmation' => '150390']))
            ->assertUnprocessable()->assertJsonValidationErrors('pin');

        $token = $this->api('POST', '/api/v1/prisoner/register', $this->form(['condition' => 'privileged']))->assertOk()
            ->assertJsonPath('profile.reviewed', false)->assertJsonPath('profile.squad', '5')
            ->assertJsonPath('profile.condition', 'Льготные')->json('token');
        $prisoner = Prisoner::firstOrFail();
        $this->assertSame([$this->institution->id, 'norm', 'privileged', '1990-03-15'],
            [$prisoner->institution_id, $prisoner->spending_mode, $prisoner->condition, $prisoner->birth_date->format('Y-m-d')]);
        $this->assertTrue($prisoner->needsReview());

        // Повторно с теми же ФИО и датой — уже зарегистрирован.
        $this->api('POST', '/api/v1/prisoner/register', $this->form(['pin' => '583920', 'pin_confirmation' => '583920']))->assertConflict();

        // До проверки покупки с баланса закрыты.
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
        // Норма учреждения ещё не задана — покупка с баланса не пройдёт, пока сотрудник не задаст норму или режим.
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
        $this->api('POST', '/api/v1/prisoner/login', $login + ['secret' => '000001'])->assertUnauthorized()
            ->assertJsonPath('message', 'Неверный пароль. Осталось попыток: 4.');
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

        // Получатель без кабинета — заказ оформляется как раньше, без проверки лимита.
        $free = $draft();
        $this->post('/o/'.$free->code, ['prisoner_name' => 'Другой Человек Без Кабинета'] + $checkout)->assertRedirect('/o/'.$free->code.'/payment');
        $this->assertNull(Order::firstOrFail()->prisoner_id);
        Order::query()->delete();

        // У получателя есть кабинет: без даты рождения или с неверной датой — не оформится.
        $first = $draft();
        $this->post('/o/'.$first->code, $checkout)->assertSessionHasErrors('recipient_birth_date');
        $this->post('/o/'.$first->code, $checkout + ['recipient_birth_date' => '1990-03-16'])->assertSessionHasErrors('recipient_birth_date');
        $this->post('/o/'.$first->code, $checkout + ['parcel_code' => str_repeat('A', 24)])->assertSessionHasErrors('parcel_code');
        $this->post('/o/'.$first->code, $checkout + ['recipient_birth_date' => '1990-03-15'])->assertRedirect('/o/'.$first->code.'/payment');
        $order = Order::firstOrFail();
        $this->assertSame([$prisoner->id, 'parcel', 'reserved'], [$order->prisoner_id, $order->prisoner_order_type, $order->parcel_state]);

        // Передача на год использована — следующий заказ блокируется.
        $second = $draft();
        $this->postJson('/o/'.$second->code.'/recipient-limits', ['institution_id' => $this->institution->id,
            'prisoner_name' => 'Тестов Арман Серикович', 'birth_date' => '1990-03-15'])->assertOk()->assertJsonPath('parcels_available', 0);
        $this->post('/o/'.$second->code, $checkout + ['recipient_birth_date' => '1990-03-15'])
            ->assertSessionHasErrors(['parcel_code' => 'У получателя закончились передачи на этот год по закону (ст. 136, 138 УИК РК). Заказ оформить нельзя.']);
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
            ->assertUnprocessable()->assertJsonValidationErrors('parcel_code');
        // Чужой или истёкший токен (например, от старого приложения) — заказ принимается как раньше.
        $this->withHeaders(['Authorization' => 'Bearer broken'])->postJson('/api/v1/guest/orders', $order())->assertCreated();
        $this->assertNull(Order::latest('id')->first()->prisoner_id);
        $this->assertSame(3, Order::count());
    }
}
