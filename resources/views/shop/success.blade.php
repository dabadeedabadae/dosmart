@extends('shop.layout')
@section('title', 'Заказ принят — DOSMART')

@section('content')
<div style="max-width:520px;margin:60px auto;text-align:center">

    <div style="width:72px;height:72px;background:var(--success-bg);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:36px">
        ✓
    </div>

    <h1 style="font-size:24px;font-weight:800;margin-bottom:8px">Заказ принят!</h1>

    <div id="order-number-block" style="display:none;margin-bottom:8px">
        <span style="font-size:15px;color:var(--text-secondary)">Номер заказа: </span>
        <span id="order-number" style="font-size:15px;font-weight:700;color:var(--accent)"></span>
    </div>

    <p style="font-size:15px;color:var(--text-secondary);margin-bottom:32px;line-height:1.6">
        Ваш заказ зарегистрирован. Оператор свяжется с вашим родственником в течение рабочего дня для подтверждения и оплаты.
    </p>

    <div class="form-card" style="text-align:left;margin-bottom:24px">
        <div style="font-size:14px;font-weight:700;margin-bottom:12px">Что дальше?</div>
        <div style="display:flex;flex-direction:column;gap:10px">
            <div style="display:flex;align-items:flex-start;gap:10px;font-size:13.5px;color:var(--text-secondary)">
                <span style="background:var(--accent);color:#fff;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0">1</span>
                Оператор проверит наличие товаров и свяжется с родственником
            </div>
            <div style="display:flex;align-items:flex-start;gap:10px;font-size:13.5px;color:var(--text-secondary)">
                <span style="background:var(--accent);color:#fff;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0">2</span>
                Родственник оплачивает заказ удобным способом
            </div>
            <div style="display:flex;align-items:flex-start;gap:10px;font-size:13.5px;color:var(--text-secondary)">
                <span style="background:var(--accent);color:#fff;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0">3</span>
                Заказ передаётся в учреждение в день доставки
            </div>
        </div>
    </div>

    <a href="{{ route('shop.index') }}" class="btn btn-primary btn-lg">
        Вернуться в магазин
    </a>

</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const params = new URLSearchParams(window.location.search);
    const orderNum = params.get('order') || localStorage.getItem('dosmart_last_order');
    if (orderNum) {
        document.getElementById('order-number').textContent = orderNum;
        document.getElementById('order-number-block').style.display = 'block';
    }
});
</script>
@endsection
