<div class="checkout-heading"><div class="checkout-kicker">ВАША ЗАЯВКА</div>
<h1>{{ $order->order_number }}</h1>
<p class="order-status" role="status">{{ $pendingChange ? 'Согласование изменений' : $order->customer_status_label }}</p></div>
<nav style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px">
<a class="btn btn-ghost" href="{{ route('pilot.receipt', $order->draft->code) }}" target="_blank" rel="noopener noreferrer">Электронный чек</a>
</nav>
<section id="order-items" class="form-card" style="margin-bottom:20px">
<h2>Состав заявки</h2>
@foreach($order->items as $item)
<div class="summary-row"><span>{{ $item->product_name }}<br><small>{{ $item->quantity }} × {{ number_format($item->price,2,',',' ') }} ₸</small></span><strong>{{ number_format($item->total,2,',',' ') }} ₸</strong></div>
@endforeach
<div class="summary-row"><span>Товары</span><strong>{{ number_format($order->subtotal,2,',',' ') }} ₸</strong></div>
<div class="summary-row"><span>Доставка</span><span>{{ number_format($order->delivery_type === 'unconfirmed' ? (float) config('pilot.delivery_fee',3000) : $order->delivery_fee,2,',',' ') }} ₸</span></div>
<div class="summary-total"><span>{{ $order->delivery_type === 'unconfirmed' ? 'Предварительно с доставкой' : 'Итого с доставкой' }}</span><strong>{{ number_format($order->delivery_type === 'unconfirmed' ? (float) $order->subtotal + (float) config('pilot.delivery_fee',3000) : $order->total,2,',',' ') }} ₸</strong></div>
@if($order->delivery_type === 'unconfirmed')<p class="checkout-note">Дату передачи и итоговую сумму подтвердит сотрудник. Пока оплачивать не нужно.</p>@endif
</section>
@if($pendingChange)
<div class="form-card" style="margin-bottom:20px"><h2>Запрос изменений отправлен</h2><p>Сотрудник проверит наличие товаров и подтвердит новый состав. До его ответа сумма заказа не меняется. Пока оплачивать не нужно.</p></div>
@endif
@if($editable)
<details id="change-order" class="form-card" style="margin-bottom:20px" @if($errors->any()) open @endif>
<summary style="cursor:pointer;font-weight:700;font-size:20px">Изменить заявку</summary>
<p class="edit-intro">Измените количество или уберите лишнее. Сотрудник проверит изменения и подтвердит новую сумму.</p>
<form method="POST" action="{{ route('pilot.changes', $order->draft->code) }}" data-change-form data-fee="{{ $order->delivery_type === 'unconfirmed' ? config('pilot.delivery_fee',3000) : $order->delivery_fee }}">@csrf
<input type="hidden" name="revision" value="{{ $revision }}">
@include('shop.partials.order-catalog')
<h3>Товары в заявке</h3>
<div class="edit-products">
@foreach($order->items as $i => $item)
@php($quantity = (string) old('items.'.$i.'.id') === (string) $item->id ? old('items.'.$i.'.quantity', $item->quantity) : $item->quantity)
<article class="edit-product" data-edit-product data-price="{{ $item->price }}" data-original="{{ $item->quantity }}">
<div class="edit-photo">@if($item->product?->image_url)<img src="{{ $item->product->image_url }}" alt="{{ $item->product_name }}" loading="lazy" onerror="this.hidden=true">@else<span>Нет фото</span>@endif</div>
<div class="edit-product-info"><h3>{{ $item->product_name }}</h3><p>{{ number_format($item->price,0,',',' ') }} ₸ <span>/ {{ $item->product?->unit ?: 'шт.' }}</span></p></div>
<input type="hidden" name="items[{{ $i }}][id]" value="{{ $item->id }}">
<div class="edit-product-bottom"><div class="edit-stepper">
<button type="button" data-quantity-action="minus" aria-label="Уменьшить количество: {{ $item->product_name }}">−</button>
<input id="quantity-{{ $item->id }}" aria-label="Количество: {{ $item->product_name }}" type="number" inputmode="numeric" name="items[{{ $i }}][quantity]" min="0" max="1000" value="{{ $quantity }}" required>
<button type="button" data-quantity-action="plus" aria-label="Увеличить количество: {{ $item->product_name }}">+</button>
</div><strong data-line-total>{{ number_format($item->price * (is_numeric($quantity) ? $quantity : 0),0,',',' ') }} ₸</strong></div>
<button type="button" class="edit-remove" data-quantity-action="remove">Убрать из заявки</button>
</article>
@endforeach
</div>
<nav class="edit-pages" aria-label="Страницы товаров" data-edit-pagination hidden><button class="btn btn-ghost" type="button" data-edit-page="-1">← Назад</button><span data-page-label aria-live="polite"></span><button class="btn btn-ghost" type="button" data-edit-page="1">Далее →</button></nav>
<div class="edit-footer">
<label class="form-label" for="change-comment">Комментарий сотруднику <span class="edit-optional">необязательно</span></label>
<textarea class="form-control" id="change-comment" name="comment" maxlength="1000" rows="3" placeholder="Например: уточните срок годности йогурта">{{ old('comment') }}</textarea>
<div class="edit-estimate"><div><span>После изменений · с доставкой</span><strong data-edit-total></strong></div><p>Предварительная сумма. Оплачивать её можно после подтверждения сотрудником.</p></div>
<p class="form-hint" data-edit-hint aria-live="polite"></p>
<button class="btn btn-primary btn-block" type="submit" data-edit-submit>Отправить изменения</button>
</div>
</form></details>
@endif
<div id="unsaved-payment" class="form-card" hidden role="status" style="margin-bottom:20px"><h2>Сначала завершите изменения</h2><p>Вы изменили заявку. Отправьте изменения сотруднику на согласование или отмените их, чтобы вернуться к оплате согласованной суммы.</p><button type="button" class="btn btn-ghost" onclick="discardOrderEdits()">Отменить мои изменения</button></div>
<section id="order-payment" class="form-card" style="margin-bottom:20px"><h2>Оплата</h2>
@if($pendingChange)<p>Оплата появится после рассмотрения изменений сотрудником.</p>
@elseif($order->status === 'new')<p>Заявка получена. Сотрудник согласует состав и доставку с родственником. После этого здесь появится кнопка оплаты. Пока оплачивать не нужно.</p>
@elseif($order->status === 'pending' && $order->payment_reported_at)<h3>Заказ в обработке</h3><p>Вы сообщили об оплате. Сотрудник проверяет поступление денег. Повторно платить не нужно. <strong>Отправьте квитанцию в WhatsApp сотруднику и укажите номер заявки и ФИО получателя.</strong></p>
@elseif($order->status === 'pending')
<button class="btn btn-ghost" type="button" onclick="copyPayment(this.dataset.value)" data-value="{{ number_format($order->total,2,'.','') }}">Скопировать сумму</button>
<button class="btn btn-ghost" type="button" onclick="copyPayment(this.dataset.value)" data-value="{{ $order->order_number }}">Скопировать номер</button>
<ol style="padding-left:22px;line-height:1.8;margin:20px 0"><li>Откройте Kaspi и самостоятельно введите сумму <strong>{{ number_format($order->total,2,',',' ') }} ₸</strong>.</li><li>Если доступен комментарий к платежу, укажите номер заявки {{ $order->order_number }} и ФИО получателя.</li><li><strong>После оплаты отправьте квитанцию в WhatsApp сотруднику и укажите номер заявки и ФИО получателя.</strong></li><li>Вернитесь сюда и нажмите «Я оплатил». Сотрудник проверит поступление денег.</li></ol>
@if($kaspiUrl)<a class="btn btn-primary btn-block" href="{{ $kaspiUrl }}" target="_blank" rel="noopener noreferrer">Оплатить через Kaspi ↗</a>@else<p class="form-errors">Ссылка Kaspi пока не настроена. Свяжитесь с сотрудником.</p>@endif
<form method="POST" action="{{ route('pilot.report-payment', $order->draft->code) }}" style="margin-top:12px">@csrf<input type="hidden" name="revision" value="{{ $revision }}"><button class="btn btn-ghost btn-block" type="submit">Я оплатил — передать на проверку</button></form>
@elseif($order->status === 'cancelled')<p>Заявка отменена. Не оплачивайте её.</p>
@elseif($order->status === 'refunded')<p>Оформлен возврат: {{ number_format($order->refund_amount,2,',',' ') }} ₸. Подробности уточните у сотрудника.</p>
@else<p>Оплата подтверждена. Повторно платить не нужно. Изменения и возвраты оформляются через сотрудника.</p>@endif
@if($whatsapp)<a class="btn btn-ghost btn-block" style="margin-top:16px" href="https://wa.me/{{ $whatsapp }}?text={{ rawurlencode('Здравствуйте! По заявке '.$order->order_number) }}" target="_blank" rel="noopener noreferrer">Написать сотруднику в WhatsApp</a>@else<p class="form-hint">Для связи вернитесь в WhatsApp-чат, откуда получили ссылку.</p>@endif
</section>
<details class="form-card checkout-history"><summary>История заявки и изменений</summary>
@foreach($order->statusHistory as $event)<p><small>{{ $event->created_at->timezone('Asia/Almaty')->format('d.m.Y H:i') }}</small> · {{ ['new'=>'Заявка получена','pending'=>'Ожидает оплаты','paid'=>'Оплата подтверждена','checking'=>'Проверка состава','processing'=>'Собираем','delivering'=>'В доставке','delivered'=>'Доставлена','refunded'=>'Возврат','cancelled'=>'Отменена'][$event->status] ?? 'Обновление заявки' }}</p>@endforeach
@foreach($order->changeRequests->sortByDesc('id') as $change)
<p><strong>{{ ['pending'=>'Изменения на согласовании','approved'=>'Изменения подтверждены','rejected'=>'Изменения отклонены'][$change->status] }}</strong> · {{ $change->created_at->timezone('Asia/Almaty')->format('d.m.Y H:i') }}</p>
@foreach($change->items as $line)<div>{{ $line['name'] }}: {{ $line['before'] }} → {{ $line['quantity'] }}</div>@endforeach
@if($change->comment)<p>Ваше пожелание: {{ $change->comment }}</p>@endif
@if($change->response)<p>Ответ сотрудника: {{ $change->response }}</p>@endif
@endforeach
</details>
