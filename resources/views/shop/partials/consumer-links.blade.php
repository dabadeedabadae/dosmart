@if(config('consumer.preview') || config('consumer.published'))
<nav aria-label="{{ trans('consumer.title', locale: 'ru') }}" class="checkout-note" style="display:flex;flex-wrap:wrap;gap:6px 14px;margin:12px 0">
    <a href="{{ route('shop.help', ['topic' => 'seller']) }}" target="_blank" rel="noopener">О продавце</a>
    <a href="{{ route('shop.help', ['topic' => 'delivery']) }}" target="_blank" rel="noopener">Доставка и оплата</a>
    <a href="{{ route('shop.help', ['topic' => 'terms']) }}" target="_blank" rel="noopener">Условия покупки</a>
    <a href="{{ route('shop.help', ['topic' => 'returns']) }}" target="_blank" rel="noopener">Возврат и претензии</a>
    <a href="{{ route('shop.help', ['topic' => 'privacy']) }}" target="_blank" rel="noopener">Обработка данных</a>
</nav>
@endif
