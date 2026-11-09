<?php
namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_filter_limits_selection_and_combines_products_for_courier(): void
    {
        $this->actingAs(User::factory()->create());
        $make = function ($number, $status, $payment, $quantity) {
            $order = Order::create(['order_number'=>$number, 'prisoner_name'=>'Тест', 'institution_name'=>'07', 'contact_phone'=>'+77011234567', 'subtotal'=>100, 'delivery_fee'=>3000, 'total'=>3100, 'status'=>$status, 'payment_status'=>$payment]);
            $order->items()->create(['product_name'=>'Чай 100 г', 'price'=>100, 'quantity'=>$quantity, 'total'=>100*$quantity]);
            return $order;
        };
        $a=$make('PAID-A','paid','paid',2);
        $b=$make('PAID-B','paid','paid',3);
        $other=$make('PROCESSING','processing','paid',7);
        $unpaid=$make('UNPAID','paid','unpaid',9);
        $url=route('admin.orders.report');
        $query=['date'=>now('Asia/Almaty')->format('Y-m-d'),'status'=>'paid','orders'=>[$a->id,$b->id,$other->id,$unpaid->id,999999]];
        $this->get($url.'?'.http_build_query($query))->assertOk()
            ->assertViewHas('orders',fn($orders)=>$orders->count()===3)
            ->assertViewHas('selected',fn($ids)=>$ids->all()===[$a->id,$b->id,$unpaid->id])
            ->assertViewHas('shopping',fn($rows)=>count($rows)===1&&$rows[0]['quantity']===14&&count($rows[0]['orders'])===3)
            ->assertSee('Курьер:')->assertSee('Куплено')->assertSee('Скопировать список');
        $this->get($url.'?status=invalid')->assertSessionHasErrors('status');
        $this->get($url.'?'.http_build_query(['date'=>$query['date'],'status'=>'pending','orders'=>[$a->id]]))
            ->assertOk()->assertViewHas('shopping',fn($rows)=>count($rows)===0)
            ->assertViewHas('selected',fn($ids)=>$ids->isEmpty());
    }

    public function test_daily_report_uses_kazakhstan_day_and_all_selected_statuses_for_shopping(): void
    {
        $url = route('admin.orders.report');
        $this->get($url)->assertRedirect(route('admin.login'));
        $make = function ($number, $at, $status, $payment) {
            $o = Order::create(['order_number'=>$number, 'prisoner_name'=>'Тест', 'institution_name'=>'07', 'contact_phone'=>'+77011234567', 'subtotal'=>100, 'delivery_fee'=>3000, 'total'=>3100, 'status'=>$status, 'payment_status'=>$payment]);
            $o->forceFill(['created_at'=>$at])->save();
            $o->items()->create(['product_name'=>'Чай 100 г', 'price'=>100, 'quantity'=>2, 'total'=>200]);
            return $o;
        };
        $paid = $make('PAID', '2026-10-17 19:00:00', 'paid', 'paid');
        $unpaid = $make('UNPAID', '2026-10-18 18:59:59', 'pending', 'pending');
        $outside = $make('OUTSIDE', '2026-10-18 19:00:00', 'paid', 'paid');
        $delivered = $make('DONE', '2026-10-18 10:00:00', 'delivered', 'paid');
        $this->actingAs(User::factory()->create());
        $response = $this->get($url.'?'.http_build_query(['date'=>'2026-10-18', 'orders'=>[$paid->id,$unpaid->id,$outside->id,$delivered->id]]));
        $response->assertOk()->assertViewHas('orders', fn ($orders)=>$orders->count()===3)
            ->assertViewHas('shopping', fn ($rows)=>count($rows)===1 && $rows[0]['quantity']===6)
            ->assertViewHas('stats', fn ($stats)=>$stats['Всего заявок']===3 && $stats['Доставлены']===1 && $stats['Оплачены']===2);
        $paid->statusHistory()->create(['status'=>'processing', 'comment'=>'operator: Начата сборка', 'created_at'=>'2026-10-18 10:00:00']);
        $event=$paid->statusHistory()->first();
        $event->forceFill(['created_at'=>'2026-10-18 10:00:00'])->save();
        $this->get($url.'?date=2026-10-18&scope=activity')->assertOk()->assertViewHas('orders', fn ($orders)=>$orders->count()===1)->assertSee('operator: Начата сборка');
        $this->get($url.'?date=wrong')->assertSessionHasErrors('date');
    }
}
